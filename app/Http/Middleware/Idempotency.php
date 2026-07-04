<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency for money POSTs. The client sends a stable Idempotency-Key; the
 * first response is cached (24h) and replayed verbatim on any retry with the
 * same key + user, so a dropped connection can't double-charge.
 *
 * A 409 is returned if the same key is reused for a *different* request body.
 */
class Idempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_required',
                    'message' => 'This endpoint requires an Idempotency-Key header.',
                    'status' => 422,
                ],
            ], 422);
        }

        $userId = optional($request->user())->id ?? 'guest';
        $cacheKey = "idem:{$userId}:{$key}";
        $fingerprint = sha1($request->getMethod().$request->path().$request->getContent());

        if ($cached = Cache::get($cacheKey)) {
            if (($cached['fingerprint'] ?? null) !== $fingerprint) {
                return response()->json([
                    'error' => [
                        'code' => 'idempotency_conflict',
                        'message' => 'This Idempotency-Key was used with a different request.',
                        'status' => 409,
                    ],
                ], 409);
            }

            return response($cached['body'], $cached['status'])
                ->withHeaders($cached['headers'] ?? [])
                ->header('Idempotency-Replayed', 'true');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
                'headers' => ['Content-Type' => $response->headers->get('Content-Type')],
            ], now()->addHours(24));
        }

        return $response;
    }
}
