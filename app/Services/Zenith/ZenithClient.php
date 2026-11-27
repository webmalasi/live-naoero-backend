<?php

namespace App\Services\Zenith;

use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Low-level client for the Zenith (TTInteractive / CitizenPlane) Modern JSON
 * Sales API. This is the only class that ever sees the authentication key.
 *
 * Every call is a POST to {endpoint}/{operation} with the key injected into
 * RequestInfo. Zenith reports business errors as HTTP 200 with
 * ResponseInfo.Error set; those become a ZenithBusinessException carrying
 * the code and the short message. The error's FullText (a server stack
 * trace) is never surfaced.
 */
class ZenithClient
{
    /** Operations the API is allowed to call. Nothing else is reachable. */
    public const OPERATIONS = [
        'SaleConfiguration', 'EligibleOriginDestinations', 'GetValueCodes', 'SearchFlights',
        'NewCustomer', 'LoadCustomer', 'UpdateCustomer', 'ChangeCustomerPassword', 'LostCustomerPassword',
        'CustomerPasswordPolicies', 'SendCustomerRemovalRequest',
        'PrepareFlights', 'CreateBooking', 'LoadBooking', 'CreateTicket', 'SendTicketConfirmation',
        'PrepareBookingModification', 'ModifyBooking', 'SearchBooking',
        // After booking: flight changes, extra trips, splitting, check-in, flight status.
        'SearchFlightsForExchange', 'PrepareExchange', 'Exchange', 'CancelPendingExchange',
        'SearchFlightsForAdditionalItinerary', 'PrepareAdditionalItinerary', 'AddItinerary',
        'SplitBooking', 'PrepareCheckin', 'Checkin', 'FlightStatus',
        // Cancelling and refunding, vouchers, services sold on their own, undoing a check-in, flown coupons.
        'PrepareCancel', 'Cancel', 'LoadMCOTickets', 'SearchServices', 'BuyServices', 'Uncheck', 'FlownCoupons',
        // Reference and health: the availability calendar, value-code lists, a ping.
        'Calendar', 'GetValueCodeList', 'Ping',
    ];

    public function __construct(private readonly ServerLog $log) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function call(string $operation, array $body = []): array
    {
        $started = hrtime(true);
        try {
            $response = $this->request()->withBody($this->payload($operation, $body), 'application/json')->post($this->url($operation));
        } catch (ConnectionException $e) {
            $this->fail($operation, $started, $this->connectionError($operation, $e));
        }

        return $this->finish($operation, $started, $response);
    }

    /**
     * Several calls at once (flexible-date searches).
     *
     * @param  array<string, array{0: string, 1: array<string, mixed>}>  $calls  key => [operation, body]
     * @return array<string, array<string, mixed>|Throwable>
     */
    public function callMany(array $calls): array
    {
        $payloads = [];
        foreach ($calls as $key => [$operation, $body]) {
            $payloads[$key] = [$operation, $this->payload($operation, $body)];
        }
        $started = hrtime(true);
        $responses = Http::pool(function (Pool $pool) use ($payloads) {
            $requests = [];
            foreach ($payloads as $key => [$operation, $json]) {
                $requests[] = $pool->as((string) $key)
                    ->timeout($this->timeoutSeconds())
                    ->acceptJson()
                    ->withBody($json, 'application/json')
                    ->post($this->url($operation));
            }

            return $requests;
        });

        $results = [];
        foreach ($payloads as $key => [$operation]) {
            $response = $responses[(string) $key] ?? null;
            try {
                if (! $response instanceof Response) {
                    $this->fail($operation, $started, $response instanceof ConnectionException
                        ? $this->connectionError($operation, $response)
                        : new ZenithUnavailableException("Could not reach Zenith ({$operation})"));
                }
                $results[$key] = $this->finish($operation, $started, $response);
            } catch (ZenithBusinessException|ZenithUnavailableException $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds())->acceptJson();
    }

    private function url(string $operation): string
    {
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw new InvalidArgumentException("Zenith operation {$operation} is not allowed.");
        }

        return config('naoero.zenith.endpoint').'/'.$operation;
    }

    private function payload(string $operation, array $body): string
    {
        $key = trim((string) config('naoero.zenith.key'));
        if ($key === '') {
            throw new ZenithUnavailableException('ZENITH_AUTH_KEY is not set.');
        }

        return Js::encode([
            ...$body,
            'RequestInfo' => [
                'AuthenticationKey' => $key,
                'CultureName' => config('naoero.zenith.culture') ?: 'en-GB',
            ],
        ]);
    }

    private function timeoutSeconds(): float
    {
        $ms = (int) config('naoero.zenith.timeout_ms');

        return ($ms > 0 ? $ms : 30000) / 1000;
    }

    private function connectionError(string $operation, ConnectionException $e): ZenithUnavailableException
    {
        $timedOut = str_contains(strtolower($e->getMessage()), 'timed out');

        return new ZenithUnavailableException($timedOut ? "Zenith {$operation} timed out" : "Could not reach Zenith ({$operation})");
    }

    /** @return array<string, mixed> */
    private function finish(string $operation, int $started, Response $response): array
    {
        try {
            $result = $this->parse($operation, $response);
        } catch (ZenithBusinessException|ZenithUnavailableException $e) {
            $this->fail($operation, $started, $e);
        }
        $this->log->log('info', 'zenith', "{$operation} ok", durationMs: $this->elapsed($started));

        return $result;
    }

    private function fail(string $operation, int $started, ZenithBusinessException|ZenithUnavailableException $e): never
    {
        $business = $e instanceof ZenithBusinessException;
        $this->log->log(
            $business ? 'warn' : 'error',
            'zenith',
            "{$operation} ".($business ? 'rejected' : 'failed'),
            durationMs: $this->elapsed($started),
            detail: $business ? ['code' => $e->zenithCode, 'message' => $e->getMessage()] : ['error' => $e->getMessage()],
        );

        throw $e;
    }

    /** @return array<string, mixed> */
    private function parse(string $operation, Response $response): array
    {
        if (! $response->successful()) {
            throw new ZenithUnavailableException("Zenith {$operation} failed with HTTP {$response->status()}", $response->status());
        }
        $body = $response->body();
        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ZenithUnavailableException("Zenith {$operation} returned invalid JSON");
        }
        $json = Js::decode($body);
        if ($json instanceof \ArrayObject) {
            $json = []; // {}: a valid, empty answer
        }
        if (! is_array($json)) {
            throw new ZenithUnavailableException("Zenith {$operation} returned an empty response");
        }

        $error = data_get($json, 'ResponseInfo.Error');
        if (is_array($error)) {
            throw new ZenithBusinessException(
                Js::text($error['Code'] ?? null) ?? 'Unknown',
                Js::text($error['Message'] ?? null) ?? 'The airline system rejected the request.',
                $json,
            );
        }

        return $json;
    }

    private function elapsed(int $started): float
    {
        return (hrtime(true) - $started) / 1e6;
    }
}
