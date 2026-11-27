<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlightSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlightScheduleApiController extends Controller
{
    /**
     * Get list of active flight timetables / schedules.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FlightSchedule::active()->ordered();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('period', 'like', "%{$search}%")
                    ->orWhere('route', 'like', "%{$search}%");
            });
        }

        $schedules = $query->get();

        return response()->json([
            'success' => true,
            'count' => $schedules->count(),
            'data' => $schedules,
        ]);
    }

    /**
     * Get distinct routes or summary information.
     */
    public function routes(): JsonResponse
    {
        $routes = FlightSchedule::active()
            ->whereNotNull('route')
            ->distinct()
            ->pluck('route');

        return response()->json([
            'success' => true,
            'data' => $routes,
        ]);
    }
}
