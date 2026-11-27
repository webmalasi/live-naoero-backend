<?php

namespace App\Services\Zenith;

use RuntimeException;

/** Zenith answered with a business error (HTTP 200 + ResponseInfo.Error). */
class ZenithBusinessException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $body  The whole response, for errors that carry details (InvalidData).
     */
    public function __construct(
        public readonly string $zenithCode,
        string $message,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }
}
