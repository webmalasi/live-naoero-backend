<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

/** Who is calling: recorded with sessions and auth events. */
final class RequestMeta
{
    public function __construct(
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public ?string $deviceLabel = null,
    ) {}

    /** The client's address as nginx passes it (X-Forwarded-For, then X-Real-IP). */
    public static function clientKey(Request $request): string
    {
        $forwarded = trim(explode(',', (string) $request->header('X-Forwarded-For'))[0]);

        return $forwarded !== '' ? $forwarded : ((string) $request->header('X-Real-IP') ?: 'local');
    }

    public static function from(Request $request, ?string $deviceLabel = null): self
    {
        $ip = self::clientKey($request);
        $agent = $request->userAgent();

        return new self(
            $ip === 'local' ? null : $ip,
            $agent === null ? null : mb_substr($agent, 0, 300),
            $deviceLabel,
        );
    }
}
