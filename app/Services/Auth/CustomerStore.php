<?php

namespace App\Services\Auth;

use App\Services\Support\Js;
use App\Services\Support\Phones;
use ArrayObject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Customers as mirrored from Zenith, and the profile the app receives.
 * Zenith is the system of record; nothing here ever stores a password.
 */
class CustomerStore
{
    private const CHILD_TABLES = ['customer_emails', 'customer_phones', 'customer_addresses', 'customer_documents'];

    private static function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : null;
    }

    private static function zenithDay(?string $value): ?string
    {
        return $value ? "{$value}T00:00:00" : null;
    }

    /**
     * A copy of $value with every password-like field removed, at any depth.
     * Zenith doesn't return passwords; this makes sure one could never be
     * stored if it ever did.
     */
    public static function withoutSecrets(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && preg_match('/pass(word)?|pwd|secret|token/i', $k)) {
                    continue;
                }
                $out[$k] = self::withoutSecrets($v);
            }

            return array_is_list($value) ? array_values($out) : ($out === [] ? new ArrayObject : $out);
        }

        return $value;
    }

    /**
     * Writes the profile Zenith returned: the customer row, its emails /
     * phones / addresses / documents (replaced wholesale), and the raw
     * profile as JSON. Matches on (environment, Zenith ref). Must run inside
     * a transaction. Returns customers.id.
     */
    public function save(string $environment, array $zc, bool $loggedIn = false): string
    {
        $now = Js::dbTime();
        $fields = [
            'zenith_login' => $zc['Login'],
            // The main email (Zenith's first); the login is a username and can differ.
            'email' => self::mainEmail($zc),
            'customer_type' => Js::text($zc['TypeCode'] ?? null),
            'civility_code' => Js::text($zc['CivilityCode'] ?? null),
            'first_name' => Js::text($zc['Firstname'] ?? null),
            'middle_name' => Js::text($zc['Middlename'] ?? null),
            'last_name' => Js::text($zc['Surname'] ?? null),
            'birth_date' => self::day($zc['BirthDate'] ?? null),
            'culture_name' => Js::text($zc['CultureName'] ?? null),
            'currency' => Js::text($zc['Currency'] ?? null),
            'company_name' => Js::text($zc['CompanyName'] ?? null),
            'company_administrative_name' => Js::text($zc['CompanyAdministrativeName'] ?? null),
            'fiscal_document_number' => Js::text($zc['FiscalDocumentNumber'] ?? null),
            'comment' => Js::text($zc['Comment'] ?? null),
            'newsletter_opt_in' => is_bool($zc['HasSubscribedToNewsletter'] ?? null) ? $zc['HasSubscribedToNewsletter'] : null,
            'zenith_profile' => Js::encode(self::withoutSecrets($zc)),
            'profile_synced_at' => $now,
            'updated_at' => $now,
        ];
        if ($loggedIn) {
            $fields['last_login_at'] = $now;
        }

        $existing = DB::table('customers')
            ->where('zenith_environment', $environment)
            ->where('zenith_customer_ref', (string) $zc['Ref'])
            ->lockForUpdate()
            ->value('id');
        if ($existing) {
            $id = $existing;
            DB::table('customers')->where('id', $id)->update($fields);
        } else {
            $id = (string) Str::uuid();
            DB::table('customers')->insert([
                'id' => $id,
                'zenith_environment' => $environment,
                'zenith_customer_ref' => (string) $zc['Ref'],
                'created_at' => $now,
                ...$fields,
            ]);
        }

        foreach (self::CHILD_TABLES as $table) {
            DB::table($table)->where('customer_id', $id)->delete();
        }
        foreach (Js::list($zc['Emails'] ?? null) as $i => $e) {
            if (Js::text($e['Email'] ?? null)) {
                DB::table('customer_emails')->insert(['customer_id' => $id, 'position' => $i, 'zenith_ref' => Js::text($e['Ref'] ?? null), 'email' => trim($e['Email'])]);
            }
        }
        // The country the customer picked for their main number (shared codes: +1, +44...).
        $preferred = DB::table('customers')->where('id', $id)->value('phone_country');
        foreach (Js::list($zc['Phones'] ?? null) as $i => $p) {
            if (Js::text($p['PhoneNumber'] ?? null)) {
                $parsed = Phones::parse($p['PhoneNumber'], $preferred);
                DB::table('customer_phones')->insert([
                    'customer_id' => $id, 'position' => $i, 'zenith_ref' => Js::text($p['Ref'] ?? null),
                    'location_type_code' => Js::text($p['PhoneLocationTypeCode'] ?? null),
                    'phone_type_code' => Js::text($p['PhoneTypeCode'] ?? null),
                    'phone_number' => trim($p['PhoneNumber']),
                    'e164' => $parsed['e164'] ?? null,
                    'country_code' => $parsed['country'] ?? null,
                ]);
            }
        }
        $this->refreshMainPhone($id, $preferred);
        foreach (Js::list($zc['Addresses'] ?? null) as $i => $a) {
            DB::table('customer_addresses')->insert([
                'customer_id' => $id, 'position' => $i, 'zenith_ref' => Js::text($a['Ref'] ?? null),
                'type_code' => Js::text($a['TypeCode'] ?? null), 'street_number' => Js::text($a['StreetNumber'] ?? null),
                'address1' => Js::text($a['Address1'] ?? null), 'address2' => Js::text($a['Address2'] ?? null),
                'zip_code' => Js::text($a['ZipCode'] ?? null), 'city' => Js::text($a['City'] ?? null),
                'state' => Js::text($a['State'] ?? null), 'country_code' => Js::text($a['CountryNameOrCode'] ?? null),
            ]);
        }
        foreach (Js::list($zc['Documents'] ?? null) as $i => $d) {
            DB::table('customer_documents')->insert([
                'customer_id' => $id, 'position' => $i, 'zenith_ref' => Js::text($d['Ref'] ?? null),
                'document_type_code' => Js::text($d['DocumentTypeCode'] ?? null), 'document_number' => Js::text($d['DocumentNumber'] ?? null),
                'issue_country_code' => Js::text($d['IssueCountryCode'] ?? null), 'nationality_country_code' => Js::text($d['NationalityCountryCode'] ?? null),
                'gender' => Js::text($d['Gender'] ?? null), 'birth_date' => self::day($d['DateOfBirth'] ?? null),
                'issuance_date' => self::day($d['DocumentIssuanceDate'] ?? null), 'expiry_date' => self::day($d['DocumentExpiryDate'] ?? null),
                'first_name' => Js::text($d['Firstname'] ?? null), 'last_name' => Js::text($d['Surname'] ?? null),
            ]);
        }

        return $id;
    }

    /** @return object{id: string, zenith_environment: string, zenith_customer_ref: string, zenith_login: string, email: ?string, status: string}|null */
    public function find(string $id): ?object
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return DB::table('customers')->where('id', $id)
            ->select('id', 'zenith_environment', 'zenith_customer_ref', 'zenith_login', 'email', 'status')->first();
    }

    /** Zenith's first email, lower-cased; else the login when it is an email. */
    public static function mainEmail(array $zc): ?string
    {
        foreach (Js::list($zc['Emails'] ?? null) as $e) {
            if ($email = Js::text($e['Email'] ?? null)) {
                return strtolower($email);
            }
        }
        $login = Js::text($zc['Login'] ?? null);

        return $login !== null && filter_var($login, FILTER_VALIDATE_EMAIL) ? strtolower($login) : null;
    }

    /**
     * The accounts that use this email: as their main email, as another
     * of their emails, or as their login. One email can have several.
     *
     * @return list<object{id: string, zenith_environment: string, zenith_customer_ref: string, zenith_login: string, email: ?string, status: string}>
     */
    public function findByEmail(string $environment, string $email): array
    {
        $email = strtolower(trim($email));

        return DB::table('customers as c')->where('c.zenith_environment', $environment)
            ->where(fn ($q) => $q->whereRaw('lower(c.email) = ?', [$email])
                ->orWhereRaw('lower(c.zenith_login) = ?', [$email])
                ->orWhereExists(fn ($e) => $e->from('customer_emails as e')->whereColumn('e.customer_id', 'c.id')->whereRaw('lower(e.email) = ?', [$email])))
            ->orderBy('c.created_at')
            ->get(['c.id', 'c.zenith_environment', 'c.zenith_customer_ref', 'c.zenith_login', 'c.email', 'c.status'])->all();
    }

    /** The sign-up choices: terms accepted now, marketing consent as given. */
    public function saveConsents(string $id, bool $termsAccepted, ?bool $marketing): void
    {
        $now = Js::dbTime();
        $values = ['updated_at' => $now];
        if ($termsAccepted) {
            $values['terms_accepted_at'] = $now;
        }
        if ($marketing !== null) {
            $values += ['marketing_opt_in' => $marketing, 'marketing_opt_in_at' => $now];
        }
        DB::table('customers')->where('id', $id)->update($values);
    }

    /** The local customer with this login (username, case-insensitive), if any. */
    public function findByLogin(string $environment, string $login): ?object
    {
        return DB::table('customers')->where('zenith_environment', $environment)
            ->whereRaw('lower(zenith_login) = lower(?)', [$login])
            ->select('id', 'zenith_environment', 'zenith_customer_ref', 'zenith_login', 'email', 'status')->first();
    }

    /**
     * customers.phone_e164 / phone_country: the main mobile for SMS (the
     * first Mobile number, else the first number). $country is the country
     * the customer picked, kept when the number belongs to it.
     */
    public function refreshMainPhone(string $id, ?string $country = null): void
    {
        $phones = DB::table('customer_phones')->where('customer_id', $id)->orderBy('position')->get(['phone_number', 'phone_type_code']);
        $main = $phones->first(fn ($p) => strcasecmp((string) $p->phone_type_code, 'Mobile') === 0) ?? $phones->first();
        $parsed = $main ? Phones::parse($main->phone_number, $country) : null;
        DB::table('customers')->where('id', $id)->update([
            'phone_e164' => $parsed['e164'] ?? null,
            'phone_country' => $parsed['country'] ?? null,
        ]);
        if ($parsed) {
            DB::table('customer_phones')->where('customer_id', $id)->where('phone_number', $main->phone_number)
                ->update(['country_code' => $parsed['country']]);
        }
    }

    public function saveEmergencyContact(string $id, ?array $contact): void
    {
        DB::table('customers')->where('id', $id)->update([
            'emergency_contact_name' => $contact['name'] ?? null,
            'emergency_contact_phone' => $contact['phone'] ?? null,
            'updated_at' => Js::dbTime(),
        ]);
    }

    /** Our copy of the address's street number and state (Zenith does not keep them). */
    public function saveAddressExtras(string $id, array $address): void
    {
        DB::table('customers')->where('id', $id)->update([
            'address_street_number' => $address['streetNumber'] ?? null,
            'address_state' => $address['state'] ?? null,
            'updated_at' => Js::dbTime(),
        ]);
    }

    /** The profile the app receives. */
    public function profile(string $id): ?array
    {
        $c = DB::table('customers')->where('id', $id)->first();
        if (! $c) {
            return null;
        }
        $phones = DB::table('customer_phones')->where('customer_id', $id)->orderBy('position')->get();
        $emails = DB::table('customer_emails')->where('customer_id', $id)->orderBy('position')->pluck('email');
        $a = DB::table('customer_addresses')->where('customer_id', $id)->orderBy('position')->first();
        $d = DB::table('customer_documents')->where('customer_id', $id)->orderBy('position')->first();
        $avatarAt = DB::table('customer_avatars')->where('customer_id', $id)->value('updated_at');

        return [
            'id' => $c->id,
            // The username the customer signs in with (Zenith's Login; can't change).
            'username' => $c->zenith_login,
            // Their main email; several accounts may share it.
            'email' => $c->email ?? ($emails->first() ?: (filter_var($c->zenith_login, FILTER_VALIDATE_EMAIL) ? $c->zenith_login : null)),
            'title' => $c->civility_code,
            'firstName' => $c->first_name,
            'middleName' => $c->middle_name,
            'lastName' => $c->last_name,
            'fullName' => implode(' ', array_filter([$c->first_name, $c->middle_name, $c->last_name])),
            'birthDate' => self::day($c->birth_date),
            // The main mobile in E.164, with the country picked for it.
            'phone' => $c->phone_e164 ?? $phones->first()?->phone_number,
            'phoneCountry' => $c->phone_country,
            'phones' => $phones->map(fn ($p) => ['number' => $p->e164 ?? $p->phone_number, 'country' => $p->country_code, 'type' => $p->phone_type_code, 'location' => $p->location_type_code])->all(),
            'emails' => $emails->all(),
            // Zenith does not keep the street number and state: our copy when it has none.
            'address' => $a ? [
                'streetNumber' => $a->street_number ?? $c->address_street_number,
                'line1' => $a->address1,
                'line2' => $a->address2,
                'city' => $a->city,
                'state' => $a->state ?? $c->address_state,
                'postcode' => $a->zip_code,
                'country' => $a->country_code,
            ] : null,
            'passport' => $d ? [
                'number' => $d->document_number,
                'documentType' => $d->document_type_code,
                'issuingCountry' => $d->issue_country_code,
                'nationality' => $d->nationality_country_code,
                'issueDate' => self::day($d->issuance_date),
                'expiryDate' => self::day($d->expiry_date),
                'gender' => $d->gender,
            ] : null,
            'currency' => $c->currency,
            'cultureName' => $c->culture_name,
            'emergencyContact' => ['name' => $c->emergency_contact_name, 'phone' => $c->emergency_contact_phone, 'phoneCountry' => Phones::country($c->emergency_contact_phone)],
            'status' => $c->status,
            'termsAcceptedAt' => Js::iso($c->terms_accepted_at ?? null),
            'marketingOptIn' => $c->marketing_opt_in === null ? null : (bool) $c->marketing_opt_in,
            // When the customer asked for their account to be deleted (still waiting for staff); null = no request.
            'deletionRequestedAt' => Js::iso($c->removal_requested_at ?? null),
            'memberSince' => Js::iso($c->created_at),
            'lastLoginAt' => Js::iso($c->last_login_at),
            'profileSyncedAt' => Js::iso($c->profile_synced_at),
            // When the profile photo last changed (GET /api/v1/me/avatar); null = none.
            'avatarUpdatedAt' => Js::iso($avatarAt),
        ];
    }

    // ------------------------------------------------------------ Zenith payloads

    /** Zenith rejects a new customer without a currency ("Missing field Customer.Currency"). */
    public static function newZenithCustomer(array $input, string $currency): array
    {
        return array_filter([
            // The username (older apps send none: their email is the login).
            'Login' => $input['username'] ?? $input['email'],
            'TypeCode' => 'EndCustomer',
            'CivilityCode' => $input['title'],
            'Firstname' => $input['firstName'],
            'Surname' => $input['lastName'],
            'BirthDate' => self::zenithDay($input['birthDate'] ?? null),
            'Emails' => [['Email' => $input['email']]],
            // The mobile (Home / Mobile, as Zenith's own site saves it).
            'Phones' => [['PhoneLocationTypeCode' => 'Home', 'PhoneTypeCode' => 'Mobile', 'PhoneNumber' => $input['phone']]],
            'CultureName' => 'en-GB',
            'Currency' => $currency,
        ], fn ($v) => $v !== null);
    }

    /**
     * Applies $update to the profile Zenith currently holds, returning the
     * payload for UpdateCustomer. Existing item refs are kept so Zenith edits
     * rows in place. Rules learned from the live system:
     * - the login is always sent unchanged;
     * - the phone list is never sent empty (Zenith accepts that and wipes it);
     * - a document's names must equal the customer's names (else DifferentData).
     */
    public static function applyProfileUpdate(array $current, array $update): array
    {
        $next = $current;

        if (! empty($update['title'])) {
            $next['CivilityCode'] = $update['title'];
        }
        if (! empty($update['firstName'])) {
            $next['Firstname'] = $update['firstName'];
        }
        if (array_key_exists('middleName', $update)) {
            $next['Middlename'] = $update['middleName'];
        }
        if (! empty($update['lastName'])) {
            $next['Surname'] = $update['lastName'];
        }
        if (array_key_exists('birthDate', $update)) {
            $next['BirthDate'] = self::zenithDay($update['birthDate']);
        }

        if (! empty($update['phone'])) {
            $phones = Js::list($current['Phones'] ?? null);
            $first = is_array($phones[0] ?? null) ? $phones[0] : ['PhoneLocationTypeCode' => 'Home', 'PhoneTypeCode' => 'Mobile'];
            $phones[0] = [...$first, 'PhoneNumber' => $update['phone']];
            $next['Phones'] = $phones;
        }

        if (! empty($update['address'])) {
            $address = $update['address'];
            $addresses = Js::list($current['Addresses'] ?? null);
            $first = is_array($addresses[0] ?? null) ? $addresses[0] : ['TypeCode' => 'Billing'];
            $addresses[0] = [
                ...$first,
                'TypeCode' => $first['TypeCode'] ?? 'Billing',
                'StreetNumber' => $address['streetNumber'] ?? null,
                'Address1' => $address['line1'],
                'Address2' => $address['line2'] ?? null,
                'City' => $address['city'],
                'State' => $address['state'] ?? null,
                'ZipCode' => $address['postcode'] ?? null,
                'CountryNameOrCode' => $address['country'],
            ];
            $next['Addresses'] = $addresses;
        }

        if (array_key_exists('passport', $update) && $update['passport'] === null) {
            $next['Documents'] = [];
        } elseif (! empty($update['passport'])) {
            $p = $update['passport'];
            $existing = Js::list($current['Documents'] ?? null)[0] ?? [];
            $existing = is_array($existing) ? $existing : [];
            $next['Documents'] = [[
                ...$existing,
                'DocumentTypeCode' => $p['documentType'],
                'DocumentNumber' => $p['number'],
                'IssueCountryCode' => $p['issuingCountry'],
                'NationalityCountryCode' => $p['nationality'],
                'Gender' => $p['gender'] ?? $existing['Gender'] ?? null,
                'DocumentIssuanceDate' => self::zenithDay($p['issueDate'] ?? null),
                'DocumentExpiryDate' => self::zenithDay($p['expiryDate']),
            ]];
        }

        // Keep every document's names in step with the profile's.
        $next['Documents'] = array_map(fn ($d) => [
            ...(is_array($d) ? $d : []),
            'Firstname' => $next['Firstname'] ?? ($d['Firstname'] ?? null),
            'Surname' => $next['Surname'] ?? ($d['Surname'] ?? null),
            'DateOfBirth' => $next['BirthDate'] ?? ($d['DateOfBirth'] ?? null),
        ], Js::list($next['Documents'] ?? null));

        $next['Login'] = $current['Login'] ?? null;
        $next['Ref'] = $current['Ref'] ?? null;
        if (Js::list($next['Phones'] ?? null) === []) {
            $next['Phones'] = $current['Phones'] ?? null;
        }

        return $next;
    }
}
