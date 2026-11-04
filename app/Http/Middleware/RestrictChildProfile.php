<?php

namespace App\Http\Middleware;

use App\Support\ActiveChildProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictChildProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ActiveChildProfile::id($request) !== null) {
            $path = $request->path();
            $blocked = ['family', 'wallet', 'billing', 'subscriptions', 'data-bundles', 'data-purchases',
                'referral', 'reviews', 'plans', 'telco', 'payment', 'transactions', 'invoices',
                'payouts', 'teacher-compensation', 'seats', 'schools',
                'me/learner-profile', 'me/devices', 'me/notifications', 'me/push'];
            $relative = preg_replace('#^api/v1/#', '', $path);
            $blockedAction = preg_match('#^learning/(stories/[^/]+/purchase|story-purchases/)#', $relative)
                || preg_match('#^(assignment-submissions|class-assignment-submissions)/[^/]+/review$#', $relative)
                || (str_starts_with($relative, 'chores') && ! preg_match('#^chores/[^/]+/submissions$#', $relative));
            if ($blockedAction) {
                return $this->deny();
            }
            foreach ($blocked as $prefix) {
                if (str_starts_with($path, 'api/v1/'.$prefix)) {
                    return $this->deny();
                }
            }
        }

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json(['error' => [
            'code' => 'parent_only', 'message' => 'Ask a grown-up to open this page.', 'status' => 403,
        ]], 403);
    }
}
