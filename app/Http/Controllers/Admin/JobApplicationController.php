<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = JobApplication::with('career');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($careerId = $request->input('career_id')) {
            $query->where('career_id', $careerId);
        }

        $applications = $query->latest()->paginate(15)->withQueryString();

        return view('admin.careers.applications', compact('applications'));
    }

    public function updateStatus(Request $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,reviewed,shortlisted,rejected'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $application->update($validated);

        ActivityLog::log('updated', 'applications', "Updated application status for {$application->full_name} to {$application->status}");

        return back()->with('success', 'Application status updated.');
    }

    public function destroy(JobApplication $application): RedirectResponse
    {
        $name = $application->full_name;
        $application->delete();

        ActivityLog::log('deleted', 'applications', "Deleted application from {$name}");

        return back()->with('success', "Application from {$name} deleted.");
    }
}
