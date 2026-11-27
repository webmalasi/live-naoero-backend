<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Services\Flights\FlightConfigService;
use App\Services\Logging\ServerLog;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Throwable;

/**
 * Customer accounts in Zenith (the system of record, and the only place a
 * password exists). Zenith reports several customer errors with no code,
 * only a message, so those are recognised by text (as seen on preproduction).
 */
class ZenithCustomers
{
    private const LOCKED_OUT = '/attempt limit/i';

    private const LOGIN_EXISTS = '/login already exists/i';

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly FlightConfigService $flights,
        private readonly ServerLog $log,
    ) {}

    private static function valid(mixed $customer): ?array
    {
        return is_array($customer) && ! empty($customer['Ref']) && ! empty($customer['Login']) ? $customer : null;
    }

    private function mapCommonErrors(Throwable $e): never
    {
        if ($e instanceof ZenithBusinessException) {
            if (preg_match(self::LOCKED_OUT, $e->getMessage())) {
                throw ApiException::tooManyAttempts();
            }
            $this->log->error('zenith-customer', 'business error', ['code' => $e->zenithCode, 'message' => $e->getMessage()]);
            throw ApiException::unavailable();
        }
        if ($e instanceof ZenithUnavailableException) {
            throw ApiException::unavailable();
        }
        throw $e;
    }

    /** Zenith's environment ("Preprod" / "Production"); customer refs are per environment. */
    public function environment(): string
    {
        try {
            return ($this->flights->saleConfiguration()['EnvironmentCode'] ?? null) ?: 'Unknown';
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }
    }

    /**
     * NewCustomer. Zenith refuses a login that exists ("This login already
     * exists"): with a chosen username that is username_taken; when the
     * login is the email (older apps), email_taken.
     */
    public function create(array $customer, string $password, bool $chosenUsername = false): array
    {
        try {
            $res = $this->zenith->call('NewCustomer', ['Customer' => $customer, 'Password' => $password]);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException && preg_match(self::LOGIN_EXISTS, $e->getMessage())) {
                throw $chosenUsername ? ApiException::usernameTaken() : ApiException::emailTaken();
            }
            $this->mapCommonErrors($e);
        }

        return self::valid($res['Customer'] ?? null) ?? throw ApiException::unavailable();
    }

    /**
     * Verifies a login and password. Zenith answers a wrong password or an
     * unknown login identically (no error, no customer).
     */
    public function authenticate(string $login, string $password): array
    {
        try {
            $res = $this->zenith->call('LoadCustomer', ['ByLoginAndPassword' => ['Login' => $login, 'Password' => $password]]);
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }

        return self::valid($res['Customer'] ?? null) ?? throw ApiException::invalidCredentials();
    }

    /** Loads a profile by the reference and login stored for the session. */
    public function loadByRef(string $ref, string $login): array
    {
        try {
            $res = $this->zenith->call('LoadCustomer', ['ByReference' => ['RefCustomer' => $ref, 'Login' => $login]]);
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }

        return self::valid($res['Customer'] ?? null) ?? throw ApiException::unavailable();
    }

    public function update(array $customer): array
    {
        try {
            $res = $this->zenith->call('UpdateCustomer', ['Customer' => $customer]);
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }

        return self::valid($res['Customer'] ?? null) ?? throw ApiException::unavailable();
    }

    /**
     * Changes the password after Zenith re-checks the current one: the
     * request identifies the customer by login AND current password.
     */
    public function changePassword(string $login, string $currentPassword, string $newPassword): ?array
    {
        try {
            $res = $this->zenith->call('ChangeCustomerPassword', [
                'ByLoginAndPassword' => ['Login' => $login, 'Password' => $currentPassword],
                'NewPassword' => $newPassword,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException && $e->zenithCode === 'WrongVerificationCustomer') {
                throw ApiException::wrongCurrentPassword();
            }
            $this->mapCommonErrors($e);
        }

        return self::valid($res['Customer'] ?? null);
    }

    /**
     * Asks Zenith to email a reset link. Says nothing about whether the email
     * exists. $login picks the account when several share the email
     * (LostCustomerPassword.CustomerLogin).
     */
    public function requestPasswordReset(string $email, ?string $login = null): void
    {
        try {
            $this->zenith->call('LostCustomerPassword', array_filter(['EmailAddress' => $email, 'CustomerLogin' => $login]));
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }
    }

    /** Asks the airline to delete the customer's account (handled by its staff). */
    public function requestRemoval(string $ref, string $login, ?string $reason): void
    {
        $body = ['ByReference' => ['RefCustomer' => $ref, 'Login' => $login]];
        if ($reason) {
            $body['RemovalReason'] = $reason;
        }
        try {
            $this->zenith->call('SendCustomerRemovalRequest', $body);
        } catch (Throwable $e) {
            $this->mapCommonErrors($e);
        }
    }

    /** The airline's password rules for end customers; best effort. */
    public function passwordPolicies(): array
    {
        try {
            $res = $this->zenith->call('CustomerPasswordPolicies');
        } catch (Throwable) {
            // Our own minimum still applies; the airline's extra rules are best effort.
            return [];
        }
        $policies = [];
        foreach ((array) ($res['Policies'] ?? []) as $p) {
            $type = $p['CustomerTypeCode'] ?? null;
            if ((! $type || $type === 'EndCustomer') && is_array($p['Policy'] ?? null)) {
                $policies[] = $p['Policy'];
            }
        }

        return $policies;
    }
}
