<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', addcslashes($request->query('action'), '%_\\').'%'))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->query('user')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', '%'.addcslashes($request->query('search'), '%_\\').'%'))
            ->latest('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ['user', 'role', 'lead', 'client', 'invoice', 'payment', 'settings'],
        ]);
    }
}
