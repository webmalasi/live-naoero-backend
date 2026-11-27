<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account deletion. Nothing is ever deleted, here or in Zenith: the
 * customer asks (with a reason, after typing their password), the request
 * waits for staff, and staff either disable the account, which keeps it and
 * all its data but stops it signing in anywhere until re-enabled, or decline
 * the request with a note the customer sees. While a request waits, the
 * customer can still sign in and can withdraw it.
 */
class AccountDeletion
{
    /** Reason => what the customer reads. "other" needs their own words. */
    public const REASONS = [
        'not_flying' => 'I no longer fly with Naoero Airlines',
        'other_account' => 'I have another account',
        'privacy' => 'Privacy concerns about my data',
        'too_many_messages' => 'I get too many emails or notifications',
        'hard_to_use' => 'The website or app is hard to use',
        'bad_experience' => 'I had a bad experience',
        'other' => 'Something else',
    ];

    public const STATUSES = [
        'pending' => 'Waiting for staff',
        'completed' => 'Account disabled',
        'declined' => 'Declined',
        'withdrawn' => 'Withdrawn by the customer',
    ];

    public function __construct(
        private readonly CustomerSessions $sessions,
        private readonly CustomerNotices $notices,
    ) {}

    /** @return list<array{code: string, label: string, needsDetails: bool}> */
    public static function reasons(): array
    {
        return array_map(fn (string $code, string $label) => ['code' => $code, 'label' => $label, 'needsDetails' => $code === 'other'], array_keys(self::REASONS), self::REASONS);
    }

    public function pending(string $customerId): ?object
    {
        return DB::table('account_deletion_requests')->where('customer_id', $customerId)->where('status', 'pending')->first();
    }

    public function latest(string $customerId): ?object
    {
        return DB::table('account_deletion_requests')->where('customer_id', $customerId)->orderByDesc('requested_at')->first();
    }

    /**
     * A new request, once the password has been checked. Asking again while
     * one waits returns that one ($created false).
     *
     * @return array{request: object, created: bool}
     */
    public function request(object $customer, string $reasonCode, ?string $reasonText, ?string $platform, ?string $ip, ?string $userAgent): array
    {
        if (! isset(self::REASONS[$reasonCode])) {
            throw ApiException::invalid('reasonCode must be one of: '.implode(', ', array_keys(self::REASONS)).'.');
        }
        if ($reasonCode === 'other' && $reasonText === null) {
            throw ApiException::invalid('Please tell us why you want your account deleted.');
        }
        if ($existing = $this->pending($customer->id)) {
            return ['request' => $existing, 'created' => false];
        }

        $id = (string) Str::uuid();
        $now = Js::dbTime();
        try {
            DB::transaction(function () use ($id, $now, $customer, $reasonCode, $reasonText, $platform, $ip, $userAgent) {
                DB::table('account_deletion_requests')->insert([
                    'id' => $id,
                    'customer_id' => $customer->id,
                    'reason_code' => $reasonCode,
                    'reason_text' => $reasonText === null ? null : mb_substr($reasonText, 0, 1000),
                    'platform' => in_array($platform, ['website', 'app'], true) ? $platform : null,
                    'ip' => $ip === null ? null : mb_substr($ip, 0, 64),
                    'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
                    'status' => 'pending',
                    'requested_at' => $now,
                ]);
                DB::table('customers')->where('id', $customer->id)->update(['removal_requested_at' => $now, 'updated_at' => $now]);
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests at once: the first one stands.
            return ['request' => $this->pending($customer->id), 'created' => false];
        }

        $this->notices->send($customer->id, 'account_deletion_requested', 'We received your request to delete your account',
            'Our team will review it and disable your account. Until then you can still sign in, and you can withdraw the request from your account page.',
            ['requestId' => $id], dedupeKey: "account-deletion:{$id}:requested");

        return ['request' => DB::table('account_deletion_requests')->where('id', $id)->first(), 'created' => true];
    }

    /** The customer changed their mind. */
    public function withdraw(string $customerId): ?object
    {
        $pending = $this->pending($customerId);
        if (! $pending) {
            return null;
        }
        DB::transaction(function () use ($pending, $customerId) {
            DB::table('account_deletion_requests')->where('id', $pending->id)->where('status', 'pending')
                ->update(['status' => 'withdrawn', 'resolved_at' => Js::dbTime()]);
            DB::table('customers')->where('id', $customerId)->update(['removal_requested_at' => null, 'updated_at' => Js::dbTime()]);
        });

        return DB::table('account_deletion_requests')->where('id', $pending->id)->first();
    }

    /**
     * Staff complete the request: the account is disabled (never deleted)
     * and signed out of every device.
     */
    public function complete(string $requestId, int $staffId, ?string $note): object
    {
        $r = $this->open($requestId);
        DB::transaction(function () use ($r, $staffId, $note) {
            DB::table('account_deletion_requests')->where('id', $r->id)->update([
                'status' => 'completed', 'resolved_at' => Js::dbTime(), 'resolved_by' => $staffId, 'staff_note' => $note,
            ]);
            $this->disable($r->customer_id, $staffId, 'The customer asked for their account to be deleted ('.self::REASONS[$r->reason_code].').');
        });

        return DB::table('account_deletion_requests')->where('id', $r->id)->first();
    }

    /** Staff decline the request; the customer is told why. */
    public function decline(string $requestId, int $staffId, string $note): object
    {
        $r = $this->open($requestId);
        DB::transaction(function () use ($r, $staffId, $note) {
            DB::table('account_deletion_requests')->where('id', $r->id)->update([
                'status' => 'declined', 'resolved_at' => Js::dbTime(), 'resolved_by' => $staffId, 'staff_note' => $note,
            ]);
            DB::table('customers')->where('id', $r->customer_id)->update(['removal_requested_at' => null, 'updated_at' => Js::dbTime()]);
        });
        $this->notices->send($r->customer_id, 'account_deletion_declined', 'Your account deletion request was declined',
            "We haven't disabled your account. {$note}", ['requestId' => $r->id], dedupeKey: "account-deletion:{$r->id}:declined");

        return DB::table('account_deletion_requests')->where('id', $r->id)->first();
    }

    /** Stops the account signing in anywhere: status disabled, every session ended. Its data stays. */
    public function disable(string $customerId, int $staffId, string $reason): int
    {
        return DB::transaction(function () use ($customerId, $staffId, $reason) {
            DB::table('customers')->where('id', $customerId)->update([
                'status' => 'disabled', 'disabled_at' => Js::dbTime(), 'disabled_reason' => mb_substr($reason, 0, 500),
                'disabled_by' => $staffId, 'updated_at' => Js::dbTime(),
            ]);

            return $this->sessions->revokeOthers($customerId, null, 'disabled_by_staff');
        });
    }

    /** Lets the account sign in again. Its old sessions stay ended. */
    public function enable(string $customerId): void
    {
        DB::table('customers')->where('id', $customerId)->update([
            'status' => 'active', 'disabled_at' => null, 'disabled_reason' => null, 'disabled_by' => null,
            'removal_requested_at' => null, 'updated_at' => Js::dbTime(),
        ]);
    }

    private function open(string $requestId): object
    {
        $r = DB::table('account_deletion_requests')->where('id', $requestId)->first();
        if (! $r || $r->status !== 'pending') {
            throw new \RuntimeException('This request is no longer waiting: it has already been handled or withdrawn.');
        }

        return $r;
    }

    /** How the customer sees a request. */
    public static function present(?object $r): ?array
    {
        if (! $r) {
            return null;
        }

        return [
            'id' => $r->id,
            'status' => $r->status,
            'reasonCode' => $r->reason_code,
            'reason' => self::REASONS[$r->reason_code] ?? $r->reason_code,
            'details' => $r->reason_text,
            'requestedAt' => Js::iso($r->requested_at),
            'resolvedAt' => Js::iso($r->resolved_at),
            // Only a declined request's note is for the customer.
            'note' => $r->status === 'declined' ? $r->staff_note : null,
        ];
    }
}
