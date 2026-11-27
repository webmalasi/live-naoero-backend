<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    /**
     * Display a listing of newsletter subscribers.
     */
    public function index(Request $request): View
    {
        $query = NewsletterSubscriber::query();

        if ($search = $request->input('search')) {
            $query->where('email', 'like', "%{$search}%");
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        $subscribers = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total' => NewsletterSubscriber::count(),
            'active' => NewsletterSubscriber::where('is_active', true)->count(),
            'unsubscribed' => NewsletterSubscriber::where('is_active', false)->count(),
        ];

        return view('admin.newsletter.index', compact('subscribers', 'stats'));
    }

    /**
     * Toggle subscriber status (subscribe / unsubscribe).
     */
    public function toggleStatus(NewsletterSubscriber $subscriber): JsonResponse|RedirectResponse
    {
        $subscriber->is_active = ! $subscriber->is_active;
        $subscriber->save();

        ActivityLog::log(
            'updated',
            'newsletter_subscribers',
            "Changed newsletter status for {$subscriber->email} to ".($subscriber->is_active ? 'Active' : 'Unsubscribed')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $subscriber->is_active,
                'message' => 'Subscriber status updated.',
            ]);
        }

        return back()->with('success', "Status of '{$subscriber->email}' updated.");
    }

    /**
     * Remove the specified subscriber from storage.
     */
    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();

        ActivityLog::log(
            'deleted',
            'newsletter_subscribers',
            "Removed newsletter subscriber '{$email}'"
        );

        return redirect()
            ->route('admin.newsletter.index')
            ->with('success', "Subscriber '{$email}' has been removed.");
    }

    /**
     * Export active subscribers to CSV.
     */
    public function export(): StreamedResponse
    {
        $fileName = 'newsletter_subscribers_'.now()->format('Y-m-d_His').'.csv';

        ActivityLog::log(
            'export',
            'newsletter_subscribers',
            'Exported newsletter subscriber database to CSV'
        );

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Email', 'Source', 'Status', 'Subscribed At', 'Created At']);

            NewsletterSubscriber::orderBy('id', 'desc')->chunk(100, function ($subscribers) use ($handle) {
                foreach ($subscribers as $s) {
                    fputcsv($handle, [
                        $s->id,
                        $s->email,
                        $s->source ?? 'website',
                        $s->is_active ? 'Active' : 'Unsubscribed',
                        $s->subscribed_at ? $s->subscribed_at->format('Y-m-d H:i:s') : '',
                        $s->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
