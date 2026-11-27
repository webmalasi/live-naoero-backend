<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryApiController extends Controller
{
    /**
     * Submit a contact inquiry.
     */
    public function submitContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $fullName = $validated['name'] ?? trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        if (empty($fullName)) {
            $fullName = 'Anonymous Visitor';
        }

        $inquiry = Inquiry::create([
            'type' => 'contact',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? 'Website Contact Message',
            'message' => $validated['message'],
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your message has been received. Our team will contact you shortly.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    /**
     * Submit customer feedback.
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'feedbackCategory' => ['nullable', 'string', 'max:100'],
            'classOfTravel' => ['nullable', 'string', 'max:100'],
            'flightNumber' => ['nullable', 'string', 'max:50'],
            'origin' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'onBehalfOfSomeoneElse' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $fullName = $validated['name'] ?? trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        if (empty($fullName)) {
            $fullName = 'Anonymous Passenger';
        }

        $inquiry = Inquiry::create([
            'type' => 'feedback',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => 'Passenger Feedback: '.($validated['feedbackCategory'] ?? 'General'),
            'message' => $validated['message'],
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for sharing your feedback with Naoero Airlines.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    /**
     * Submit group booking inquiry.
     */
    public function submitGroupBooking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contactNumber' => ['nullable', 'string', 'max:50'],
            'phoneNumber' => ['nullable', 'string', 'max:50'],
            'passengerCount' => ['nullable'],
            'groupSize' => ['nullable'],
            'origin' => ['nullable', 'string', 'max:100'],
            'departureLocation' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'arrivalLocation' => ['nullable', 'string', 'max:100'],
            'departureDate' => ['nullable', 'string'],
            'returnDate' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $fullName = $validated['name'] ?? trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        if (empty($fullName)) {
            $fullName = $request->input('companyName') ?? 'Group Organizer';
        }

        $phone = $validated['phone'] ?? $validated['contactNumber'] ?? $validated['phoneNumber'] ?? '';
        $origin = $validated['origin'] ?? $validated['departureLocation'] ?? '';
        $destination = $validated['destination'] ?? $validated['arrivalLocation'] ?? '';
        $message = $validated['notes'] ?? $validated['message'] ?? 'Group booking quote request';

        $inquiry = Inquiry::create([
            'type' => 'group_booking',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $phone,
            'subject' => 'Group Booking Request'.($origin || $destination ? ": {$origin} to {$destination}" : ''),
            'message' => $message,
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group booking request received. Our dedicated team will prepare a customized quote.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    /**
     * Submit charter quote inquiry.
     */
    public function submitCharter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phoneNumber' => ['nullable', 'string', 'max:50'],
            'contactNumber' => ['nullable', 'string', 'max:50'],
            'aircraftType' => ['nullable', 'string', 'max:100'],
            'charterType' => ['nullable', 'string', 'max:100'],
            'origin' => ['nullable', 'string', 'max:100'],
            'departureLocation' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'arrivalLocation' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:255'],
            'departureDate' => ['nullable', 'string'],
            'arrivalDate' => ['nullable', 'string'],
            'dates' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $fullName = trim($validated['name'] ?? '');
        if (empty($fullName)) {
            $fullName = trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        }
        if (empty($fullName)) {
            $fullName = $request->input('companyName') ?? $request->input('company') ?? 'Charter Client';
        }

        $phone = $validated['phone'] ?? $validated['phoneNumber'] ?? $validated['contactNumber'] ?? $request->input('mobileNumber') ?? '';
        $charterType = $validated['charterType'] ?? $validated['aircraftType'] ?? $request->input('charter_type') ?? 'Charter Service';
        $message = $validated['message'] ?? $request->input('notes') ?? $request->input('comments') ?? $request->input('passengerSupportingDetails') ?? $request->input('freightSupportingDetails') ?? 'Private aircraft charter request';

        $inquiry = Inquiry::create([
            'type' => 'charter',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $phone,
            'subject' => "Charter Inquiry: {$charterType}",
            'message' => $message,
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Charter request received. Our flight operations team will review and respond promptly.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    /**
     * Submit ACMI lease inquiry.
     */
    public function submitAcmi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobileNumber' => ['nullable', 'string', 'max:50'],
            'landingPhone' => ['nullable', 'string', 'max:50'],
            'aircraftType' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
            'estimatedHours' => ['nullable', 'string', 'max:100'],
            'operatingBases' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $fullName = $validated['name'] ?? trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        if (empty($fullName)) {
            $fullName = $request->input('companyName') ?? 'ACMI Partner';
        }

        $phone = $validated['phone'] ?? $validated['mobileNumber'] ?? $validated['landingPhone'] ?? '';
        $company = $validated['company'] ?? $validated['companyName'] ?? $fullName;
        $message = $validated['message'] ?? 'ACMI Aircraft wet lease inquiry';

        $inquiry = Inquiry::create([
            'type' => 'acmi',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $phone,
            'subject' => "ACMI Lease Inquiry: {$company}",
            'message' => $message,
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ACMI inquiry received. Our commercial lease team will contact you.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    /**
     * Submit freight / cargo quote inquiry.
     */
    public function submitFreight(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
            'companyName' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phoneNumber' => ['nullable', 'string', 'max:50'],
            'contactNumber' => ['nullable', 'string', 'max:50'],
            'cargoType' => ['nullable', 'string', 'max:100'],
            'origin' => ['nullable', 'string', 'max:100'],
            'departureLocation' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'arrivalLocation' => ['nullable', 'string', 'max:100'],
            'estimatedWeight' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'string', 'max:100'],
            'dimensions' => ['nullable', 'string', 'max:200'],
            'requiredDate' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $fullName = $validated['name'] ?? trim(($validated['firstName'] ?? '').' '.($validated['lastName'] ?? ''));
        if (empty($fullName)) {
            $fullName = $request->input('companyName') ?? 'Cargo Shipper';
        }

        $phone = $validated['phone'] ?? $validated['phoneNumber'] ?? $validated['contactNumber'] ?? '';
        $origin = $validated['origin'] ?? $validated['departureLocation'] ?? '';
        $destination = $validated['destination'] ?? $validated['arrivalLocation'] ?? '';
        $message = $validated['message'] ?? 'Freight shipping rate quote request';

        $inquiry = Inquiry::create([
            'type' => 'freight',
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $phone,
            'subject' => 'Freight / Cargo Quote'.($origin || $destination ? ": {$origin} to {$destination}" : ''),
            'message' => $message,
            'details' => $request->all(),
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Freight quote inquiry received. Our Pacific cargo team will get back to you with rates.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }
}
