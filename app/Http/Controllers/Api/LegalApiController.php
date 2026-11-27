<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\PageBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalApiController extends Controller
{
    /**
     * Get Privacy Policy Document.
     */
    public function privacyPolicy(Request $request): JsonResponse
    {
        $doc = LegalDocument::findBySlug('privacy-policy');
        $banner = PageBanner::findByPage('privacy-policy');

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'slug' => $doc->slug,
                'title' => $doc->title,
                'heading' => $doc->heading,
                'subheading' => $doc->subheading,
                'badge_text' => $doc->badge_text,
                'banner_image' => $banner ? $banner->banner_image_url : $doc->banner_image_url,
                'contact_email' => $doc->contact_email ?? 'privacy@naoeroairlines.com.au',
                'summary' => $doc->summary,
                'sections' => $doc->sections,
                'content' => $doc->content,
                'last_reviewed_at' => $doc->last_reviewed_at?->format('Y-m-d') ?? '2026-08-18',
                'next_review_at' => $doc->next_review_at?->format('Y-m-d') ?? '2027-01-01',
            ],
        ]);
    }

    /**
     * Get Terms of Use Document.
     */
    public function termsOfUse(Request $request): JsonResponse
    {
        $doc = LegalDocument::findBySlug('terms-of-use');
        $banner = PageBanner::findByPage('terms-of-use');

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => 'Terms of use not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'slug' => $doc->slug,
                'title' => $doc->title,
                'heading' => $doc->heading,
                'subheading' => $doc->subheading,
                'badge_text' => $doc->badge_text,
                'banner_image' => $banner ? $banner->banner_image_url : $doc->banner_image_url,
                'contact_email' => $doc->contact_email ?? 'privacy@naoeroairlines.com.au',
                'summary' => $doc->summary,
                'sections' => $doc->sections,
                'content' => $doc->content,
            ],
        ]);
    }

    /**
     * Get Conditions of Carriage Document.
     */
    public function conditionsOfCarriage(Request $request): JsonResponse
    {
        $doc = LegalDocument::findBySlug('conditions-of-carriage');
        $banner = PageBanner::findByPage('conditions-of-carriage');

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => 'Conditions of carriage not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'slug' => $doc->slug,
                'title' => $doc->title,
                'heading' => $doc->heading,
                'subheading' => $doc->subheading,
                'badge_text' => $doc->badge_text,
                'banner_image' => $banner ? $banner->banner_image_url : $doc->banner_image_url,
                'pdf_url' => $doc->download_pdf_url,
                'contact_email' => $doc->contact_email ?? 'reservations@naoeroairlines.com.au',
                'summary' => $doc->summary,
                'sections' => $doc->sections,
                'content' => $doc->content,
            ],
        ]);
    }

    /**
     * Get any legal document by slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $doc = LegalDocument::findBySlug($slug);

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => "Document '{$slug}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'slug' => $doc->slug,
                'title' => $doc->title,
                'heading' => $doc->heading,
                'subheading' => $doc->subheading,
                'badge_text' => $doc->badge_text,
                'banner_image' => $doc->banner_image_url,
                'pdf_url' => $doc->download_pdf_url,
                'contact_email' => $doc->contact_email,
                'summary' => $doc->summary,
                'sections' => $doc->sections,
                'content' => $doc->content,
            ],
        ]);
    }
}
