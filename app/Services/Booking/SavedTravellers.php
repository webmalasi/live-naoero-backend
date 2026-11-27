<?php

namespace App\Services\Booking;

use App\Services\Support\Js;
use App\Services\Support\Phones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Passengers a customer asked to keep for later bookings. Values use the
 * same keys as the passenger form, so the app can drop them straight into
 * a form.
 */
class SavedTravellers
{
    private const COLUMNS = [
        'id', 'title', 'first_name', 'last_name', 'gender', 'date_of_birth', 'nationality', 'document_type', 'document_number',
        'document_issue_country', 'document_issue_date', 'document_expiry', 'email', 'phone', 'phone_country', 'last_passenger_type',
    ];

    private static function day(?string $v): ?string
    {
        return $v ? substr($v, 0, 10) : null;
    }

    /** @return array<string, mixed> */
    private static function toTraveller(object $r): array
    {
        $values = [];
        $map = [
            'title' => $r->title, 'firstName' => $r->first_name, 'lastName' => $r->last_name, 'gender' => $r->gender,
            'dateOfBirth' => self::day($r->date_of_birth), 'nationality' => $r->nationality, 'docType' => $r->document_type,
            'docNumber' => $r->document_number, 'docIssueCountry' => $r->document_issue_country,
            'docIssueDate' => self::day($r->document_issue_date), 'docExpiry' => self::day($r->document_expiry),
            'email' => $r->email, 'mobilePhone' => $r->phone, 'mobilePhoneCountry' => $r->phone_country,
        ];
        foreach ($map as $k => $v) {
            if ($v) {
                $values[$k] = $v;
            }
        }

        return [
            'id' => $r->id,
            'firstName' => $r->first_name,
            'lastName' => $r->last_name,
            'dateOfBirth' => self::day($r->date_of_birth),
            'lastPassengerType' => $r->last_passenger_type,
            'values' => Js::map($values),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function list(string $customerId): array
    {
        return DB::table('saved_travellers')->where('customer_id', $customerId)
            ->orderByDesc('last_used_at')->limit(50)->get(self::COLUMNS)
            ->map(self::toTraveller(...))->all();
    }

    public function delete(string $customerId, string $id): bool
    {
        return DB::table('saved_travellers')->where('id', $id)->where('customer_id', $customerId)->delete() > 0;
    }

    /** Saves (or updates the same person's) details from validated form values. */
    public function save(string $customerId, string $passengerType, array $values): void
    {
        $v = fn (string $k) => Js::text($values[$k] ?? null);
        $first = $v('firstName');
        $last = $v('lastName');
        if (! $first || ! $last) {
            return;
        }
        $gender = $v('gender') ?? BookingForm::genderForTitle($v('title'));
        $dob = $v('dateOfBirth');
        $now = Js::dbTime();
        $details = [
            'title' => $v('title'),
            'gender' => in_array($gender, ['M', 'F'], true) ? $gender : null,
            'nationality' => $v('nationality'),
            'document_type' => $v('docType'),
            'document_number' => $v('docNumber'),
            'document_issue_country' => $v('docIssueCountry'),
            'document_issue_date' => $v('docIssueDate'),
            'document_expiry' => $v('docExpiry'),
            'email' => $v('email'),
            // E.164, and its country (from the picker when given).
            'phone' => ($phone = Phones::parse($v('mobilePhone'), $v('mobilePhoneCountry')))['e164'] ?? $v('mobilePhone'),
            'phone_country' => $phone['country'] ?? null,
        ];

        // One entry per person: the same name and birth date updates it.
        $existing = DB::table('saved_travellers')
            ->where('customer_id', $customerId)
            ->whereRaw('upper(first_name) = upper(?)', [$first])
            ->whereRaw('upper(last_name) = upper(?)', [$last])
            ->when($dob, fn ($q) => $q->where('date_of_birth', $dob), fn ($q) => $q->whereNull('date_of_birth'))
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $update = ['last_passenger_type' => $passengerType, 'last_used_at' => $now, 'updated_at' => $now];
            foreach ($details as $column => $value) {
                if ($value !== null) {
                    $update[$column] = $value;
                }
            }
            DB::table('saved_travellers')->where('id', $existing->id)->update($update);

            return;
        }

        DB::table('saved_travellers')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'first_name' => $first,
            'last_name' => $last,
            'date_of_birth' => $dob,
            ...$details,
            'last_passenger_type' => $passengerType,
            'last_used_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Which passenger slots of this booking each saved traveller can fill: by
     * age on the travel dates when the birth date is known, otherwise by the
     * type they were last booked as.
     */
    public static function eligibility(array $travellers, array $form, array $ref): array
    {
        return array_map(function (array $t) use ($form, $ref) {
            $eligible = [];
            foreach ($form['passengers'] as $p) {
                if ($t['dateOfBirth']) {
                    $limits = BookingForm::ageLimits($p['type'], $ref);
                    if ($limits['min'] !== null && BookingForm::ageOn($t['dateOfBirth'], $form['travel']['firstDate']) < $limits['min']) {
                        continue;
                    }
                    if ($limits['max'] !== null && BookingForm::ageOn($t['dateOfBirth'], $form['travel']['lastDate']) >= $limits['max']) {
                        continue;
                    }
                    $eligible[] = $p['ref'];
                } elseif (! $t['lastPassengerType'] || $t['lastPassengerType'] === $p['type']) {
                    $eligible[] = $p['ref'];
                }
            }

            return [...$t, 'eligibleFor' => $eligible];
        }, $travellers);
    }
}
