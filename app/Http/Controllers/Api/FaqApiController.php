<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqApiController extends Controller
{
    /**
     * Get list of active FAQs (optionally filtered by category or grouped).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Faq::query()->active()->ordered();

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        $faqs = $query->get();

        $grouped = $faqs->groupBy('category');

        return response()->json([
            'success' => true,
            'count' => $faqs->count(),
            'data' => $faqs,
            'grouped' => $grouped,
        ]);
    }
}
