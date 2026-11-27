<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\Notice;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    /**
     * Display a listing of all notices with filtering, counts, and global status.
     */
    public function index(Request $request): View
    {
        $query = Notice::query();

        // Status Filter
        $status = $request->input('status', 'all');
        $now = now();

        if ($status === 'active') {
            $query->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                });
        } elseif ($status === 'scheduled') {
            $query->where('is_active', true)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now);
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<', $now);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('badge_text', 'like', "%{$search}%");
            });
        }

        $notices = $query->ordered()->paginate(15)->withQueryString();

        // Stats counts
        $allNotices = Notice::all();
        $totalCount = $allNotices->count();
        $activeCount = $allNotices->filter->is_currently_active->count();
        $scheduledCount = $allNotices->filter->is_scheduled->count();
        $expiredCount = $allNotices->filter->is_expired->count();
        $inactiveCount = $allNotices->where('is_active', false)->count();
        $masterEnabled = SiteSetting::get('notice_bar_enabled', '1') === '1';

        return view('admin.notices.index', compact(
            'notices',
            'totalCount',
            'activeCount',
            'scheduledCount',
            'expiredCount',
            'inactiveCount',
            'masterEnabled',
            'status',
            'search'
        ));
    }

    /**
     * Show form for creating a new notice announcement.
     */
    public function create(): View
    {
        $noticeTypes = DropdownOption::getGroup('notice_types');
        return view('admin.notices.create', compact('noticeTypes'));
    }

    /**
     * Store a newly created notice in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'badge_text' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
            'link' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['priority'] = (int) ($validated['priority'] ?? 0);
        $data['badge_text'] = $validated['badge_text'] ?: ($validated['title'] ?? 'Update and News');

        // Handle scheduled start time & duration
        if (!empty($validated['starts_at'])) {
            $data['starts_at'] = Carbon::parse($validated['starts_at']);
        } else {
            $data['starts_at'] = now();
        }

        if (!empty($validated['duration_hours']) && $validated['duration_hours'] > 0) {
            $data['duration_hours'] = (int) $validated['duration_hours'];
            $data['expires_at'] = Carbon::parse($data['starts_at'])->copy()->addHours($data['duration_hours']);
        } elseif (!empty($validated['expires_at'])) {
            $data['expires_at'] = Carbon::parse($validated['expires_at']);
            $data['duration_hours'] = (int) Carbon::parse($data['starts_at'])->diffInHours($data['expires_at']);
        } else {
            $data['duration_hours'] = null;
            $data['expires_at'] = null; // Indefinite / permanent
        }

        $notice = Notice::create($data);

        ActivityLog::log('created', 'notices', "Created notice announcement: '{$notice->title}'");

        return redirect()->route('admin.notices.index')->with('success', 'Notice announcement published successfully.');
    }

    /**
     * Show form to edit an existing notice.
     */
    public function edit(Notice $notice): View
    {
        $noticeTypes = DropdownOption::getGroup('notice_types');
        return view('admin.notices.edit', compact('notice', 'noticeTypes'));
    }

    /**
     * Update the specified notice in storage.
     */
    public function update(Request $request, Notice $notice): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'badge_text' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
            'link' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active');
        $data['priority'] = (int) ($validated['priority'] ?? 0);
        $data['badge_text'] = $validated['badge_text'] ?: ($validated['title'] ?? 'Update and News');

        if (!empty($validated['starts_at'])) {
            $data['starts_at'] = Carbon::parse($validated['starts_at']);
        } else {
            $data['starts_at'] = null;
        }

        if (!empty($validated['duration_hours']) && $validated['duration_hours'] > 0) {
            $data['duration_hours'] = (int) $validated['duration_hours'];
            $base = $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();
            $data['expires_at'] = $base->copy()->addHours($data['duration_hours']);
        } elseif (!empty($validated['expires_at'])) {
            $data['expires_at'] = Carbon::parse($validated['expires_at']);
            $base = $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();
            $data['duration_hours'] = max(0, (int) $base->diffInHours($data['expires_at']));
        } else {
            $data['duration_hours'] = null;
            $data['expires_at'] = null;
        }

        $notice->update($data);

        ActivityLog::log('updated', 'notices', "Updated notice announcement #{$notice->id}: '{$notice->title}'");

        return redirect()->route('admin.notices.index')->with('success', 'Notice announcement updated successfully.');
    }

    /**
     * Remove notice from database.
     */
    public function destroy(Notice $notice): RedirectResponse
    {
        $title = $notice->title;
        $notice->delete();

        ActivityLog::log('deleted', 'notices', "Deleted notice announcement: '{$title}'");

        return redirect()->route('admin.notices.index')->with('success', 'Notice announcement deleted successfully.');
    }

    /**
     * Toggle active/deactive status for a specific notice.
     */
    public function toggleActive(Request $request, Notice $notice): JsonResponse|RedirectResponse
    {
        $notice->is_active = ! $notice->is_active;
        
        // If reactivating an expired notice without duration change, allow extending by 24h if expired
        if ($notice->is_active && $notice->isExpired()) {
            $notice->starts_at = now();
            $hours = $notice->duration_hours ?: 24;
            $notice->duration_hours = $hours;
            $notice->expires_at = now()->addHours($hours);
        }

        $notice->save();

        $statusText = $notice->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'notices', "Notice #{$notice->id} {$statusText}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $notice->is_active,
                'status' => $notice->status,
                'status_text' => $notice->status_text,
                'message' => "Notice announcement {$statusText} successfully.",
            ]);
        }

        return back()->with('success', "Notice announcement {$statusText} successfully.");
    }

    /**
     * Quick extend duration / time for a notice.
     */
    public function extendTime(Request $request, Notice $notice): RedirectResponse
    {
        $hours = (int) $request->input('hours', 24);

        if ($hours > 0) {
            $base = ($notice->expires_at && $notice->expires_at->isFuture()) 
                ? $notice->expires_at->copy() 
                : now();

            $newExpiry = $base->addHours($hours);
            $notice->expires_at = $newExpiry;
            $notice->duration_hours = ($notice->duration_hours ?? 0) + $hours;
            $notice->is_active = true;
            $notice->save();

            ActivityLog::log('updated', 'notices', "Extended notice #{$notice->id} duration by {$hours} hours");

            return back()->with('success', "Notice extended by {$hours} hours. Valid until {$newExpiry->format('M d, Y H:i')}.");
        }

        return back()->with('warning', 'Invalid duration specified.');
    }

    /**
     * Master Switch: Toggle entire Notice Bar globally ON / OFF.
     */
    public function toggleMaster(Request $request): JsonResponse|RedirectResponse
    {
        $setting = SiteSetting::firstOrCreate(
            ['key' => 'notice_bar_enabled'],
            ['group' => 'general', 'type' => 'boolean', 'label' => 'Header Notice Bar Master Switch']
        );

        $currentState = ($setting->value === '1' || $setting->value === 'true' || $setting->value === true);
        $newState = ! $currentState;
        $setting->update(['value' => $newState ? '1' : '0']);

        $stateLabel = $newState ? 'ENABLED globally' : 'DISABLED globally';
        ActivityLog::log('updated', 'settings', "Master Announcement Notice Bar {$stateLabel}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'master_enabled' => $newState,
                'message' => "Announcement Notice Bar is now {$stateLabel}.",
            ]);
        }

        return back()->with('success', "Header Announcement Notice Bar is now {$stateLabel}.");
    }

    /**
     * Bulk action: Activate all, Deactivate all, Delete selected.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $ids = $request->input('ids', []);

        if (empty($ids) && in_array($action, ['activate_all', 'deactivate_all'])) {
            if ($action === 'activate_all') {
                Notice::query()->update(['is_active' => true]);
                ActivityLog::log('updated', 'notices', 'Activated all notice announcements');
                return back()->with('success', 'All notice announcements have been activated.');
            }

            if ($action === 'deactivate_all') {
                Notice::query()->update(['is_active' => false]);
                ActivityLog::log('updated', 'notices', 'Deactivated all notice announcements');
                return back()->with('success', 'All notice announcements have been deactivated.');
            }
        }

        if (!empty($ids) && is_array($ids)) {
            if ($action === 'activate') {
                Notice::whereIn('id', $ids)->update(['is_active' => true]);
                return back()->with('success', count($ids) . ' notices activated.');
            }

            if ($action === 'deactivate') {
                Notice::whereIn('id', $ids)->update(['is_active' => false]);
                return back()->with('success', count($ids) . ' notices deactivated.');
            }

            if ($action === 'delete') {
                Notice::whereIn('id', $ids)->delete();
                return back()->with('success', count($ids) . ' notices deleted.');
            }
        }

        return back()->with('warning', 'No action was performed.');
    }
}
