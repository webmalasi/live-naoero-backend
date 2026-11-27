<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(Request $request): View
    {
        $query = ActivityLog::with('user');

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Module Filter
        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        // Action Filter
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // User Filter
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->latest()->paginate(15)->withQueryString();
        $modules = ActivityLog::distinct()->pluck('module');
        $actions = ActivityLog::distinct()->pluck('action');
        $users = User::orderBy('name')->get();

        return view('admin.logs.index', compact('logs', 'modules', 'actions', 'users'));
    }

    /**
     * Clear all logs (Super Admin only).
     */
    public function clear(): RedirectResponse
    {
        ActivityLog::truncate();

        ActivityLog::log('clear', 'System', 'Audit log database table was truncated.');

        return back()->with('success', 'Audit trail logs cleared successfully.');
    }
}
