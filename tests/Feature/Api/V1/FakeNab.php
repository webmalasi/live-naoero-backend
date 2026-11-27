<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A stand-in for NAB Gateway: signs its JWTs with a throwaway RSA key
 * (published like /flex/v2/public-keys/{kid}) and answers transaction
 * lookups and searches from what the test sets up.
 */
class FakeNab
{
    private const KID = 'test-kid-1';

    private \OpenSSLAsymmetricKey $key;

    /** @var array<string, array> transaction id => details */
    public array $transactions = [];

    /** Lookups answered 404 this many times first (the gateway's indexing delay). */
    public int $notYetIndexed = 0;

    /** Set to make NAB refuse refunds. */
    public bool $refuseRefunds = false;

    /** @var list<array{payment: string, amount: string, currency: string}> */
    public array $refunds = [];

    /** @var list<array{0: string, 1: string, 2: array|null}> method, path, body */
    public array $calls = [];

    public function __construct()
    {
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function jwt(array $payload): string
    {
        $head = self::b64(json_encode(['alg' => 'RS256', 'kid' => self::KID, 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload + ['exp' => time() + 900]));
        openssl_sign("{$head}.{$body}", $signature, $this->key, OPENSSL_ALGO_SHA256);

        return "{$head}.{$body}.".self::b64($signature);
    }

    /** A successful card sale for an order, as /tss/v2/transactions returns it. */
    public function sale(string $id, string $reference, string $amount, string $currency = 'AUD', array $codes = []): array
    {
        $codes += ['ics_auth' => '100', 'ics_bill' => '100', 'ics_decision' => '100'];
        $t = [
            'id' => $id,
            'applicationInformation' => [
                'reasonCode' => 100,
                'applications' => array_map(fn ($name, $code) => ['name' => $name, 'reasonCode' => $code, 'rCode' => $code === '100' ? '1' : '0'], array_keys($codes), $codes),
            ],
            'clientReferenceInformation' => ['code' => $reference],
            'consumerAuthenticationInformation' => ['eciRaw' => '05'],
            'orderInformation' => ['amountDetails' => ['totalAmount' => $amount, 'currency' => $currency]],
            'paymentInformation' => ['card' => ['suffix' => '1091', 'type' => '001'], 'paymentType' => ['method' => 'VI']],
        ];
        $this->transactions[$id] = $t;

        return $t;
    }

    public function install(): self
    {
        config([
            'naoero.nab.host' => 'apitest.cybersource.com',
            'naoero.nab.merchant_id' => 'test_merchant',
            'naoero.nab.key_id' => 'test-key-id',
            'naoero.nab.shared_secret' => base64_encode('test-secret'),
            'naoero.shop.pay_origin' => 'https://pay.test',
        ]);
        Http::fake(['apitest.cybersource.com/*' => fn (Request $r) => $this->handle($r)]);

        return $this;
    }

    private function handle(Request $request)
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $body = $request->body() === '' ? null : json_decode($request->body(), true);
        $this->calls[] = [$request->method(), $path, $body];
        if (! $request->hasHeader('Signature') || ! $request->hasHeader('v-c-merchant-id')) {
            return Http::response(['message' => 'unsigned'], 401);
        }

        if ($path === '/flex/v2/public-keys/'.self::KID) {
            $d = openssl_pkey_get_details($this->key)['rsa'];

            return Http::response(['kty' => 'RSA', 'use' => 'enc', 'kid' => self::KID, 'n' => self::b64($d['n']), 'e' => self::b64($d['e'])]);
        }
        if ($path === '/up/v1/capture-contexts') {
            return Http::response($this->jwt(['ctx' => [['data' => [
                'clientLibrary' => 'https://testup.cybersource.com/uc/v1/assets/0.31.0/SecureAcceptance.js',
                'clientLibraryIntegrity' => 'sha256-test',
                'targetOrigins' => $body['targetOrigins'] ?? [],
            ]]]]), 201);
        }
        if (str_starts_with($path, '/tss/v2/transactions/')) {
            $id = basename($path);
            if ($this->notYetIndexed > 0) {
                $this->notYetIndexed--;

                return Http::response(['message' => 'not found'], 404);
            }

            return isset($this->transactions[$id]) ? Http::response($this->transactions[$id]) : Http::response(['message' => 'not found'], 404);
        }
        if (preg_match('#^/pts/v2/payments/([A-Za-z0-9]+)/refunds$#', $path, $m)) {
            if ($this->refuseRefunds) {
                return Http::response(['status' => 'INVALID_REQUEST', 'errorInformation' => ['reason' => 'INVALID_DATA', 'message' => 'Refund not allowed']], 400);
            }
            $this->refunds[] = ['payment' => $m[1], 'amount' => $body['orderInformation']['amountDetails']['totalAmount'], 'currency' => $body['orderInformation']['amountDetails']['currency']];

            return Http::response(['id' => '80000000000000000000'.count($this->refunds), 'status' => 'PENDING'], 201);
        }
        if ($path === '/tss/v2/searches') {
            preg_match('/clientReferenceInformation\.code:(\S+)/', $body['query'] ?? '', $m);
            $found = array_values(array_filter($this->transactions, fn ($t) => $t['clientReferenceInformation']['code'] === ($m[1] ?? null)));

            return Http::response(['_embedded' => ['transactionSummaries' => $found]], 201);
        }

        return Http::response(['message' => "unexpected {$path}"], 400);
    }
}
