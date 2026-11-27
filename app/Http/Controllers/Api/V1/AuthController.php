<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Exceptions\TokenReuseException;
use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\LogoutRequest;
use App\Http\Requests\Api\V1\Auth\RefreshRequest;
use App\Http\Requests\Api\V1\Auth\SignupRequest;
use App\Services\Auth\AccessToken;
use App\Services\Auth\AccountDeletion;
use App\Services\Auth\AuthEvents;
use App\Services\Auth\CustomerSessions;
use App\Services\Auth\CustomerStore;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\Usernames;
use App\Services\Auth\ZenithCustomers;
use App\Services\Flights\FlightConfigService;
use App\Services\Logging\ServerLog;
use App\Services\Reference\ReferenceService;
use App\Services\Support\Js;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Customer accounts. Zenith decides whether a password is right; no
 * password, and nothing derived from one, is ever stored here.
 */
class AuthController extends ApiController
{
    // Identical whether or not the email has an account, so the endpoint
    // can't be used to find out who is registered.
    private const RESET_SENT_MESSAGE = "If an account exists, we've sent a reset link. "
        .'It expires in about two hours, so check your inbox (and spam folder) soon.';

    public function __construct(
        private readonly ZenithCustomers $zenith,
        private readonly CustomerStore $customers,
        private readonly CustomerSessions $sessions,
        private readonly AuthEvents $events,
        private readonly PasswordPolicy $policy,
        private readonly ReferenceService $reference,
        private readonly FlightConfigService $flights,
        private readonly ServerLog $log,
    ) {}

    /**
     * Whether a username can be chosen, as the customer types it. Zenith has
     * no call for this, so it is checked against every account we know and
     * the usernames Zenith refused before; Zenith confirms it at sign-up.
     */
    public function usernameAvailable(Request $request, Usernames $usernames): JsonResponse
    {
        $username = Usernames::normalize((string) $request->query('username'));
        if ($username === '') {
            throw ApiException::invalid('Enter a username to check.');
        }

        return $this->json(['username' => $username, ...$usernames->check($this->zenith->environment(), $username)]);
    }

    /** Creates the Zenith customer (NewCustomer), stores the profile, and signs in. */
    public function signup(SignupRequest $request, Usernames $usernames): JsonResponse
    {
        $input = $request->validated();
        $chosen = isset($input['username']);
        // The login: the chosen username (older apps: the email).
        $login = $input['username'] ?? $input['email'];
        $meta = $this->meta($request, $input['deviceLabel'] ?? null);

        $this->events->enforceLimit('signup', $login, $meta->ip, perLogin: 5, perIp: 10, windowMinutes: 60, countSuccesses: true);
        if ($chosen && ($problem = Usernames::problem($login))) {
            throw new ApiException(400, 'invalid_username', $problem, extra: ['field' => 'username']);
        }
        $this->reference->assertValidTitle($input['title']);
        $problems = PasswordPolicy::problems($input['password'], $this->policy->effective());
        if ($problems) {
            throw ApiException::invalid(PasswordPolicy::sentence($problems));
        }
        if ($chosen && strcasecmp($input['password'], $login) === 0) {
            throw ApiException::invalid('Your password must be different from your username.');
        }

        $environment = $this->zenith->environment();
        // Taken in an account we know: no need to ask Zenith.
        if ($chosen && $usernames->known($environment, $login)) {
            $this->events->record('signup', false, $meta, login: $login, detail: 'username_taken (known here)');
            throw ApiException::usernameTaken();
        }
        try {
            try {
                $currency = ($this->flights->saleConfiguration()['DefaultCurrency'] ?? null) ?: 'AUD';
            } catch (Throwable) {
                $currency = 'AUD';
            }
            $created = $this->zenith->create(CustomerStore::newZenithCustomer($input, $currency), $input['password'], chosenUsername: $chosen);
        } catch (Throwable $e) {
            if ($e instanceof ApiException && $e->errorCode === 'username_taken') {
                // An account made on the airline's own site: remembered for the next check.
                $usernames->rememberTaken($environment, $login);
            }
            $this->events->record('signup', false, $meta, login: $login, detail: $e instanceof ApiException ? $e->errorCode : 'zenith_error');
            throw $e;
        }

        try {
            $result = DB::transaction(function () use ($environment, $created, $meta, $login, $input) {
                $customerId = $this->customers->save($environment, $created, loggedIn: true);
                $this->customers->saveConsents($customerId, ($input['acceptTerms'] ?? false) === true, $input['marketingOptIn'] ?? null);
                $this->customers->refreshMainPhone($customerId, $input['phoneCountry'] ?? null);
                $tokens = $this->sessions->start($customerId, $meta);
                $this->events->record('signup', true, $meta, $customerId, $login, "ref {$created['Ref']}");

                return compact('customerId', 'tokens');
            });
        } catch (Throwable $e) {
            // The Zenith account exists; logging in will store it and sign in.
            $this->log->error('signup', 'saved in Zenith but not locally', ['ref' => $created['Ref'], 'error' => $e->getMessage()]);

            throw new ApiException(503, 'account_created_login_required', 'Your account was created. Please log in to continue.');
        }

        return $this->json(['customer' => $this->customers->profile($result['customerId']), ...$result['tokens']], 201);
    }

    /**
     * Login, with the username (Zenith's login) or with the email:
     *  0. By email, the email is turned into the account's username. Zenith
     *     signs in by login only, and one email can have several accounts:
     *     several here means the customer must use their username. An email
     *     we don't know is tried as the login (older accounts' login is
     *     their email).
     *  1. A disabled account is refused without calling Zenith.
     *  2. Zenith LoadCustomer (ByLoginAndPassword) decides whether the
     *     password is right. Whatever Zenith accepts, we accept.
     *     A login we have never seen is asked too: the account may have
     *     been created on the airline's booking site (Zenith) rather than
     *     here. If Zenith accepts it, the account is added here on this
     *     first login, exactly as a signup would. If not, the answer stays
     *     "not registered" (Zenith can't tell an unknown login from a wrong
     *     password), so the app still offers to sign up.
     *  3. The profile is refreshed from Zenith and a session starts.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $input = $request->validated();
        $byEmail = ! isset($input['username']);
        $typed = $input['username'] ?? $input['email'];
        $meta = $this->meta($request, $input['deviceLabel'] ?? null);

        $this->events->enforceLimit('login', $typed, $meta->ip, perLogin: 5, perIp: 30, windowMinutes: 15);

        $environment = $this->zenith->environment();
        $login = $typed;
        if ($byEmail) {
            $accounts = $this->customers->findByEmail($environment, $typed);
            if (count($accounts) > 1) {
                $this->events->record('login', false, $meta, login: $typed, detail: count($accounts).' accounts use this email: username needed (Zenith not called)');
                throw ApiException::severalAccounts();
            }
            if ($accounts !== []) {
                $login = $accounts[0]->zenith_login;
            }
        }
        $registered = $this->customers->findByLogin($environment, $login);
        if ($registered && $registered->status !== 'active') {
            $this->events->record('login', false, $meta, $registered->id, $login, "account {$registered->status} (Zenith not called)");
            throw ApiException::accountDisabled();
        }
        if (! $registered) {
            // Zenith locks an account after about 4 wrong passwords. An email we
            // don't know gets fewer tries, so this app can never be what locks
            // someone's booking-site account.
            $this->events->enforceLimit('login', $login, $meta->ip, perLogin: 3, perIp: 30, windowMinutes: 15);
        }

        try {
            $zenithCustomer = $this->zenith->authenticate($login, $input['password']);
        } catch (Throwable $e) {
            $refused = $e instanceof ApiException && $e->errorCode === 'invalid_credentials';
            $notFound = ! $registered && $refused;
            $this->events->record('login', false, $meta, $registered?->id, $login, match (true) {
                $notFound => ($byEmail ? 'email' : 'username').' not registered here; Zenith refused it too',
                $e instanceof ApiException => $e->errorCode,
                default => 'zenith_error',
            });
            // Unknown here and refused by Zenith: no account, or a wrong password on a booking-site account.
            throw match (true) {
                $notFound => $byEmail ? ApiException::emailNotRegistered() : ApiException::usernameNotRegistered(),
                $refused => ApiException::invalidCredentials($byEmail),
                default => $e,
            };
        }

        if (! $registered) {
            // Only customer accounts are added here; agency and corporate logins belong elsewhere.
            $type = $zenithCustomer['TypeCode'] ?? null;
            if ($type !== null && $type !== '' && $type !== 'EndCustomer') {
                $this->events->record('login', false, $meta, login: $login, detail: "Zenith account of type {$type} not added");
                throw $byEmail ? ApiException::emailNotRegistered() : ApiException::usernameNotRegistered();
            }
        } elseif ((string) $zenithCustomer['Ref'] !== $registered->zenith_customer_ref) {
            // The account behind this login must be the one we have on file.
            $this->events->record('login', false, $meta, $registered->id, $login, "Zenith returned a different customer (ref {$zenithCustomer['Ref']})");
            throw ApiException::unavailable();
        }

        $firstLogin = ! $registered;
        $result = DB::transaction(function () use ($environment, $zenithCustomer, $meta, $login, $typed, $firstLogin) {
            $customerId = $this->customers->save($environment, $zenithCustomer, loggedIn: true);
            if (DB::table('customers')->where('id', $customerId)->value('status') !== 'active') {
                throw ApiException::accountDisabled();
            }
            $tokens = $this->sessions->start($customerId, $meta);
            $this->events->record('login', true, $meta, $customerId, $login, $firstLogin ? 'first login of an account created in Zenith: added here' : null);

            // Zenith can't tell us a password was reset, so sessions on other
            // devices survive it. If a reset was requested recently and other
            // sessions exist, the app offers to sign them out.
            $others = $this->sessions->countOthers($customerId, $tokens['sessionId']);
            $recentReset = DB::table('auth_events')
                // A reset asked by username or by email.
                ->where(fn ($q) => $q->whereRaw('lower(login) = lower(?)', [$login])->orWhereRaw('lower(login) = lower(?)', [$typed]))
                ->where('event', 'password_reset_request')
                ->where('success', true)
                ->where('created_at', '>', Js::dbTime(now()->subDay()))
                ->exists();

            return ['customerId' => $customerId, 'tokens' => $tokens, 'others' => $others, 'suggest' => $recentReset && $others > 0];
        });

        return $this->json([
            'customer' => $this->customers->profile($result['customerId']),
            ...$result['tokens'],
            'otherActiveSessions' => $result['others'],
            'suggestSignOutOtherDevices' => $result['suggest'],
        ]);
    }

    /** Swaps a refresh token for a new access + refresh token pair. */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $input = $request->validated();
        $meta = $this->meta($request, $input['deviceLabel'] ?? null);
        try {
            $result = DB::transaction(fn () => $this->sessions->rotate($input['refreshToken'], $meta));
        } catch (TokenReuseException $e) {
            // An old token came back: someone else has a copy. End that whole
            // device session, including the copy the thief is now holding.
            $this->sessions->revokeFamily($e->familyId, 'reuse_detected');
            $this->events->record('token_reuse', false, $meta, $e->customerId, detail: 'rotated refresh token presented again; session revoked');
            throw $e;
        }

        return $this->json($result['tokens']);
    }

    /**
     * Ends this device's session (by its refresh token), or every session of
     * the signed-in customer with allDevices / otherDevices. Always succeeds
     * for an unknown or already-revoked token, so the app can just clear
     * its storage.
     */
    public function logout(LogoutRequest $request, AuthenticateCustomer $auth): JsonResponse
    {
        $input = $request->validated();
        $meta = $this->meta($request);

        if (! empty($input['allDevices']) || ! empty($input['otherDevices'])) {
            $customer = $auth->resolve($request);
            $keep = ! empty($input['otherDevices']) && empty($input['allDevices']) ? $this->sessionId($request) : null;
            $count = $this->sessions->revokeOthers($customer->id, $keep, $keep ? 'logout_others' : 'logout_all');
            $this->events->record('logout', true, $meta, $customer->id, detail: ($keep ? 'other devices' : 'all devices')." ({$count})");

            return $this->json(['ok' => true, 'sessionsEnded' => $count]);
        }
        if (empty($input['refreshToken'])) {
            throw ApiException::invalid('refreshToken is required.');
        }

        $family = $this->sessions->familyOf($input['refreshToken']);
        if ($family) {
            $this->sessions->revokeFamily($family->family_id, 'logout');
            $this->events->record('logout', true, $meta, $family->customer_id);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * Starts the reset with Zenith's LostCustomerPassword: Zenith emails the
     * customer a link to set a new password on the airline's site. The
     * answer is the same whether or not the email has an account.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $input = $request->validated();
        $email = $input['email'] ?? null;
        $username = $input['username'] ?? null;
        $meta = $this->meta($request);
        // A reset while signed in ends that session first.
        $endedFor = $this->endCallerSession($request);
        // 3 per hour per email/username and per IP: stops inbox flooding and
        // saves Zenith quota. The same limit applies whether it exists or not.
        $this->events->enforceLimit('password_reset_request', $username ?? $email, $meta->ip, perLogin: 3, perIp: 3, windowMinutes: 60, countSuccesses: true);

        // Zenith sends the link to the account's email; a username alone is
        // turned into its email when we know the account.
        $environment = $this->zenith->environment();
        $account = $username !== null ? $this->customers->findByLogin($environment, $username) : null;
        $email ??= $account?->email;
        $logins = $email !== null ? $this->resetLogins($environment, $email, $username, (bool) ($input['allAccounts'] ?? false)) : [];
        if ($logins === null) {
            // Nothing sent and nothing counted: the customer is asked for the login.
            if ($input['askForLogin'] ?? false) {
                return $this->json(['ok' => false, 'needsLogin' => true, 'code' => 'several_accounts', 'message' => ApiException::SEVERAL_ACCOUNTS_FOR_RESET]);
            }
            throw ApiException::severalAccountsForReset();
        }
        foreach ($logins as $login) {
            $this->zenith->requestPasswordReset($email, $login);
        }
        $this->events->record('password_reset_request', $email !== null, $meta, $endedFor ?? $account?->id, $username ?? $email,
            trim(($endedFor ? 'caller session ended. ' : '').($email === null ? 'username unknown here: no email to send to' : '')) ?: null);
        // Recorded under the email too, so signing in either way sees the reset.
        if ($username !== null && $email !== null) {
            $this->events->record('password_reset_request', true, $meta, $account?->id, $email, "for username {$username}");
        }

        return $this->json(['ok' => true, 'message' => self::RESET_SENT_MESSAGE]);
    }

    /**
     * Which accounts to send the reset for, as the airline's own site does.
     * Zenith answers the same whatever happens and sends nothing when the
     * email has several accounts and no login is named, so a login is always
     * named when we know it. Several accounts and no login: null, so the
     * customer is asked for one; allAccounts sends one email per account.
     *
     * @return list<string|null>|null the logins (a null login: let Zenith pick by the email); null: ask for the login
     */
    private function resetLogins(string $environment, string $email, ?string $username, bool $allAccounts): ?array
    {
        if ($username !== null) {
            return [$username];
        }
        $logins = collect($this->customers->findByEmail($environment, $email))
            ->pluck('zenith_login')->filter()->unique(fn ($l) => strtolower($l))->values();
        if ($logins->count() > 1 && ! $allAccounts) {
            return null;
        }

        return $logins->isEmpty() ? [null] : $logins->all();
    }

    /** Ends the caller's own session, if the request carries one. */
    private function endCallerSession(Request $request): ?string
    {
        if (! preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m)) {
            return null;
        }
        try {
            $claims = AccessToken::verify(trim($m[1]));
        } catch (ApiException) {
            return null; // an expired or bad token has nothing left to end
        }
        $this->sessions->revokeFamily($claims['sessionId'], 'password_reset_request');

        return $claims['customerId'];
    }

    /**
     * Changes the password for the signed-in customer. Zenith re-checks the
     * current password itself, so a stolen session alone cannot change it.
     * Other devices are signed out.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $input = $request->validated();
        $meta = $this->meta($request);
        $login = $customer->zenith_login;

        $this->events->enforceLimit('password_change', $login, $meta->ip, perLogin: 5, perIp: 20, windowMinutes: 15);
        if ($input['newPassword'] === $input['currentPassword']) {
            throw ApiException::invalid('Your new password must be different from your current one.');
        }
        $problems = PasswordPolicy::problems($input['newPassword'], $this->policy->effective());
        if ($problems) {
            throw ApiException::invalid(PasswordPolicy::sentence($problems));
        }

        try {
            $updated = $this->zenith->changePassword($login, $input['currentPassword'], $input['newPassword']);
        } catch (Throwable $e) {
            $this->events->record('password_change', false, $meta, $customer->id, $login, $e instanceof ApiException ? $e->errorCode : 'zenith_error');
            throw $e;
        }

        $sessionId = $this->sessionId($request);
        $signedOut = DB::transaction(function () use ($updated, $customer, $sessionId, $meta, $login) {
            if ($updated) {
                $this->customers->save($customer->zenith_environment, $updated);
            }
            $count = $this->sessions->revokeOthers($customer->id, $sessionId, 'password_changed');
            $this->events->record('password_change', true, $meta, $customer->id, $login, "other sessions revoked: {$count}");

            return $count;
        });

        return $this->json(['ok' => true, 'otherSessionsSignedOut' => $signedOut, 'message' => 'Your password has been changed.']);
    }

    /**
     * Everything the sign-up and account screens need from the airline: the
     * titles to offer, the password rules, and account deletion (always
     * available: staff disable the account, nothing is deleted) with its reasons.
     */
    public function config(): JsonResponse
    {
        try {
            return $this->json([
                'titles' => $this->reference->titles(),
                'passwordPolicy' => PasswordPolicy::describe($this->policy->effective()),
                // The rules a new username must follow (also checked here).
                'usernameRules' => ['pattern' => '^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$', 'minLength' => 3, 'maxLength' => 50, 'description' => Usernames::RULES],
                // Our own process (staff disable the account), always open.
                'accountDeletionAvailable' => true,
                'accountDeletionReasons' => AccountDeletion::reasons(),
            ]);
        } catch (Throwable $e) {
            $this->log->error('auth-config', $e->getMessage());
            throw ApiException::unavailable();
        }
    }

    /** The password rules, so the app can check them as the user types. */
    public function passwordPolicy(): JsonResponse
    {
        return $this->json(PasswordPolicy::describe($this->policy->effective()));
    }
}
