<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An app API failure with a status, a stable code and a message that is
 * safe to show users. Rendered as {"error": {"code", "message", ...}}.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  list<array{passengerRef: ?string, field: ?string, message: string}>  $problems
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        public readonly array $problems = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $error = ['code' => $this->errorCode, 'message' => $this->getMessage()];
        if ($this->problems !== []) {
            $error['problems'] = $this->problems;
        }
        $error += $this->extra;

        $headers = $this->retryAfterSeconds ? ['Retry-After' => (string) $this->retryAfterSeconds] : [];

        return response()->json(['error' => $error], $this->status, $headers + ['Cache-Control' => 'no-store']);
    }

    public static function invalidCredentials(bool $byEmail = false): self
    {
        return new self(401, 'invalid_credentials', $byEmail ? 'Incorrect email or password.' : 'Incorrect username or password.');
    }

    public static function emailNotRegistered(): self
    {
        return new self(404, 'email_not_registered', "We couldn't sign you in with this email and password. If you already have a Naoero Airlines account, check your password or reset it. Otherwise, please sign up.");
    }

    public static function wrongCurrentPassword(): self
    {
        return new self(400, 'wrong_current_password', 'Your current password is incorrect.');
    }

    public static function emailTaken(): self
    {
        return new self(409, 'email_taken', 'An account with this email already exists. Try logging in instead.');
    }

    public static function usernameTaken(): self
    {
        return new self(409, 'username_taken', 'This username is taken. Please choose another one.', extra: ['field' => 'username']);
    }

    /** Signing in with a username that we don't know and Zenith refused. */
    public static function usernameNotRegistered(): self
    {
        return new self(404, 'username_not_registered', "We couldn't sign you in with this username and password. Check both, or reset your password. If you don't have an account yet, please sign up.");
    }

    /** Signing in by email when several accounts use that email. */
    public static function severalAccounts(): self
    {
        return new self(409, 'several_accounts', 'More than one account uses this email address. Please sign in with your username instead.');
    }

    /** Forgot password: the airline site's own words when several accounts use the email. */
    public const SEVERAL_ACCOUNTS_FOR_RESET = 'Several accounts are linked to this email address, please specify the log in, if not, a new password recovery email will be sent for each account.';

    /** Forgot password, for a client that did not send askForLogin: several accounts use the email. */
    public static function severalAccountsForReset(): self
    {
        return new self(409, 'several_accounts', self::SEVERAL_ACCOUNTS_FOR_RESET);
    }

    public static function tooManyAttempts(int $retryAfterSeconds = 900): self
    {
        return new self(429, 'too_many_attempts', 'Too many attempts. Please wait a few minutes and try again.', $retryAfterSeconds);
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'unauthenticated', 'Please log in again.');
    }

    public static function sessionExpired(): self
    {
        return new self(401, 'session_expired', 'Your session has ended. Please log in again.');
    }

    public static function accountDisabled(): self
    {
        return new self(403, 'account_disabled', 'This account has been disabled, so it can no longer sign in. If you think this is a mistake, please contact Naoero Airlines.');
    }

    public static function unavailable(): self
    {
        return new self(503, 'unavailable', 'Account services are temporarily unavailable. Please try again shortly.');
    }

    public static function invalid(string $message): self
    {
        return new self(400, 'invalid_request', $message);
    }
}
