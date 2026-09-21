<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return response()->json(['error' => [
                'code' => 'email_not_verified',
                'message' => 'Verify your email address before continuing.',
                'status' => 403,
            ]], 403);
        }

        return $next($request);
    }
}
