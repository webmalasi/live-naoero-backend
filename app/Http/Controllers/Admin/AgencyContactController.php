<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgencyContact;
use App\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyContactController extends Controller
{
    /**
     * Display a listing of agency contacts.
     */
    public function index(Request $request): View
    {
        $query = AgencyContact::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        $agencies = $query->ordered()->paginate(15)->withQueryString();

        $stats = [
            'total' => AgencyContact::count(),
            'active' => AgencyContact::where('is_active', true)->count(),
            'countries' => AgencyContact::distinct('country')->count('country'),
        ];

        return view('admin.agencies.index', compact('agencies', 'stats'));
    }

    /**
     * Show the form for creating a new agency contact.
     */
    public function create(): View
    {
        $nextOrder = (AgencyContact::max('sort_order') ?? 0) + 1;
        $countries = DropdownOption::getValues('agency_countries');

        return view('admin.agencies.create', compact('nextOrder', 'countries'));
    }

    /**
     * Store a newly created agency contact.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phones' => ['nullable', 'string'],
            'emails' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $phones = ! empty($validated['phones'])
            ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['phones'])))
            : [];

        $emails = ! empty($validated['emails'])
            ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['emails'])))
            : [];

        $agency = AgencyContact::create([
            'country' => $validated['country'],
            'flag' => $validated['flag'] ?? null,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'phones' => array_values($phones),
            'emails' => array_values($emails),
            'website' => $validated['website'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'created',
            'agency_contacts',
            "Created agency contact '{$agency->name}' ({$agency->country})",
            ['id' => $agency->id]
        );

        return redirect()
            ->route('admin.agencies.index')
            ->with('success', "Agency '{$agency->name}' created successfully.");
    }

    /**
     * Show the form for editing an agency contact.
     */
    public function edit(AgencyContact $agency): View
    {
        $countries = DropdownOption::getValues('agency_countries');
        return view('admin.agencies.edit', compact('agency', 'countries'));
    }

    /**
     * Update the specified agency contact.
     */
    public function update(Request $request, AgencyContact $agency): RedirectResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phones' => ['nullable', 'string'],
            'emails' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $phones = ! empty($validated['phones'])
            ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['phones'])))
            : [];

        $emails = ! empty($validated['emails'])
            ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['emails'])))
            : [];

        $agency->update([
            'country' => $validated['country'],
            'flag' => $validated['flag'] ?? null,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'phones' => array_values($phones),
            'emails' => array_values($emails),
            'website' => $validated['website'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'updated',
            'agency_contacts',
            "Updated agency contact '{$agency->name}'",
            ['id' => $agency->id]
        );

        return redirect()
            ->route('admin.agencies.index')
            ->with('success', "Agency '{$agency->name}' updated successfully.");
    }

    /**
     * Remove the specified agency contact.
     */
    public function destroy(AgencyContact $agency): RedirectResponse
    {
        $name = $agency->name;
        $agency->delete();

        ActivityLog::log(
            'deleted',
            'agency_contacts',
            "Deleted agency contact '{$name}'"
        );

        return redirect()
            ->route('admin.agencies.index')
            ->with('success', "Agency '{$name}' has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(AgencyContact $agency): JsonResponse|RedirectResponse
    {
        $agency->is_active = ! $agency->is_active;
        $agency->save();

        ActivityLog::log(
            'updated',
            'agency_contacts',
            "Changed status of '{$agency->name}' to ".($agency->is_active ? 'Active' : 'Inactive')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $agency->is_active,
                'message' => 'Agency status updated.',
            ]);
        }

        return back()->with('success', "Status of '{$agency->name}' updated.");
    }
}
