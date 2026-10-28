<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'type' => ['required', Rule::in(Activity::TYPES)],
            'body' => ['required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date', Rule::requiredIf($request->input('type') === 'task')],
        ]);

        if ($data['type'] !== 'task') {
            $data['due_at'] = null;
        }

        $lead->activities()->create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('leads.show', $lead)->with('status', 'Activity logged.');
    }

    public function complete(Request $request, Activity $activity): RedirectResponse
    {
        abort_unless($activity->type === 'task', 404);
        $this->authorizeActivity($activity);

        $activity->update(['completed_at' => $activity->completed_at ? null : now()]);

        return back();
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorizeActivity($activity);

        $activity->delete();

        return back()->with('status', 'Activity removed.');
    }

    /** Lead entries follow lead edit rights; client entries follow client note rights. */
    private function authorizeActivity(Activity $activity): void
    {
        if ($activity->lead_id) {
            Gate::authorize('update', $activity->lead()->withTrashed()->firstOrFail());
        } else {
            Gate::authorize('addNote', $activity->client()->firstOrFail());
        }
    }
}
