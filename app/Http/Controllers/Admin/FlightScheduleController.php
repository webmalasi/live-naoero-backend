<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FlightSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FlightScheduleController extends Controller
{
    /**
     * Display a listing of flight schedules.
     */
    public function index(Request $request): View
    {
        $query = FlightSchedule::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('period', 'like', "%{$search}%")
                    ->orWhere('route', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        $schedules = $query->ordered()->paginate(15)->withQueryString();

        $stats = [
            'total' => FlightSchedule::count(),
            'active' => FlightSchedule::where('is_active', true)->count(),
        ];

        return view('admin.schedules.index', compact('schedules', 'stats'));
    }

    /**
     * Show the form for creating a new flight schedule.
     */
    public function create(): View
    {
        $nextOrder = (FlightSchedule::max('sort_order') ?? 0) + 1;

        return view('admin.schedules.create', compact('nextOrder'));
    }

    /**
     * Store a newly created flight schedule in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period' => ['required', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,csv,zip', 'max:20480'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'alert_notice' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('schedules', 'public');
        }

        $schedule = FlightSchedule::create([
            'title' => $validated['title'],
            'period' => $validated['period'],
            'route' => $validated['route'] ?? null,
            'file_path' => $filePath,
            'file_url' => $validated['file_url'] ?? null,
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'alert_notice' => $validated['alert_notice'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'created',
            'flight_schedules',
            "Created flight schedule timetable '{$schedule->title}'",
            ['id' => $schedule->id]
        );

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', "Flight schedule '{$schedule->title}' created successfully.");
    }

    /**
     * Show the form for editing the specified flight schedule.
     */
    public function edit(FlightSchedule $schedule): View
    {
        return view('admin.schedules.edit', compact('schedule'));
    }

    /**
     * Update the specified flight schedule in storage.
     */
    public function update(Request $request, FlightSchedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period' => ['required', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,csv,zip', 'max:20480'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'alert_notice' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $filePath = $schedule->file_path;
        if ($request->hasFile('file')) {
            if ($filePath && ! Str::startsWith($filePath, ['http://', 'https://'])) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $request->file('file')->store('schedules', 'public');
        }

        $schedule->update([
            'title' => $validated['title'],
            'period' => $validated['period'],
            'route' => $validated['route'] ?? null,
            'file_path' => $filePath,
            'file_url' => $validated['file_url'] ?? null,
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'alert_notice' => $validated['alert_notice'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'updated',
            'flight_schedules',
            "Updated flight schedule '{$schedule->title}'",
            ['id' => $schedule->id]
        );

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', "Flight schedule '{$schedule->title}' updated successfully.");
    }

    /**
     * Remove the specified flight schedule from storage.
     */
    public function destroy(FlightSchedule $schedule): RedirectResponse
    {
        $title = $schedule->title;

        if ($schedule->file_path && ! Str::startsWith($schedule->file_path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($schedule->file_path);
        }

        $schedule->delete();

        ActivityLog::log(
            'deleted',
            'flight_schedules',
            "Deleted flight schedule '{$title}'"
        );

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', "Flight schedule '{$title}' has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(FlightSchedule $schedule): JsonResponse|RedirectResponse
    {
        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        ActivityLog::log(
            'updated',
            'flight_schedules',
            "Changed status of '{$schedule->title}' to ".($schedule->is_active ? 'Active' : 'Inactive')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $schedule->is_active,
                'message' => 'Schedule status updated.',
            ]);
        }

        return back()->with('success', "Status of '{$schedule->title}' updated.");
    }
}
