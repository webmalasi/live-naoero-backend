<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;

/** A booking made elsewhere: its reference or an e-ticket number, and a passenger's name. */
class FindBookingRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            'pnr' => ['required_without:ticketNumber', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{5,8}$/'],
            'ticketNumber' => ['required_without:pnr', 'nullable', 'string', 'regex:/^\d{13,14}$/'],
            'lastName' => ['required', 'string', 'min:1', 'max:60', "regex:/^[\\pL][\\pL '\\-.]*$/u"],
            'firstName' => ['nullable', 'string', 'max:60', "regex:/^[\\pL][\\pL '\\-.]*$/u"],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'pnr.required_without' => 'Enter your booking reference or ticket number.',
            'ticketNumber.required_without' => 'Enter your booking reference or ticket number.',
            'ticketNumber.regex' => 'A ticket number is 13 digits, for example 1232415191663.',
            'pnr.regex' => 'A booking reference is 5 to 8 letters and numbers, for example 003PV5.',
            'lastName.required' => 'Enter the surname of a passenger on the booking.',
            'lastName.regex' => 'Enter the surname as it appears on the booking.',
            'firstName.regex' => 'Enter the first name as it appears on the booking.',
        ];
    }

    protected function normalize(array $data): array
    {
        foreach (['pnr', 'lastName', 'firstName'] as $key) {
            if (is_string($data[$key] ?? null)) {
                $data[$key] = trim(preg_replace('/\s+/', ' ', $data[$key]));
            }
        }
        // Ticket numbers are often written with spaces or a dash (123-2415191663).
        if (is_string($data['ticketNumber'] ?? null)) {
            $data['ticketNumber'] = preg_replace('/[\s\-]/', '', $data['ticketNumber']);
        }
        foreach (['firstName', 'pnr', 'ticketNumber'] as $key) {
            if (($data[$key] ?? null) === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
