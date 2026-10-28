<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\UserRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update which notifications the user receives by email.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['array'], 'email.*' => ['in:1']]);

        $chosen = array_keys($request->input('email', []));
        $request->user()->forceFill([
            'notification_preferences' => collect(User::NOTIFICATION_TYPES)
                ->mapWithKeys(fn ($label, $type) => [$type => in_array($type, $chosen, true)])->all(),
        ])->save();

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (app(UserRecords::class)->hasRecords($user)) {
            return back()->withErrors(['password' => 'Your account owns business records and cannot be deleted. Ask an administrator to deactivate it instead.'], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
