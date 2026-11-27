<?php

namespace App\Services\Reference;

use App\Exceptions\ApiException;
use App\Services\Flights\FlightConfigService;
use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithClient;
use RuntimeException;
use Throwable;

/**
 * The airline's reference lists (titles, countries, document types,
 * genders), straight from Zenith's value codes, so nothing is hardcoded.
 */
class ReferenceService
{
    // Civilities that describe a child passenger rather than an account holder.
    private const PASSENGER_ONLY = ['CHD', 'INF'];

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly FlightConfigService $flights,
    ) {}

    /** @return list<array{code: string, label: string}> */
    private function codes(string $name, int $ttl, ?callable $keep = null): array
    {
        return CachedValue::get("reference:{$name}", $ttl, function () use ($name, $keep) {
            $res = $this->zenith->call('GetValueCodes', ['ValueCodeName' => $name]);
            $list = [];
            foreach (Js::list($res['Codes'] ?? null) as $c) {
                if (empty($c['Code']) || ($keep && ! $keep($c['Code']))) {
                    continue;
                }
                $label = trim((string) ($c['Label'] ?? $c['Code']));
                $list[] = ['code' => $c['Code'], 'label' => preg_replace('/\.$/', '', $label)];
            }
            if ($list === []) {
                throw new RuntimeException("Zenith returned no {$name} codes");
            }

            return $list;
        });
    }

    /** Titles an account holder can choose (Mr, Mrs, Ms, Miss...). */
    public function titles(): array
    {
        return $this->codes('Civility', 6 * 3600, fn ($code) => ! in_array(strtoupper($code), self::PASSENGER_ONLY, true));
    }

    /** Travel document types (Passport, ID card...). */
    public function documentTypes(): array
    {
        return $this->codes('DocumentType', 86400);
    }

    /** Genders as Zenith codes them (M / F). */
    public function genders(): array
    {
        return $this->codes('Gender', 86400);
    }

    /** The airline's country list (ISO 3166 alpha-2), sorted by name. */
    public function countries(): array
    {
        return CachedValue::get('reference:Country', 86400, function () {
            $res = $this->zenith->call('GetValueCodes', ['ValueCodeName' => 'Country']);
            $list = [];
            foreach (Js::list($res['Codes'] ?? null) as $c) {
                if (is_string($c['Code'] ?? null) && preg_match('/^[A-Z]{2}$/', $c['Code'])) {
                    $list[] = ['code' => $c['Code'], 'name' => Js::text($c['Label'] ?? null) ?? $c['Code']];
                }
            }
            usort($list, fn ($a, $b) => Js::compare($a['name'], $b['name']));

            return $list;
        });
    }

    /** Throws a user-facing error unless $code is one of the airline's titles. */
    public function assertValidTitle(string $code): void
    {
        try {
            $titles = $this->titles();
        } catch (Throwable) {
            throw ApiException::unavailable();
        }
        if (! in_array($code, array_column($titles, 'code'), true)) {
            throw ApiException::invalid('Choose a valid title.');
        }
    }

    /** Whether the airline lets customers request account deletion. */
    public function removalRequestsAllowed(): bool
    {
        try {
            return (bool) ($this->flights->saleConfiguration()['AllowCustomerRemovalRequest'] ?? false);
        } catch (Throwable) {
            return false;
        }
    }
}
