<?php

namespace App\Services\Payments;

use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * NAB Gateway (NAB-branded Cybersource) REST client. Every call is signed
 * with an HTTP Signature (HMAC-SHA256 over host, date, request target,
 * body digest and merchant id) using the REST shared secret, which never
 * leaves the server. Card data never reaches us: customers type it into
 * NAB's Unified Checkout, which hands back signed JWTs.
 */
class NabGateway
{
    public function __construct(private readonly ServerLog $log) {}

    private static function setting(string $key): string
    {
        $value = trim((string) config("naoero.nab.{$key}"));
        if ($value === '') {
            throw new NabException("NAB {$key} is not configured.");
        }

        return $value;
    }

    public static function configured(): bool
    {
        foreach (['host', 'merchant_id', 'key_id', 'shared_secret'] as $key) {
            if (trim((string) config("naoero.nab.{$key}")) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * A signed request. Returns [status, body], body being the decoded JSON
     * (or the raw text, e.g. a capture-context JWT).
     *
     * @return array{0: int, 1: mixed}
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $host = self::setting('host');
        $merchant = self::setting('merchant_id');
        $body = $payload === null ? null : Js::encode($payload);
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $digest = $body === null ? null : 'SHA-256='.base64_encode(hash('sha256', $body, true));

        $signed = array_filter([
            'host' => $host,
            'v-c-date' => $date,
            'request-target' => strtolower($method).' '.$path,
            'digest' => $digest,
            'v-c-merchant-id' => $merchant,
        ], fn ($v) => $v !== null);
        $signingString = implode("\n", array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($signed), $signed));
        $signature = base64_encode(hash_hmac('sha256', $signingString, base64_decode(self::setting('shared_secret')), true));

        $headers = [
            'v-c-merchant-id' => $merchant,
            'v-c-date' => $date,
            'Signature' => sprintf('keyid="%s", algorithm="HmacSHA256", headers="%s", signature="%s"', self::setting('key_id'), implode(' ', array_keys($signed)), $signature),
        ];
        if ($digest !== null) {
            $headers['Digest'] = $digest;
        }

        $started = hrtime(true);
        try {
            $pending = Http::timeout(40)->withHeaders($headers);
            $response = $body === null
                ? $pending->send($method, "https://{$host}{$path}")
                : $pending->withBody($body, 'application/json;charset=utf-8')->send($method, "https://{$host}{$path}");
        } catch (ConnectionException $e) {
            $this->log->log('error', 'nab', "{$method} {$path} failed", durationMs: (hrtime(true) - $started) / 1e6, detail: ['error' => $e->getMessage()]);
            throw new NabException('Could not reach the payment gateway.');
        }

        $raw = $response->body();
        $decoded = json_decode($raw, true);
        // NAB takes a few seconds to index a new transaction; a 404 on the
        // lookup right after paying is expected and retried, not a problem.
        $notIndexedYet = $method === 'GET' && $response->status() === 404 && str_starts_with($path, '/tss/v2/transactions/');
        $this->log->log(
            $response->successful() || $notIndexedYet ? 'info' : 'warn',
            'nab',
            "{$method} ".preg_replace('/[0-9a-f]{20,}/i', '{id}', $path)." {$response->status()}".($notIndexedYet ? ' (not indexed yet, retrying)' : ''),
            status: $response->status(),
            durationMs: (hrtime(true) - $started) / 1e6,
            detail: $response->successful() ? null : ['response' => is_array($decoded) ? $decoded : mb_substr($raw, 0, 500)],
        );

        return [$response->status(), json_last_error() === JSON_ERROR_NONE ? $decoded : $raw];
    }

    /**
     * A Unified Checkout session for one order: returns the capture-context
     * JWT and the client library (with its integrity hash) it names.
     *
     * @return array{captureContext: string, clientLibrary: string, clientLibraryIntegrity: ?string}
     */
    public function captureContext(array $body): array
    {
        [$status, $jwt] = $this->request('POST', '/up/v1/capture-contexts', $body);
        if ($status < 200 || $status >= 300 || ! is_string($jwt) || substr_count($jwt, '.') !== 2) {
            throw new NabException('The payment gateway did not open a checkout session.');
        }
        $payload = $this->verifyJwt($jwt);
        $data = $payload['ctx'][0]['data'] ?? [];
        if (empty($data['clientLibrary'])) {
            throw new NabException('The checkout session has no client library.');
        }

        return [
            'captureContext' => $jwt,
            'clientLibrary' => $data['clientLibrary'],
            'clientLibraryIntegrity' => $data['clientLibraryIntegrity'] ?? null,
        ];
    }

    /** Transaction details; null while the gateway hasn't indexed it yet (404). */
    public function transaction(string $id): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{6,40}$/', $id)) {
            throw new NabException('Invalid transaction id.');
        }
        [$status, $body] = $this->request('GET', "/tss/v2/transactions/{$id}");
        if ($status === 404) {
            return null;
        }
        if ($status !== 200 || ! is_array($body)) {
            throw new NabException("Transaction lookup failed (HTTP {$status}).");
        }

        return $body;
    }

    /**
     * Refunds (part of) a captured sale. NAB answers PENDING: the refund is
     * accepted and settles with the next batch.
     *
     * @return array{ok: bool, id: ?string, status: ?string, reason: ?string, body: mixed}
     */
    public function refund(string $paymentId, string $reference, float $amount, string $currency): array
    {
        if (! preg_match('/^[A-Za-z0-9]{6,40}$/', $paymentId)) {
            throw new NabException('Invalid transaction id.');
        }
        [$status, $body] = $this->request('POST', "/pts/v2/payments/{$paymentId}/refunds", [
            'clientReferenceInformation' => ['code' => $reference],
            'orderInformation' => ['amountDetails' => ['totalAmount' => number_format($amount, 2, '.', ''), 'currency' => $currency]],
        ]);
        $nabStatus = is_array($body) ? ($body['status'] ?? null) : null;
        $ok = $status >= 200 && $status < 300 && in_array($nabStatus, ['PENDING', 'TRANSMITTED', 'COMPLETED', 'REFUNDED'], true);
        $reason = null;
        if (! $ok) {
            $reason = is_array($body)
                ? trim(($body['errorInformation']['reason'] ?? $body['reason'] ?? '').' '.($body['errorInformation']['message'] ?? $body['message'] ?? ''))
                : '';
            $reason = $reason !== '' ? $reason : "HTTP {$status}";
        }

        return [
            'ok' => $ok,
            'id' => is_array($body) ? ($body['id'] ?? null) : null,
            'status' => $nabStatus,
            'reason' => $reason,
            'body' => $body,
        ];
    }

    /**
     * Transactions recorded against our reference (the order number), newest
     * first; used to recover a payment whose result never came back.
     *
     * @return list<array>
     */
    public function searchByReference(string $reference): array
    {
        [$status, $body] = $this->request('POST', '/tss/v2/searches', [
            'save' => false,
            'name' => 'naoero-order-lookup',
            'timezone' => 'UTC',
            'query' => 'clientReferenceInformation.code:'.$reference.' AND submitTimeUtc:[NOW/DAY-7DAYS TO NOW/DAY+1DAY}',
            'offset' => 0,
            'limit' => 20,
            'sort' => 'id:asc,submitTimeUtc:asc',
        ]);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            throw new NabException("Transaction search failed (HTTP {$status}).");
        }

        return $body['_embedded']['transactionSummaries'] ?? [];
    }

    // ------------------------------------------------------------ JWT

    /**
     * Verifies a gateway JWT (RS256, key from /flex/v2/public-keys/{kid})
     * and returns its payload. Throws when the signature or expiry is bad.
     */
    public function verifyJwt(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new NabException('Not a gateway token.');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::unb64($h), true);
        $payload = json_decode(self::unb64($p), true);
        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new NabException('Unexpected gateway token.');
        }
        $key = $this->publicKey((string) $header['kid']);
        if (openssl_verify("{$h}.{$p}", self::unb64($s), $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new NabException('Gateway token signature is invalid.');
        }
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new NabException('Gateway token has expired.');
        }

        return $payload;
    }

    private function publicKey(string $kid): string
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $kid)) {
            throw new NabException('Invalid key id.');
        }

        return CachedValue::get("nab:public-key:{$kid}", 86400, function () use ($kid) {
            [$status, $jwk] = $this->request('GET', '/flex/v2/public-keys/'.rawurlencode($kid));
            if ($status !== 200 || ! is_array($jwk) || ($jwk['kty'] ?? null) !== 'RSA') {
                throw new NabException("Could not fetch gateway key {$kid}.");
            }

            return self::rsaPem(self::unb64($jwk['n']), self::unb64($jwk['e']));
        });
    }

    private static function unb64(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new NabException('Malformed gateway token.');
        }

        return $decoded;
    }

    /** PEM SubjectPublicKeyInfo for an RSA modulus and exponent. */
    public static function rsaPem(string $modulus, string $exponent): string
    {
        $integer = function (string $bytes): string {
            $bytes = ltrim($bytes, "\0");
            if ($bytes === '' || ord($bytes[0]) > 0x7F) {
                $bytes = "\0".$bytes;
            }

            return "\x02".self::derLength(strlen($bytes)).$bytes;
        };
        $rsaKey = $integer($modulus).$integer($exponent);
        $rsaKey = "\x30".self::derLength(strlen($rsaKey)).$rsaKey;
        $algorithm = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";
        $bitString = "\x03".self::derLength(strlen($rsaKey) + 1)."\0".$rsaKey;
        $spki = "\x30".self::derLength(strlen($algorithm.$bitString)).$algorithm.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
