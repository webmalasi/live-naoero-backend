<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyContactApiController extends Controller
{
    /**
     * Get all active travel agency contacts grouped or filtered.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AgencyContact::active()->ordered();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        $agencies = $query->get();

        return response()->json([
            'success' => true,
            'count' => $agencies->count(),
            'data' => $agencies,
        ]);
    }
}
