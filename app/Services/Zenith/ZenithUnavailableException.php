<?php

namespace App\Services\Zenith;

use RuntimeException;

/** Anything that is not a normal business answer: timeouts, HTTP errors, bad JSON. */
class ZenithUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null)
    {
        parent::__construct($message);
    }
}
