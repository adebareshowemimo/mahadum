<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

/**
 * Locks in the first-party web SPA auth wiring: the CSRF-cookie endpoint, the
 * stateful-frontend middleware on the `api` group, and session-cookie auth.
 * (CSRF token-matching itself is auto-bypassed by Laravel's test runner, so it
 * is not asserted here.)
 */
class SpaStatefulAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_api_group_has_stateful_frontend_middleware(): void
    {
        $apiGroup = $this->app['router']->getMiddlewareGroups()['api'] ?? [];

        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $apiGroup);
    }

    public function test_spa_session_cookie_authenticates_api_request(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('parent');

        // Simulate a first-party SPA call: authenticated via the web session
        // guard with an Origin from a stateful domain.
        $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_bearer_token_clients_still_authenticate(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('parent');

        // No Origin header → not treated as first-party; bearer/token path.
        $this->actingAsUser($user);
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }
}
