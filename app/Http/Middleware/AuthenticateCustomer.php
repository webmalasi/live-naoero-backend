<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Auth\AccessToken;
use App\Services\Auth\CustomerSessions;
use App\Services\Auth\CustomerStore;
use App\Services\Logging\ServerLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Bearer access token to an active customer and a live device
 * session. A session revoked by logout or a password change elsewhere stops
 * working at once, not when its 15-minute access token runs out.
 *
 * Sets the request attributes "customer" (id, zenith_environment,
 * zenith_customer_ref, zenith_login, status) and "sessionId".
 */
class AuthenticateCustomer
{
    public function __construct(
        private readonly CustomerStore $customers,
        private readonly CustomerSessions $sessions,
        private readonly ServerLog $log,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->resolve($request);

        return $next($request);
    }

    /** Authenticates $request and sets its attributes; throws when it can't. */
    public function resolve(Request $request): object
    {
        if (! preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m)) {
            throw ApiException::unauthenticated();
        }
        $claims = AccessToken::verify(trim($m[1]));
        $customer = $this->customers->find($claims['customerId']) ?? throw ApiException::unauthenticated();
        $this->log->setCustomer($customer->id);
        if ($customer->status !== 'active') {
            throw ApiException::accountDisabled();
        }
        if (! $this->sessions->isActive($claims['sessionId'])) {
            throw ApiException::sessionExpired();
        }

        $request->attributes->set('customer', $customer);
        $request->attributes->set('sessionId', $claims['sessionId']);

        return $customer;
    }
}
