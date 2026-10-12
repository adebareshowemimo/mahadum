<?php

namespace App\Http\Controllers;

use App\Services\PublicSeo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicWebsiteController extends Controller
{
    public function __construct(private PublicSeo $seo) {}

    public function shell(Request $request): Response
    {
        $paymentReference = $request->query('paymentReference');
        if ($request->path() === '/' && is_string($paymentReference) && preg_match('/^data_[a-f0-9]{64}$/D', $paymentReference)) {
            return redirect('/billing/data?'.http_build_query($request->query()));
        }
        $path = '/'.trim($request->path(), '/');
        $redirects = ['/v1' => '/', '/v5' => '/schools'];
        if (isset($redirects[$path])) {
            return redirect($redirects[$path], 301);
        }
        $pages = $this->seo->pages();
        $public = isset($pages[$path]);
        $file = $public
            ? config('seo.prerender').'/'.($path === '/' ? 'home' : substr($path, 1)).'.html'
            : config('seo.shell');
        $status = $public || $this->knownAppRoute($path) ? 200 : 404;
        abort_unless(is_file($file), 503, 'Website build is unavailable.');
        $meta = $pages[$path] ?? ['title' => $status === 404 ? 'Page not found | Mahadum360' : 'Mahadum360', 'description' => 'Mahadum360 Nigerian language learning.'];
        $html = $this->seo->decorate(file_get_contents($file), $path, $meta, $public);

        return response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache, no-store, must-revalidate', 'X-Robots-Tag' => $public ? 'index,follow' : 'noindex,follow']);
    }

    private function knownAppRoute(string $path): bool
    {
        $file = config('seo.prerender').'/spa-routes.json';
        if (! is_file($file)) {
            return false;
        }
        $routes = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        foreach ($routes as $route) {
            // Build output is trusted code, never a request-controlled pattern.
            $pattern = preg_quote($route, '~');
            $pattern = preg_replace('~\\\\:[A-Za-z][A-Za-z0-9_]*~', '[^/]+', $pattern);
            if (preg_match('~^'.$pattern.'$~D', $path)) {
                return true;
            }
        }

        return false;
    }
}
