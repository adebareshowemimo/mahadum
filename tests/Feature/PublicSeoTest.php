<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\PublicDocument;
use App\Models\User;
use App\Services\SignupDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    private string $build;

    protected function setUp(): void
    {
        parent::setUp();
        $this->build = sys_get_temp_dir().'/mahadum-seo-test-'.Str::uuid();
        File::makeDirectory($this->build, recursive: true);
        $shell = '<!doctype html><html><head><title>Old</title><meta name="description" content="Old"></head><body><div id="root"></div></body></html>';
        File::put($this->build.'/shell.html', $shell);
        File::put($this->build.'/families.html', str_replace('<div id="root"></div>', '<main><h1>Families learn together</h1><p>Readable without JavaScript.</p></main>', $shell));
        File::put($this->build.'/spa-routes.json', json_encode(['/register', '/home', '/school/students', '/learn/lessons/:lessonId']));
        File::put($this->build.'/chrome.json', json_encode([
            'header' => '<header>Shared website navigation</header>',
            'footer' => '<footer>Shared website footer</footer>',
            'styles' => ['/assets/website.css'],
            'script' => '/assets/public-chrome.js',
        ]));
        config(['seo.shell' => $this->build.'/shell.html', 'seo.prerender' => $this->build]);
    }

    protected function tearDown(): void
    {
        if (isset($this->build) && str_contains($this->build, '/mahadum-seo-test-')) {
            File::deleteDirectory($this->build);
        }
        parent::tearDown();
    }

    public function test_public_html_has_visible_content_unique_metadata_canonical_and_schema(): void
    {
        $response = $this->get('/families')->assertOk()->assertSee('Readable without JavaScript.')->assertSee('Families learn together');
        $response->assertSee('Nigerian Languages for Families, Home &amp; Abroad | Mahadum360', false)
            ->assertSee('rel="canonical" href="https://mahadum360.com/families"', false)
            ->assertSee('https://mahadum360.com/images/og-cover.jpg', false)
            ->assertDontSee('<title>Old</title>', false);
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $matches);
        $types = array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR)['@type'], $matches[1]);
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_all_content_pages_use_built_website_header_footer_and_styles(): void
    {
        PublicDocument::create($this->guide());
        foreach (['/languages', '/learn-igbo', '/learn-yoruba', '/learn-hausa', '/guides', '/guides/greetings', '/vote'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<header>Shared website navigation</header>', false)
                ->assertSee('<footer>Shared website footer</footer>', false)
                ->assertSee('href="/assets/website.css"', false)
                ->assertSee('src="/assets/public-chrome.js"', false);
        }
    }

    public function test_missing_chrome_build_returns_service_unavailable(): void
    {
        File::delete($this->build.'/chrome.json');
        $this->get('/guides')->assertStatus(503);
    }

    public function test_missing_pages_are_404_and_known_account_routes_are_noindex(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex,follow');
        $this->get('/register')->assertOk()->assertSee('noindex,follow');
        $this->get('/learn/lessons/42')->assertOk()->assertHeader('X-Robots-Tag', 'noindex,follow');
        $this->get('/school/students')->assertOk();
    }

    public function test_editorial_headline_updates_are_escaped_and_shared_with_the_browser(): void
    {
        PublicDocument::where('path', '/families')->update(['heading' => 'Approved family headline <script>']);
        $response = $this->get('/families')->assertOk();
        $response->assertSee('<h1>Approved family headline &lt;script&gt;</h1>', false);
        preg_match('~<script id="public-page-copy" type="application/json">(.*?)</script>~s', $response->getContent(), $matches);
        $copy = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Approved family headline <script>', $copy['/families']['heading']);
    }

    public function test_canonical_redirect_preserves_path_and_query(): void
    {
        $this->get('http://www.mahadum360.com/families?utm_source=youtube')->assertStatus(301)->assertRedirect('https://mahadum360.com/families?utm_source=youtube');
    }

    public function test_sitemap_is_xml_and_contains_only_public_pages_and_published_guides(): void
    {
        $guide = PublicDocument::create($this->guide());
        PublicDocument::create($this->guide('/guides/draft'));
        PublicDocument::where('path', '/guides/draft')->update(['published' => false]);
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $response->assertSee('https://mahadum360.com'.$guide->path)->assertSee('/learn-hausa')->assertDontSee('/guides/draft')->assertDontSee('/home');
        $this->get('/guides/draft')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://mahadum360.com/sitemap.xml')->assertSee('Disallow: /school$')->assertDontSee('Disallow: /schools');
        $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_language_content_audio_video_and_schema_use_the_same_approved_data(): void
    {
        $document = PublicDocument::where('path', '/learn-hausa')->firstOrFail();
        $document->update(['content' => [
            'language' => 'hausa', 'phrases' => [['text' => 'Sannu', 'meaning' => 'Hello', 'pronunciation' => 'san-nu', 'audio_url' => 'https://mahadum360.com/storage/approved.mp3']],
            'faqs' => [['question' => 'Is it free?', 'answer' => 'Level 0 is free.']], 'levels' => [],
            'videos' => [['youtube_id' => 'abcdefghijk', 'title' => 'Greetings', 'description' => 'An approved lesson.', 'upload_date' => '2026-10-08']],
        ]]);
        $response = $this->get('/learn-hausa')->assertOk()->assertSee('Sannu')->assertSee('preload="none"', false)->assertSee('/register?lang=hausa')->assertSee('data-youtube="abcdefghijk"', false)->assertDontSee('<iframe', false);
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $matches);
        $types = array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR)['@type'], $matches[1]);
        foreach (['Course', 'FAQPage', 'VideoObject'] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_signup_records_one_vote_preferred_language_source_and_conversion_events(): void
    {
        $this->seedRbac();
        $language = Language::create(['code' => 'ha', 'name' => 'Hausa', 'is_active' => true, 'position' => 1]);
        $input = ['first_name' => 'Test', 'last_name' => 'Learner', 'email' => 'seo@test.local', 'phone' => '+2348037771234', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'Test', 'account_type' => 'individual', 'preferred_language' => 'hausa', 'language_vote' => 'Tiv', 'utm_source' => 'youtube', 'utm_medium' => 'social', 'utm_campaign' => 'hausa'];
        $response = $this->postJson('/api/v1/auth/register', $input)->assertCreated();
        $id = $response->json('data.user.id');
        $this->assertDatabaseHas('users', ['id' => $id, 'preferred_language' => 'hausa', 'utm_source' => 'youtube']);
        $this->assertDatabaseHas('learner_profiles', ['user_id' => $id, 'target_language_id' => $language->id]);
        $this->assertDatabaseHas('language_votes', ['user_id' => $id, 'language' => 'Tiv']);
        $input['email'] = 'SEO@TEST.LOCAL';
        $input['phone'] = '+2348037775678';
        $this->postJson('/api/v1/auth/register', $input)->assertUnprocessable();
        $this->assertSame(1, DB::table('language_votes')->count());
        app(SignupDiscovery::class)->firstLesson($id);
        app(SignupDiscovery::class)->firstLesson($id);
        $this->assertSame(1, DB::table('discovery_events')->where('name', 'first_lesson_completed')->count());
        $this->get('/vote')->assertOk()->assertDontSee('1 of 10,000 votes');
        DB::table('users')->where('id', $id)->update(['email_verified_at' => now()]);
        $this->get('/vote')->assertOk()->assertSee('1 of 10,000 votes');
    }

    public function test_publishing_is_role_guarded_audited_and_immediately_updates_sitemap(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('student'));
        $this->postJson('/api/v1/website/documents', $this->guide())->assertForbidden();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $response = $this->postJson('/api/v1/website/documents', $this->guide())->assertCreated();
        $this->get('/guides/greetings')->assertOk()->assertSee('Approved guide text.')->assertSee('Article');
        $this->get('/sitemap.xml')->assertSee('/guides/greetings');
        $this->assertDatabaseHas('audit_logs', ['action' => 'website.document.created']);
        $this->putJson('/api/v1/website/documents/'.$response->json('data.id'), $this->guide())->assertOk();
        $bad = $this->guide();
        $bad['description'] = str_repeat('x', 200);
        $this->putJson('/api/v1/website/documents/'.$response->json('data.id'), $bad)->assertUnprocessable();
        $this->getJson('/api/v1/website/reports')->assertForbidden();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->get('/api/v1/website/votes.csv')->assertOk();
    }

    public function test_language_voting_requires_verified_account_and_prevents_duplicates(): void
    {
        $this->postJson('/api/v1/language-votes', ['language' => 'Tiv'])->assertUnauthorized();
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAsUser($user);
        $this->postJson('/api/v1/language-votes', ['language' => 'Tiv'])->assertForbidden();
        $this->assertDatabaseCount('language_votes', 0);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/api/v1/language-votes', ['language' => 'Invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/language-votes', ['language' => 'Other', 'other_language' => '   '])->assertUnprocessable();
        $this->postJson('/api/v1/language-votes', ['language' => 'Benin'])->assertCreated();
        $this->postJson('/api/v1/language-votes', ['language' => 'Tiv'])->assertConflict();
        $this->assertDatabaseCount('language_votes', 1);
    }

    public function test_benin_replaces_edo_and_existing_votes_keep_their_count(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('language_votes')->insert([
            'user_id' => $user->id,
            'language' => 'Edo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_10_23_000001_rename_edo_votes_to_benin.php'))->up();

        $this->assertDatabaseHas('language_votes', ['user_id' => $user->id, 'language' => 'Benin']);
        $this->get('/vote')->assertOk()->assertSee('Choose Benin')->assertSee('1 of 10,000 votes')->assertDontSee('Choose Edo');
        $this->get('/languages')->assertOk()->assertSee('Benin')->assertDontSee('>Edo<');
    }

    /** @return array<string, mixed> */
    private function guide(string $path = '/guides/greetings'): array
    {
        return ['path' => $path, 'kind' => 'guide', 'title' => 'Hausa Greetings | Mahadum360', 'description' => 'An approved Hausa guide.', 'heading' => 'Hausa greetings', 'intro' => 'Start with greetings.', 'body' => 'Approved guide text.', 'author' => 'Content team', 'published' => true, 'published_at' => now(), 'content' => ['language' => 'hausa', 'phrases' => [], 'faqs' => [], 'videos' => [], 'levels' => []]];
    }
}
