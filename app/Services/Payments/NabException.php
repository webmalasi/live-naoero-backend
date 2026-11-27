<?php

namespace App\Services\Payments;

use RuntimeException;

/** The payment gateway failed or answered something we can't use. */
class NabException extends RuntimeException {}
