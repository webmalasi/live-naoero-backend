<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationApiController extends Controller
{
    /**
     * Display a listing of active destinations (with optional filters).
     * Used by Explore Our Destinations section on Home and Destinations Page.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Destination::query()->active()->ordered();

        // Optional filter for Featured destinations (e.g. for Home Page Explore Our Destinations)
        if ($request->boolean('featured') || $request->query('featured') === '1') {
            $query->featured();
        }

        // Optional search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        $limit = $request->integer('limit');
        if ($limit > 0) {
            $destinations = $query->limit($limit)->get();
        } else {
            $destinations = $query->get();
        }

        // Format to match both frontend camelCase and standard attributes
        $formatted = $destinations->map(function (Destination $dest) {
            return [
                'id' => $dest->id,
                'slug' => $dest->slug,
                'name' => $dest->name,
                'country' => $dest->country,
                'flag' => $dest->flag,
                'image' => $dest->thumbnail_url ?: $dest->thumbnail,
                'thumbnail' => $dest->thumbnail_url ?: $dest->thumbnail,
                'bannerImage' => $dest->banner_image_url ?: $dest->banner_image,
                'banner_image' => $dest->banner_image_url ?: $dest->banner_image,
                'description' => $dest->short_description,
                'short_description' => $dest->short_description,
                'quote' => $dest->quote,
                'is_featured' => $dest->is_featured,
                'sort_order' => $dest->sort_order,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Get featured destinations specifically for Explore Our Destinations section.
     */
    public function featured(Request $request): JsonResponse
    {
        $destinations = Destination::query()
            ->active()
            ->featured()
            ->ordered()
            ->get();

        $formatted = $destinations->map(function (Destination $dest) {
            return [
                'id' => $dest->id,
                'slug' => $dest->slug,
                'name' => $dest->name,
                'country' => $dest->country,
                'flag' => $dest->flag,
                'image' => $dest->thumbnail_url ?: $dest->thumbnail,
                'thumbnail' => $dest->thumbnail_url ?: $dest->thumbnail,
                'banner_image' => $dest->banner_image_url ?: $dest->banner_image,
                'description' => $dest->short_description,
                'short_description' => $dest->short_description,
                'quote' => $dest->quote,
                'is_featured' => $dest->is_featured,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Display the specified destination detail by slug or ID.
     * Used by Destination Detail page (e.g. /destinations/brisbane).
     */
    public function show(string $identifier): JsonResponse
    {
        $destination = Destination::query()
            ->active()
            ->where(function ($query) use ($identifier) {
                if (is_numeric($identifier)) {
                    $query->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $query->where('slug', $identifier);
                }
            })
            ->first();

        if (! $destination) {
            return response()->json([
                'success' => false,
                'message' => 'Destination not found.',
            ], 404);
        }

        // Fetch other destinations for "Discover More Places" carousel
        $otherDestinations = Destination::query()
            ->active()
            ->where('id', '!=', $destination->id)
            ->ordered()
            ->limit(10)
            ->get()
            ->map(function (Destination $dest) {
                return [
                    'id' => $dest->id,
                    'slug' => $dest->slug,
                    'name' => $dest->name,
                    'country' => $dest->country,
                    'flag' => $dest->flag,
                    'thumbnail' => $dest->thumbnail_url ?: $dest->thumbnail,
                    'image' => $dest->thumbnail_url ?: $dest->thumbnail,
                ];
            });

        // Format nested collections to ensure all image URLs are properly resolved
        $whyVisitFormatted = collect($destination->why_visit ?? [])->map(function ($item) {
            if (is_array($item)) {
                if (! empty($item['image'])) {
                    $item['image'] = Destination::formatImageUrl($item['image']);
                    $item['image_url'] = $item['image'];
                }
            }
            return $item;
        })->all();

        $thingsToDoFormatted = collect($destination->things_to_do ?? [])->map(function ($item) {
            if (is_array($item)) {
                if (! empty($item['images']) && is_array($item['images'])) {
                    $item['images'] = array_values(array_filter(array_map(function ($img) {
                        return Destination::formatImageUrl($img);
                    }, $item['images'])));
                }
            }
            return $item;
        })->all();

        $staysFormatted = collect($destination->stays ?? [])->map(function ($item) {
            if (is_array($item)) {
                if (! empty($item['image'])) {
                    $item['image'] = Destination::formatImageUrl($item['image']);
                    $item['image_url'] = $item['image'];
                }
            }
            return $item;
        })->all();

        // Format data aligning with DestinationDetail.jsx expectations
        $detail = [
            'id' => $destination->id,
            'slug' => $destination->slug,
            'name' => $destination->name,
            'country' => $destination->country,
            'flag' => $destination->flag,
            'image' => $destination->banner_image_url ?: $destination->banner_image,
            'banner_image' => $destination->banner_image_url ?: $destination->banner_image,
            'thumbnail' => $destination->thumbnail_url ?: $destination->thumbnail,
            'short_description' => $destination->short_description,
            'quote' => $destination->quote,
            'discoverHeading' => $destination->discover_heading,
            'discover_heading' => $destination->discover_heading,
            'intro' => $destination->intro,
            'banner' => $destination->banner_text,
            'banner_text' => $destination->banner_text,
            'closingParagraph' => $destination->closing_paragraph,
            'closing_paragraph' => $destination->closing_paragraph,
            'whyVisit' => $whyVisitFormatted,
            'why_visit' => $whyVisitFormatted,
            'infoSections' => $destination->info_sections ?? null,
            'info_sections' => $destination->info_sections ?? null,
            'detailedInfo' => $destination->detailed_info ?? null,
            'detailed_info' => $destination->detailed_info ?? null,
            'thingsToDoIntro' => $destination->things_to_do_intro,
            'things_to_do_intro' => $destination->things_to_do_intro,
            'thingsToDo' => $thingsToDoFormatted,
            'things_to_do' => $thingsToDoFormatted,
            'staysIntro' => $destination->stays_intro,
            'stays_intro' => $destination->stays_intro,
            'stays' => $staysFormatted,
            'is_featured' => $destination->is_featured,
            'is_active' => $destination->is_active,
            'otherDestinations' => $otherDestinations,
            'other_destinations' => $otherDestinations,
        ];

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }
}
