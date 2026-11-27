<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\Me\DeleteRequestRequest;
use App\Http\Requests\Api\V1\Me\UpdateProfileRequest;
use App\Services\Auth\AccountDeletion;
use App\Services\Auth\AuthEvents;
use App\Services\Auth\CustomerSessions;
use App\Services\Auth\CustomerStore;
use App\Services\Auth\ZenithCustomers;
use App\Services\Reference\ReferenceService;
use App\Services\Support\Js;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfileController extends ApiController
{
    public const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly ZenithCustomers $zenith,
        private readonly CustomerStore $customers,
        private readonly CustomerSessions $sessions,
        private readonly AuthEvents $events,
        private readonly ReferenceService $reference,
    ) {}

    /**
     * The signed-in customer's profile. Refreshed from Zenith (the system of
     * record) and stored; if Zenith is unreachable, the stored copy is
     * returned with stale: true rather than failing the screen.
     */
    public function show(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $stale = false;
        try {
            $fresh = $this->zenith->loadByRef($customer->zenith_customer_ref, $customer->zenith_login);
            DB::transaction(fn () => $this->customers->save($customer->zenith_environment, $fresh));
        } catch (ApiException) {
            $stale = true;
        }

        return $this->json(['customer' => $this->customers->profile($customer->id), 'stale' => $stale]);
    }

    /**
     * Updates the profile: name, phone, birth date, address and passport go
     * to Zenith (UpdateCustomer) and are stored; the emergency contact is
     * app-only.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $update = $request->update();
        if (! empty($update['title'])) {
            $this->reference->assertValidTitle($update['title']);
        }
        // The picked country is ours only (Zenith keeps the number itself).
        $zenithFields = array_diff_key($update, ['emergencyContact' => true, 'phoneCountry' => true]);

        if ($zenithFields !== []) {
            $current = $this->zenith->loadByRef($customer->zenith_customer_ref, $customer->zenith_login);
            $saved = $this->zenith->update(CustomerStore::applyProfileUpdate($current, $update));
            // Store what Zenith actually holds now, not what was asked for.
            try {
                $confirmed = $this->zenith->loadByRef((string) ($saved['Ref'] ?? $customer->zenith_customer_ref), $customer->zenith_login);
            } catch (Throwable) {
                $confirmed = $saved;
            }
            DB::transaction(fn () => $this->customers->save($customer->zenith_environment, $confirmed));
        }
        if (array_key_exists('phone', $update) || array_key_exists('phoneCountry', $update)) {
            $this->customers->refreshMainPhone($customer->id, $update['phoneCountry'] ?? null);
        }
        if (array_key_exists('emergencyContact', $update)) {
            $this->customers->saveEmergencyContact($customer->id, $update['emergencyContact']);
        }
        if (! empty($update['address'])) {
            $this->customers->saveAddressExtras($customer->id, $update['address']);
        }

        $this->events->record('profile_update', true, $this->meta($request), $customer->id, $customer->zenith_login, implode(',', array_keys($update)));

        return $this->json(['customer' => $this->customers->profile($customer->id)]);
    }

    /** The real type from the file's first bytes; the Content-Type header is not trusted. */
    public static function sniffImageType(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            strlen($bytes) >= 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    /**
     * The profile photo. Zenith has no photo field, so it is stored only in
     * our database (customer_avatars).
     */
    public function avatar(Request $request): Response
    {
        $customer = $this->customer($request);
        $avatar = DB::table('customer_avatars')->where('customer_id', $customer->id)->first(['content_type', 'image', 'sha256']);
        if (! $avatar) {
            return $this->json(['error' => ['code' => 'not_found', 'message' => 'No profile photo.']], 404);
        }
        $etag = "\"{$avatar->sha256}\"";
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }
        $image = is_resource($avatar->image) ? stream_get_contents($avatar->image) : (string) $avatar->image;

        return response($image, 200, $headers + ['Content-Type' => $avatar->content_type, 'Content-Length' => (string) strlen($image)]);
    }

    /** Replaces the photo; the body is the raw JPEG, PNG or WebP bytes, at most 2 MB. */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        if ((int) $request->header('Content-Length', '0') > self::MAX_AVATAR_BYTES) {
            throw ApiException::invalid('The photo must be 2 MB or smaller.');
        }
        $bytes = $request->getContent();
        if ($bytes === '') {
            throw ApiException::invalid('No photo was sent.');
        }
        if (strlen($bytes) > self::MAX_AVATAR_BYTES) {
            throw ApiException::invalid('The photo must be 2 MB or smaller.');
        }
        $type = self::sniffImageType($bytes) ?? throw ApiException::invalid('The photo must be a JPEG, PNG or WebP image.');

        $updatedAt = Js::dbTime();
        // Bound as a LOB so the bytes reach bytea / blob unchanged.
        $statement = DB::connection()->getPdo()->prepare(
            'insert into customer_avatars (customer_id, content_type, image, byte_size, sha256, updated_at)
             values (:customer, :type, :image, :size, :sha, :at)
             on conflict (customer_id) do update
               set content_type = excluded.content_type, image = excluded.image, byte_size = excluded.byte_size,
                   sha256 = excluded.sha256, updated_at = excluded.updated_at'
        );
        $statement->bindValue(':customer', $customer->id);
        $statement->bindValue(':type', $type);
        $statement->bindValue(':image', $bytes, PDO::PARAM_LOB);
        $statement->bindValue(':size', strlen($bytes), PDO::PARAM_INT);
        $statement->bindValue(':sha', hash('sha256', $bytes));
        $statement->bindValue(':at', $updatedAt);
        $statement->execute();

        $this->events->record('avatar_update', true, $this->meta($request), $customer->id, $customer->zenith_login, "{$type}, ".strlen($bytes).' bytes');

        return $this->json(['ok' => true, 'avatarUpdatedAt' => Js::iso($updatedAt)]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        DB::table('customer_avatars')->where('customer_id', $customer->id)->delete();
        $this->events->record('avatar_remove', true, $this->meta($request), $customer->id, $customer->zenith_login);

        return $this->json(['ok' => true, 'avatarUpdatedAt' => null]);
    }

    /** The reasons to choose from, and the customer's open (or latest) deletion request. */
    public function deletionStatus(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $customerId = $this->customer($request)->id;

        return $this->json([
            'reasons' => AccountDeletion::reasons(),
            'request' => AccountDeletion::present($deletion->pending($customerId) ?? $deletion->latest($customerId)),
        ]);
    }

    /**
     * Account deletion request. Nothing is deleted, here or in Zenith: after
     * the customer re-enters their password, the request waits for staff,
     * who disable the account (it keeps its data and can't sign in) or
     * decline. Until then the customer can still sign in and withdraw it.
     */
    public function deleteRequest(DeleteRequestRequest $request, AccountDeletion $deletion): JsonResponse
    {
        $customer = $this->customer($request);
        $input = $request->validated();
        $meta = $this->meta($request);
        $login = $customer->zenith_login;
        // Older apps send only "reason" (their own words): that is "other".
        $code = $input['reasonCode'] ?? 'other';
        $text = $input['reasonText'] ?? $input['reason'] ?? null;
        if ($code === 'other' && $text === null) {
            throw ApiException::invalid('Please choose a reason, or tell us why you want your account deleted.');
        }

        $this->events->enforceLimit('account_removal', $login, $meta->ip, perLogin: 5, perIp: 20, windowMinutes: 60);
        try {
            $this->zenith->authenticate($login, $input['password']);
        } catch (Throwable $e) {
            $this->events->record('account_removal', false, $meta, $customer->id, $login, 'password check failed');
            if ($e instanceof ApiException && $e->errorCode === 'invalid_credentials') {
                throw ApiException::wrongCurrentPassword();
            }
            throw $e;
        }

        $platform = strtolower((string) $request->header('X-Client-Platform'));
        $result = $deletion->request($customer, $code, $text, $platform, $meta->ip, $request->userAgent());
        if ($result['created']) {
            $this->events->record('account_removal', true, $meta, $customer->id, $login, AccountDeletion::REASONS[$code].($text ? ": {$text}" : ''));
        }

        return $this->json([
            'ok' => true,
            'alreadyRequested' => ! $result['created'],
            'request' => AccountDeletion::present($result['request']),
            'message' => $result['created']
                ? 'Your request has been sent. Our team will review it and disable your account. Until then you can still sign in, and you can withdraw the request.'
                : 'You have already asked for your account to be deleted. Our team will review it.',
        ], $result['created'] ? 201 : 200);
    }

    /** The customer withdraws their waiting deletion request. */
    public function withdrawDeleteRequest(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $customer = $this->customer($request);
        $withdrawn = $deletion->withdraw($customer->id);
        if (! $withdrawn) {
            throw new ApiException(404, 'not_found', 'There is no deletion request waiting to withdraw.');
        }
        $this->events->record('account_removal_withdrawn', true, $this->meta($request), $customer->id, $customer->zenith_login);

        return $this->json(['ok' => true, 'request' => AccountDeletion::present($withdrawn), 'message' => 'Your request has been withdrawn. Your account stays open.']);
    }
}
