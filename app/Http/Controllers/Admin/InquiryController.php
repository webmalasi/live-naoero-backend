<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Inquiry::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total' => Inquiry::count(),
            'new' => Inquiry::where('status', 'new')->count(),
            'contacts' => Inquiry::where('type', 'contact')->count(),
            'feedback' => Inquiry::where('type', 'feedback')->count(),
            'group_bookings' => Inquiry::where('type', 'group_booking')->count(),
            'charters' => Inquiry::where('type', 'charter')->count(),
        ];

        return view('admin.inquiries.index', compact('inquiries', 'stats'));
    }

    public function show(Inquiry $inquiry): View
    {
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'in_progress']);
        }

        return view('admin.inquiries.show', compact('inquiry'));
    }

    public function updateStatus(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:new,in_progress,resolved,archived'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $inquiry->update($validated);

        ActivityLog::log('updated', 'inquiries', "Updated inquiry #{$inquiry->id} status to {$inquiry->status}");

        return back()->with('success', 'Inquiry updated.');
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $id = $inquiry->id;
        $inquiry->delete();

        ActivityLog::log('deleted', 'inquiries', "Deleted inquiry #{$id}");

        return redirect()->route('admin.inquiries.index')->with('success', 'Inquiry deleted.');
    }
}
