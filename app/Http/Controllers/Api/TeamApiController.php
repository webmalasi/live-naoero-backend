<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamApiController extends Controller
{
    /**
     * Get list of active team members.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeamMember::query()->active()->ordered();

        if ($dept = $request->query('department')) {
            $query->where('department', $dept);
        }

        $members = $query->get();

        return response()->json([
            'success' => true,
            'count' => $members->count(),
            'data' => $members,
        ]);
    }
}
