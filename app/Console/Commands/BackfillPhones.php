<?php

namespace App\Console\Commands;

use App\Services\Auth\CustomerStore;
use App\Services\Booking\BookingService;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts the phone numbers already stored into E.164 with their country
 * (customers, their phones, saved travellers, bookings, shop orders and
 * refund requests). Numbers that are not valid are left as they are and
 * counted. Safe to run again.
 */
class BackfillPhones extends Command
{
    protected $signature = 'phones:backfill {--dry-run : Count only, change nothing}';

    protected $description = 'Store every saved phone number in E.164 with its country';

    public function handle(CustomerStore $customers): int
    {
        $dry = (bool) $this->option('dry-run');
        $report = [];
        $count = function (string $what, ?array $parsed) use (&$report): void {
            $report[$what][$parsed ? 'valid' : 'not valid'] = ($report[$what][$parsed ? 'valid' : 'not valid'] ?? 0) + 1;
        };

        foreach (DB::table('customer_phones')->get(['id', 'phone_number']) as $p) {
            $parsed = Phones::parse($p->phone_number);
            $count('customer phones', $parsed);
            if ($parsed && ! $dry) {
                DB::table('customer_phones')->where('id', $p->id)->update(['e164' => $parsed['e164'], 'country_code' => $parsed['country']]);
            }
        }
        foreach (DB::table('customers')->get(['id', 'phone_country', 'emergency_contact_phone']) as $c) {
            if (! $dry) {
                $customers->refreshMainPhone($c->id, $c->phone_country);
            }
            if ($c->emergency_contact_phone) {
                $parsed = Phones::parse($c->emergency_contact_phone);
                $count('emergency contacts', $parsed);
                if ($parsed && ! $dry) {
                    DB::table('customers')->where('id', $c->id)->update(['emergency_contact_phone' => $parsed['e164']]);
                }
            }
        }
        foreach (DB::table('saved_travellers')->whereNotNull('phone')->get(['id', 'phone', 'phone_country']) as $t) {
            $parsed = Phones::parse($t->phone, $t->phone_country);
            $count('saved travellers', $parsed);
            if ($parsed && ! $dry) {
                DB::table('saved_travellers')->where('id', $t->id)->update(['phone' => $parsed['e164'], 'phone_country' => $parsed['country']]);
            }
        }
        foreach (DB::table('bookings')->get(['id', 'zenith_booking']) as $b) {
            $booking = Js::decode($b->zenith_booking);
            $contact = is_array($booking) ? BookingService::contactPhone($booking) : ['contact_phone' => null, 'contact_phone_country' => null];
            $count('bookings', $contact['contact_phone'] ? $contact : null);
            if (! $dry) {
                DB::table('bookings')->where('id', $b->id)->update($contact);
            }
        }
        foreach (DB::table('shop_orders')->whereNotNull('shipping_address')->get(['id', 'shipping_address']) as $o) {
            $address = Js::decode($o->shipping_address);
            $parsed = is_array($address) ? Phones::parse($address['phone'] ?? null, $address['phoneCountry'] ?? $address['country'] ?? null) : null;
            $count('shop orders', $parsed);
            if ($parsed && ! $dry) {
                DB::table('shop_orders')->where('id', $o->id)->update(['contact_phone' => $parsed['e164'], 'contact_phone_country' => $parsed['country']]);
            }
        }
        foreach (DB::table('refund_requests')->whereNotNull('contact_phone')->get(['id', 'contact_phone', 'contact_phone_country']) as $r) {
            $parsed = Phones::parse($r->contact_phone, $r->contact_phone_country);
            $count('refund requests', $parsed);
            if ($parsed && ! $dry) {
                DB::table('refund_requests')->where('id', $r->id)->update(['contact_phone' => $parsed['e164'], 'contact_phone_country' => $parsed['country']]);
            }
        }

        $this->table(['Where', 'Valid', 'Not valid (left as is)'], array_map(
            fn ($what, $n) => [$what, $n['valid'] ?? 0, $n['not valid'] ?? 0], array_keys($report), $report));
        $this->info($dry ? 'Dry run: nothing changed.' : 'Done.');

        return self::SUCCESS;
    }
}
