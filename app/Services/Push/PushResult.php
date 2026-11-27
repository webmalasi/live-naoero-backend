<?php

namespace App\Services\Push;

/** What Apple or Google answered for one push. */
final class PushResult
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** The token is no longer valid: the app was removed or the token replaced. */
    public const INVALID_TOKEN = 'invalid_token';

    public function __construct(
        public readonly string $status,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        /** Worth trying again later (network trouble, 429, 5xx). */
        public readonly bool $retryable = false,
    ) {}

    public static function sent(int $httpStatus, ?string $providerId): self
    {
        return new self(self::SENT, $httpStatus, $providerId);
    }

    public static function failed(?int $httpStatus, string $error, bool $retryable = false): self
    {
        return new self(self::FAILED, $httpStatus, null, mb_substr($error, 0, 500), $retryable);
    }

    public static function invalidToken(?int $httpStatus, string $error): self
    {
        return new self(self::INVALID_TOKEN, $httpStatus, null, mb_substr($error, 0, 500));
    }

    public function ok(): bool
    {
        return $this->status === self::SENT;
    }
}
