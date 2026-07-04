<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force-update gate. Compares the client's X-App-Version against the per-platform
 * minimum from config('app_versions'). Returns 426 Upgrade Required when stale.
 * Non-mobile callers (no X-Client header) pass through untouched.
 */
class MinAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $platform = $request->header('X-Client');       // ios | android
        $version = $request->header('X-App-Version');  // e.g. 1.4.0

        if ($platform && $version) {
            $min = config("app_versions.min.$platform");

            if ($min && version_compare($version, $min, '<')) {
                return response()->json([
                    'error' => [
                        'code' => 'upgrade_required',
                        'message' => 'Please update the app to continue.',
                        'status' => 426,
                    ],
                ], 426);
            }
        }

        return $next($request);
    }
}
