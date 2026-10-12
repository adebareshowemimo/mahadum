<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // A smoke test uses its own rendered fixture, preserving real builds.
        $directory = sys_get_temp_dir().'/mahadum-smoke-test-'.Str::uuid();
        File::makeDirectory($directory, recursive: true);
        File::put($directory.'/home.html', '<!doctype html><html><head></head><body><h1>Learn together</h1></body></html>');
        config(['seo.prerender' => $directory]);
        try {
            $this->get('/')->assertOk()->assertSee('Learn together')
                ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
