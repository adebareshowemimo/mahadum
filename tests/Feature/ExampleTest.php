<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // The root route serves the built SPA's index.html (see routes/web.php);
        // stand one up since the deploy build doesn't run in tests.
        $index = base_path('resources/spa/index.html');
        if (! is_dir(dirname($index))) {
            mkdir(dirname($index), recursive: true);
        }
        file_put_contents($index, '<!doctype html><title>test</title>');

        $response = $this->get('/');

        $response->assertStatus(200);

        unlink($index);
    }
}
