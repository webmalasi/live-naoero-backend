<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerApiController extends Controller
{
    /**
     * Get list of active job openings.
     */
    public function index(Request $request): JsonResponse
    {
        $careers = Career::query()->active()->ordered()->get();

        return response()->json([
            'success' => true,
            'count' => $careers->count(),
            'data' => $careers,
        ]);
    }

    /**
     * Get single career job posting by slug or ID.
     */
    public function show(string $identifier): JsonResponse
    {
        $career = Career::query()
            ->active()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (! $career) {
            return response()->json([
                'success' => false,
                'message' => 'Job opening not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $career,
        ]);
    }

    /**
     * Submit an application for a career position.
     */
    public function apply(Request $request, int $id): JsonResponse
    {
        $career = Career::findOrFail($id);

        if ($request->has('full_name') && ! $request->has('first_name')) {
            $parts = explode(' ', (string) $request->input('full_name'), 2);
            $request->merge([
                'first_name' => $parts[0] ?? 'Applicant',
                'last_name' => $parts[1] ?? ($parts[0] ?? 'Applicant'),
            ]);
        } elseif ($request->has('name') && ! $request->has('first_name')) {
            $parts = explode(' ', (string) $request->input('name'), 2);
            $request->merge([
                'first_name' => $parts[0] ?? 'Applicant',
                'last_name' => $parts[1] ?? ($parts[0] ?? 'Applicant'),
            ]);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('applications/resumes', 'public');
        }

        $application = JobApplication::create([
            'career_id' => $career->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? $validated['first_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'cover_letter' => $validated['cover_letter'] ?? null,
            'resume_path' => $resumePath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your application has been received successfully.',
            'application_id' => $application->id,
        ], 201);
    }
}
