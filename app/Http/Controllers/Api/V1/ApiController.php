<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\RequestMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** The signed-in customer, set by the "customer" middleware. */
    protected function customer(Request $request): object
    {
        return $request->attributes->get('customer');
    }

    protected function sessionId(Request $request): string
    {
        return $request->attributes->get('sessionId');
    }

    protected function meta(Request $request, ?string $deviceLabel = null): RequestMeta
    {
        return RequestMeta::from($request, $deviceLabel);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, $headers + ['Cache-Control' => 'no-store'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
