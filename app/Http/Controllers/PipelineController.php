<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = PerPage::from($request);

        $totals = Lead::visibleTo($user)->selectRaw('lead_status_id, count(*) as total')
            ->groupBy('lead_status_id')->pluck('total', 'lead_status_id');

        $statuses = LeadStatus::orderBy('position')->get();

        // First page of cards for each column; the rest load on demand.
        $leads = $statuses->mapWithKeys(
            fn (LeadStatus $s) => [$s->id => $this->columnQuery($user, $s)->limit($perPage)->get()]
        );

        return view('pipeline.index', compact('statuses', 'leads', 'totals', 'perPage'));
    }

    /** Next batch of cards for one column (the "Show more" button). */
    public function column(Request $request, LeadStatus $status): JsonResponse
    {
        $user = $request->user();
        $offset = max(0, (int) $request->query('offset'));

        $leads = $this->columnQuery($user, $status)->skip($offset)->limit(PerPage::from($request))->get();

        return response()->json([
            'html' => view('pipeline._cards', compact('leads', 'status'))->render(),
            'count' => $leads->count(),
        ]);
    }

    public function move(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['lead_status_id' => ['required', 'exists:lead_statuses,id']]);
        $to = LeadStatus::findOrFail($data['lead_status_id']);

        if ($lead->isConverted() && ! $to->is_won) {
            return response()->json(['message' => 'Converted leads stay in Won / Converted; manage the relationship from the client record.'], 422);
        }

        $lead->changeStatus($to, $request->user());
        $lead->load('status');

        // Dropped into a won stage without a client yet: offer the conversion workflow.
        $convertUrl = $lead->status->is_won && $request->user()->can('convert', $lead)
            ? route('conversions.create', $lead) : null;

        return response()->json(['ok' => true, 'converted_at' => $lead->converted_at, 'convert_url' => $convertUrl, 'lead_name' => $lead->name]);
    }

    private function columnQuery(User $user, LeadStatus $status): Builder
    {
        return Lead::visibleTo($user)
            ->where('lead_status_id', $status->id)
            ->with(['assignee:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }
}
