<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialOfferApiController extends Controller
{
    /**
     * Get list of active special flight offers / promotions.
     */
    public function index(Request $request): JsonResponse
    {
        $offers = SpecialOffer::query()->active()->ordered()->get();

        return response()->json([
            'success' => true,
            'count' => $offers->count(),
            'data' => $offers,
        ]);
    }

    /**
     * Get single special offer detail.
     */
    public function show(string $identifier): JsonResponse
    {
        $offer = SpecialOffer::query()
            ->active()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (! $offer) {
            return response()->json([
                'success' => false,
                'message' => 'Special offer not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $offer,
        ]);
    }
}
