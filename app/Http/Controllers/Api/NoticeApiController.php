<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeApiController extends Controller
{
    /**
     * Get active header notice bar data for frontend ticker (NoticeBar.jsx).
     *
     * GET /api/notice-bar
     */
    public function noticeBar(): JsonResponse
    {
        // Check global master switch
        $masterSetting = SiteSetting::where('key', 'notice_bar_enabled')->first();
        $masterEnabled = $masterSetting ? ($masterSetting->value === '1' || $masterSetting->value === 'true' || $masterSetting->value === true) : true;

        if (!$masterEnabled) {
            return response()->json([
                'success' => true,
                'master_enabled' => false,
                'data' => null,
                'items' => [],
                'count' => 0,
                'message' => 'Notice bar is currently turned off by global master switch.',
            ]);
        }

        $activeNotices = Notice::query()
            ->active()
            ->ordered()
            ->get();

        $formatted = $activeNotices->map(fn (Notice $n) => $this->formatNotice($n));
        $primary = $formatted->first() ?: null;

        return response()->json([
            'success' => true,
            'master_enabled' => true,
            'data' => $primary,
            'items' => $formatted,
            'count' => $formatted->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Display a listing of active notices with optional filtering.
     *
     * GET /api/notices
     */
    public function index(Request $request): JsonResponse
    {
        $masterSetting = SiteSetting::where('key', 'notice_bar_enabled')->first();
        $masterEnabled = $masterSetting ? ($masterSetting->value === '1' || $masterSetting->value === 'true' || $masterSetting->value === true) : true;

        $query = Notice::query()->active()->ordered();

        // Optional filter by notice type (info, warning, alert, success)
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Optional search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('badge_text', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $limit = $request->integer('limit');
        if ($limit > 0) {
            $notices = $query->limit($limit)->get();
        } else {
            $notices = $query->get();
        }

        $formatted = $notices->map(fn (Notice $n) => $this->formatNotice($n));

        return response()->json([
            'success' => true,
            'master_enabled' => $masterEnabled,
            'count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Get the single latest / highest-priority active notice.
     *
     * GET /api/notices/latest
     */
    public function latest(): JsonResponse
    {
        $notice = Notice::query()->active()->ordered()->first();

        if (!$notice) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active announcements currently published.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNotice($notice),
        ]);
    }

    /**
     * Display a single notice by ID.
     *
     * GET /api/notices/{id}
     */
    public function show(int $id): JsonResponse
    {
        $notice = Notice::query()->find($id);

        if (!$notice) {
            return response()->json([
                'success' => false,
                'message' => 'Notice announcement not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNotice($notice),
        ]);
    }

    /**
     * Format Notice model for API responses.
     */
    private function formatNotice(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'badgeText' => $notice->badge_text ?: $notice->title,
            'badge_text' => $notice->badge_text ?: $notice->title,
            'message' => $notice->message,
            'link' => $notice->link,
            'type' => $notice->type,
            'durationHours' => $notice->duration_hours,
            'duration_hours' => $notice->duration_hours,
            'startsAt' => $notice->starts_at ? $notice->starts_at->toIso8601String() : null,
            'starts_at' => $notice->starts_at ? $notice->starts_at->toIso8601String() : null,
            'expiresAt' => $notice->expires_at ? $notice->expires_at->toIso8601String() : null,
            'expires_at' => $notice->expires_at ? $notice->expires_at->toIso8601String() : null,
            'priority' => $notice->priority,
            'isActive' => $notice->is_active,
            'is_active' => $notice->is_active,
            'isActiveNow' => $notice->isActiveNow(),
            'is_active_now' => $notice->isActiveNow(),
            'isExpired' => $notice->isExpired(),
            'is_expired' => $notice->isExpired(),
            'remainingTime' => $notice->remaining_time_text,
            'remaining_time' => $notice->remaining_time_text,
            'status' => $notice->status,
            'createdAt' => $notice->created_at ? $notice->created_at->toIso8601String() : null,
            'created_at' => $notice->created_at ? $notice->created_at->toIso8601String() : null,
            'updatedAt' => $notice->updated_at ? $notice->updated_at->toIso8601String() : null,
            'updated_at' => $notice->updated_at ? $notice->updated_at->toIso8601String() : null,
        ];
    }
}
