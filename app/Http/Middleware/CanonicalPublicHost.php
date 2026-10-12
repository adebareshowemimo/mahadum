<?php

namespace App\Http\Middleware;

use App\Services\PublicSeo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalPublicHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), ['mahadum360.com', 'www.mahadum360.com'], true)
            && ($request->getHost() !== 'mahadum360.com' || ! $request->isSecure())) {
            return redirect()->away(PublicSeo::ORIGIN.$request->getRequestUri(), 301);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 404) {
            $response->headers->set('X-Robots-Tag', 'noindex,follow');
        }

        return $response;
    }
}
