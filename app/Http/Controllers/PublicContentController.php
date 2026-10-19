<?php

namespace App\Http\Controllers;

use App\Models\PublicDocument;
use App\Services\PublicSeo;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicContentController extends Controller
{
    public function __construct(private PublicSeo $seo) {}

    public function languages(): Response
    {
        return $this->page('/languages', 'public.languages', ['languages' => $this->languagesList()]);
    }

    public function language(string $language): Response
    {
        abort_unless(in_array($language, ['igbo', 'yoruba', 'hausa'], true), 404);
        $document = PublicDocument::query()->where('path', '/learn-'.$language)->where('published', true)->firstOrFail();
        $content = $document->content ?? [];
        $schema = [[
            '@context' => 'https://schema.org', '@type' => 'Course', 'name' => $document->heading,
            'description' => $document->description, 'inLanguage' => ['igbo' => 'ig', 'yoruba' => 'yo', 'hausa' => 'ha'][$language],
            'provider' => ['@type' => 'Organization', 'name' => 'Mahadum360', 'sameAs' => PublicSeo::ORIGIN],
            'offers' => ['@type' => 'Offer', 'name' => 'Level 0', 'price' => 0, 'priceCurrency' => 'NGN', 'url' => PublicSeo::ORIGIN.'/register?lang='.$language],
        ]];
        if (! empty($content['faqs'])) {
            $schema[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($faq) => ['@type' => 'Question', 'name' => $faq['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']]], $content['faqs'])];
        }
        foreach ($content['videos'] ?? [] as $video) {
            $schema[] = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $video['title'], 'description' => $video['description'], 'uploadDate' => $video['upload_date'], 'thumbnailUrl' => 'https://i.ytimg.com/vi/'.$video['youtube_id'].'/hqdefault.jpg', 'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$video['youtube_id']];
        }

        return $this->page($document->path, 'public.language', ['document' => $document, 'content' => $content, 'language' => $language, 'languages' => $this->languagesList()], $schema);
    }

    public function guides(): Response
    {
        return $this->page('/guides', 'public.guides', ['guides' => PublicDocument::query()->where('kind', 'guide')->where('published', true)->latest('published_at')->get()]);
    }

    public function guide(string $slug): Response
    {
        $document = PublicDocument::query()->where('path', '/guides/'.$slug)->where('kind', 'guide')->where('published', true)->firstOrFail();
        $schema = [['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $document->heading, 'description' => $document->description, 'datePublished' => $document->published_at?->toIso8601String(), 'dateModified' => $document->updated_at?->toIso8601String(), 'author' => ['@type' => 'Person', 'name' => $document->author], 'publisher' => $this->seo->organization(), 'mainEntityOfPage' => PublicSeo::ORIGIN.$document->path]];

        return $this->page($document->path, 'public.guide', ['document' => $document, 'content' => $document->content ?? []], $schema, ['title' => $document->title, 'description' => $document->description]);
    }

    public function vote(): Response
    {
        $counts = DB::table('language_votes')->join('users', 'users.id', '=', 'language_votes.user_id')->whereNotNull('users.email_verified_at')->select('language', DB::raw('COUNT(*) AS total'))->groupBy('language')->pluck('total', 'language');

        return $this->page('/vote', 'public.vote', ['languages' => config('language-votes.languages'), 'counts' => $counts, 'target' => config('language-votes.target')]);
    }

    public function sitemap(): Response
    {
        $urls = [];
        foreach (array_keys($this->seo->pages()) as $path) {
            $document = PublicDocument::query()->where('path', $path)->first();
            $template = config('seo.prerender').'/'.($path === '/' ? 'home' : substr($path, 1)).'.html';
            $urls[] = ['url' => PublicSeo::ORIGIN.$path, 'modified' => $document?->updated_at?->toAtomString() ?? (is_file($template) ? gmdate(DATE_ATOM, filemtime($template)) : null)];
        }
        foreach (PublicDocument::query()->where('kind', 'guide')->where('published', true)->get() as $guide) {
            $urls[] = ['url' => PublicSeo::ORIGIN.$guide->path, 'modified' => $guide->updated_at?->toAtomString()];
        }

        return response()->view('public.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        // Use the same source that the build publishes for Apache.
        $text = file_get_contents(base_path('web/public/robots.txt'));

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function llms(): Response
    {
        return response("# Mahadum360\n\nNigerian language learning for families and schools. Five-minute Igbo, Yorùbá, Hausa and English lessons with native voices and stories. Level 0 is free; paid plans unlock Level 1 and above.\n\n## Pages\n- [Languages](https://mahadum360.com/languages)\n- [Igbo](https://mahadum360.com/learn-igbo)\n- [Yorùbá](https://mahadum360.com/learn-yoruba)\n- [Hausa](https://mahadum360.com/learn-hausa)\n- [Guides](https://mahadum360.com/guides)\n- [Pricing](https://mahadum360.com/pricing)\n- [Contact](https://mahadum360.com/contact)\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return array<string, string> */
    private function languagesList(): array
    {
        return ['hausa' => 'Hausa', 'igbo' => 'Igbo', 'yoruba' => 'Yorùbá'];
    }

    /** @param array<string, mixed> $data
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array{title: string, description: string}|null  $meta
     */
    private function page(string $path, string $view, array $data, array $schema = [], ?array $meta = null): Response
    {
        $meta ??= $this->seo->pages()[$path];
        $chromeFile = config('seo.prerender').'/chrome.json';
        abort_unless(is_file($chromeFile), 503, 'Website assets are being prepared.');
        $chrome = json_decode(file_get_contents($chromeFile), true, flags: JSON_THROW_ON_ERROR);

        return response()->view($view, $data + ['head' => $this->seo->head($path, $meta, $schema), 'chrome' => $chrome], 200, ['Cache-Control' => 'no-cache', 'Content-Type' => 'text/html; charset=UTF-8']);
    }
}
