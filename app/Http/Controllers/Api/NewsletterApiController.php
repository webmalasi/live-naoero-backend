<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterApiController extends Controller
{
    /**
     * Subscribe to newsletter.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid email address.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $source = $request->input('source', 'website_footer');

        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber) {
            if (! $subscriber->is_active) {
                $subscriber->update([
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'source' => $source,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Welcome back! Your subscription has been reactivated.',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'You are already subscribed to our newsletter!',
            ]);
        }

        NewsletterSubscriber::create([
            'email' => $email,
            'source' => $source,
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for subscribing to Naoero Airlines newsletter!',
        ], 201);
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.',
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber) {
            $subscriber->update(['is_active' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'You have been successfully unsubscribed.',
        ]);
    }
}
