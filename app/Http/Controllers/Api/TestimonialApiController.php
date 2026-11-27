<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialApiController extends Controller
{
    /**
     * Get list of active customer reviews / testimonials for homepage.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 6);

        $query = Testimonial::query()->active()->ordered();

        if ($request->filled('rating')) {
            $query->where('rating', '>=', $request->integer('rating'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('quote', 'like', "%{$search}%");
            });
        }

        $testimonials = $query->limit($limit)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'location' => $item->location ?? 'Passenger',
                'quote' => $item->quote,
                'image' => $item->image_url ?? $item->image,
                'image_url' => $item->image_url ?? $item->image,
                'rating' => $item->rating,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at,
            ];
        });

        $heading = SiteSetting::where('key', 'testimonials_heading')->value('value') ?? 'Our Grateful Customers';
        $subheading = SiteSetting::where('key', 'testimonials_subheading')->value('value') ?? 'Read real experiences from passengers travelling across our Pacific network.';

        return response()->json([
            'success' => true,
            'heading' => $heading,
            'subheading' => $subheading,
            'count' => $testimonials->count(),
            'data' => $testimonials,
        ]);
    }

    /**
     * Get single testimonial review.
     */
    public function show(int $id): JsonResponse
    {
        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => "Testimonial #{$id} not found.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $testimonial->id,
                'name' => $testimonial->name,
                'location' => $testimonial->location,
                'quote' => $testimonial->quote,
                'image' => $testimonial->image_url ?? $testimonial->image,
                'image_url' => $testimonial->image_url ?? $testimonial->image,
                'rating' => $testimonial->rating,
                'is_active' => $testimonial->is_active,
                'sort_order' => $testimonial->sort_order,
                'created_at' => $testimonial->created_at,
                'updated_at' => $testimonial->updated_at,
            ],
        ]);
    }
}
