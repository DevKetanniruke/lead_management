<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\AccountSetup;
use App\Notifications\AccountStatusChanged;
use App\Services\UserPerformance;
use App\Services\UserRecords;
use App\Support\Audit;
use App\Support\PerPage;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    private const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->with('roles')
            ->withCount('assignedLeads')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->query('search'), '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('department', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->role($request->query('role')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy($sort, $dir)
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Permissions::ROLES,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User, 'roles' => Permissions::ROLES]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $sendLink = $request->boolean('send_setup_link') && empty($data['password']);

        $user = DB::transaction(function () use ($data, $request, $sendLink) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'department' => $data['department'] ?? null,
                // With a setup link the user picks their own password; this random one is never shared.
                'password' => $sendLink ? Str::password(32) : $data['password'],
            ]);
            $user->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
            $user->syncRoles($data['role']);

            if ($request->hasFile('photo')) {
                $user->forceFill(['profile_photo_path' => $request->file('photo')->store('avatars', 'local')])->save();
            }

            Audit::log('user.created', $user, "User {$user->name} created as ".Permissions::roleLabel($data['role']), [
                'email' => $user->email,
                'role' => $data['role'],
            ]);

            return $user;
        });

        $message = 'User created.';
        if ($sendLink) {
            $message .= $this->sendSetupLink($user) ? ' A password setup link was emailed.' : ' The setup email could not be sent; use "Send reset link" to retry.';
        }

        return redirect()->route('users.index')->with('status', $message);
    }

    public function show(Request $request, User $user, UserPerformance $performance, UserRecords $records): View
    {
        $user->load('roles');

        return view('users.show', [
            'user' => $user,
            'stats' => $performance->forUser($user),
            'records' => $records->counts($user),
            'leads' => Lead::where('assigned_to', $user->id)->with(['status'])->latest()
                ->paginate(PerPage::from($request))->withQueryString(),
            'reassignTo' => User::active()->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => Permissions::ROLES]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $oldRole = $user->roleName();

        $privilegedRoles = [Permissions::ADMIN, Permissions::SUPERADMIN];
        if ($user->is($request->user()) && ! in_array($data['role'], $privilegedRoles, true)) {
            return back()->withErrors(['role' => 'You cannot remove your own privileged role.'])->withInput();
        }

        if (in_array($oldRole, $privilegedRoles, true) && ! in_array($data['role'], $privilegedRoles, true) && $this->isLastActiveAdmin($user)) {
            return back()->withErrors(['role' => 'At least one active privileged account is required.'])->withInput();
        }

        DB::transaction(function () use ($user, $data, $request, $oldRole) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'department' => $data['department'] ?? null,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
                $this->deletePhoto($user);
            }
            if ($request->hasFile('photo')) {
                $user->profile_photo_path = $request->file('photo')->store('avatars', 'local');
            }

            $changes = array_keys($user->getDirty());
            $user->save();
            $user->syncRoles($data['role']);

            if ($oldRole !== $data['role']) {
                Audit::log('user.role_changed', $user, "{$user->name}'s role changed from ".Permissions::roleLabel($oldRole).' to '.Permissions::roleLabel($data['role']), [
                    'from' => $oldRole,
                    'to' => $data['role'],
                ]);
            }

            if ($changes) {
                Audit::log('user.updated', $user, "User {$user->name} updated", ['fields' => array_values(array_diff($changes, ['password', 'updated_at']))]);
            }
        });

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        if ($user->is_active) {
            return back();
        }

        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        Audit::log('user.activated', $user, "User {$user->name} activated");
        $this->notifySafely($user, new AccountStatusChanged(true));

        return back()->with('status', "{$user->name} has been activated.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['status' => 'You cannot deactivate your own account.']);
        }
        if ($user->hasAnyRole([Permissions::ADMIN, Permissions::SUPERADMIN]) && $this->isLastActiveAdmin($user)) {
            return back()->withErrors(['status' => 'At least one active privileged account is required.']);
        }
        if (! $user->is_active) {
            return back();
        }

        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
        $this->endSessions($user);
        Audit::log('user.deactivated', $user, "User {$user->name} deactivated");
        $this->notifySafely($user, new AccountStatusChanged(false));

        return back()->with('status', "{$user->name} has been deactivated and signed out.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        if (! $user->is_active) {
            return back()->withErrors(['status' => 'Activate the account before sending a reset link.']);
        }

        try {
            $result = Password::broker()->sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e);
            $result = null;
        }

        Audit::log('user.password_reset_sent', $user, "Password reset link sent to {$user->name}");

        return $result === Password::RESET_LINK_SENT
            ? back()->with('status', "A password reset link was emailed to {$user->email}.")
            : back()->withErrors(['status' => 'The reset link could not be sent. Please try again shortly.']);
    }

    /** Lock the user out of every session and require a new password via emailed link. */
    public function resetAccess(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['status' => 'Use your profile page to change your own password.']);
        }

        $user->forceFill(['password' => Str::password(32), 'remember_token' => Str::random(60)])->save();
        $this->endSessions($user);
        Audit::log('user.access_reset', $user, "Access reset for {$user->name}: sessions ended, password invalidated");

        $sent = $user->is_active && $this->sendSetupLink($user);

        return back()->with('status', "{$user->name}'s sessions were ended and their password invalidated."
            .($sent ? ' A link to choose a new password was emailed.' : ''));
    }

    public function reassign(Request $request, User $user, UserRecords $records): RedirectResponse
    {
        $data = $request->validate([
            'to_user_id' => ['required', Rule::exists('users', 'id')->where('is_active', true), Rule::notIn([$user->id])],
        ], ['to_user_id.required' => 'Choose who should take over the records.']);

        $moved = $records->reassign($user, User::findOrFail($data['to_user_id']), $request->user());

        return back()->with('status', collect($moved)->map(fn ($n, $type) => "$n $type")->implode(', ').' reassigned.');
    }

    public function destroy(Request $request, User $user, UserRecords $records): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['delete' => 'You cannot delete your own account here.']);
        }

        if ($user->hasAnyRole([Permissions::ADMIN, Permissions::SUPERADMIN])) {
            return back()->withErrors(['delete' => 'Privileged accounts cannot be deleted. Assign another privileged account and change this user to a standard role first.']);
        }

        if ($counts = $records->counts($user)) {
            $summary = collect($counts)->map(fn ($n, $type) => "$n $type")->implode(', ');

            return back()->withErrors(['delete' => "{$user->name} still owns business records ($summary). Deactivate the account or reassign the records instead."]);
        }

        Audit::log('user.deleted', $user, "User {$user->name} deleted", ['email' => $user->email]);
        $this->deletePhoto($user);
        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    public function photo(User $user): StreamedResponse
    {
        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);

        return Storage::disk('local')->response($user->profile_photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function sendSetupLink(User $user): bool
    {
        try {
            $user->notify(new AccountSetup(Password::broker()->createToken($user)));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function notifySafely(User $user, $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function deletePhoto(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('local')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
        }
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return ! User::role([Permissions::ADMIN, Permissions::SUPERADMIN])->active()->whereKeyNot($user->id)->exists();
    }
}
