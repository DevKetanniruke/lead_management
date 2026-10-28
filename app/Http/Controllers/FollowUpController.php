<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = PerPage::from($request);

        $open = fn () => Activity::query()
            ->where('type', 'task')
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->visibleTo($user)
            ->with(['lead.status', 'client', 'user'])
            ->orderBy('due_at')
            ->orderBy('id');

        $startOfToday = now()->startOfDay();
        $endOfToday = now()->endOfDay();

        // Each group pages independently (overdue_page, today_page, upcoming_page).
        return view('follow-ups.index', [
            'overdue' => $open()->where('due_at', '<', $startOfToday)
                ->paginate($perPage, ['*'], 'overdue_page')->withQueryString(),
            'today' => $open()->whereBetween('due_at', [$startOfToday, $endOfToday])
                ->paginate($perPage, ['*'], 'today_page')->withQueryString(),
            'upcoming' => $open()->where('due_at', '>', $endOfToday)
                ->paginate($perPage, ['*'], 'upcoming_page')->withQueryString(),
        ]);
    }
}
