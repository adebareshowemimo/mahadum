<?php

namespace App\Services;

use App\Models\PublicDocument;
use Illuminate\Database\Eloquent\Collection;

class PublicSeo
{
    /** @var Collection<int, PublicDocument>|null */
    private ?Collection $documents = null;

    /** @return Collection<int, PublicDocument> */
    private function documents(): Collection
    {
        return $this->documents ??= PublicDocument::query()->where('published', true)->where('kind', '!=', 'guide')->get();
    }

    public const ORIGIN = 'https://mahadum360.com';

    /** @return array<string, array{title: string, description: string}> */
    public function pages(): array
    {
        $pages = json_decode(file_get_contents(base_path('web/src/lib/seo/pages.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($this->documents() as $page) {
            $pages[$page->path] = ['title' => $page->title, 'description' => $page->description];
        }

        return $pages;
    }

    /** @return array<string, mixed> */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org', '@type' => 'Organization', '@id' => self::ORIGIN.'/#organization',
            'name' => 'Mahadum360', 'url' => self::ORIGIN.'/',
            'logo' => self::ORIGIN.'/images/m360_logo_official.png',
            'description' => 'Igbo, Yorùbá, Hausa and English lessons for families and schools. Start free with Level 0.',
            'sameAs' => ['https://www.youtube.com/@mahadum360', 'https://www.instagram.com/mahadum360/', 'https://www.facebook.com/Mahadum360/'],
            'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'Partnerships@Mahadum360.com', 'telephone' => '+2349028111111'],
        ];
    }

    /** @param array{title: string, description: string} $meta
     * @param  array<int, array<string, mixed>>  $schema
     */
    public function head(string $path, array $meta, array $schema = [], bool $index = true): string
    {
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = $escape(self::ORIGIN.$path);
        $title = $escape($meta['title']);
        $description = $escape($meta['description']);
        $image = self::ORIGIN.'/images/og-cover.jpg';
        $head = '<title>'.$title.'</title><meta name="description" content="'.$description.'">'
            .'<meta name="robots" content="'.($index ? 'index,follow' : 'noindex,follow').'">'
            .'<link rel="canonical" href="'.$url.'">';
        foreach (['google-site-verification' => config('seo.google_verification'), 'msvalidate.01' => config('seo.bing_verification')] as $name => $value) {
            if ($value) {
                $head .= '<meta name="'.$name.'" content="'.$escape($value).'">';
            }
        }
        foreach (['type' => 'website', 'title' => $title, 'description' => $description, 'url' => $url, 'image' => $image, 'image:width' => '1200', 'image:height' => '630', 'image:alt' => 'Mahadum360 Nigerian language learning for families'] as $key => $value) {
            $head .= '<meta property="og:'.$key.'" content="'.$value.'">';
        }
        foreach (['card' => 'summary_large_image', 'title' => $title, 'description' => $description, 'image' => $image] as $key => $value) {
            $head .= '<meta name="twitter:'.$key.'" content="'.$value.'">';
        }
        $schema = array_merge([$this->organization(), ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Mahadum360', 'url' => self::ORIGIN.'/']], $schema);
        foreach ($schema as $item) {
            $head .= '<script type="application/ld+json">'.json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR).'</script>';
        }
        $head .= '<script id="public-page-metadata" type="application/json">'.json_encode($this->pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR).'</script>';
        $copies = $this->documents()->where('kind', 'page')->mapWithKeys(fn ($page) => [$page->path => ['heading' => $page->heading, 'intro' => $page->intro]])->all();
        $head .= '<script id="public-page-copy" type="application/json">'.json_encode($copies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR).'</script>';

        return $head;
    }

    /** @param array{title: string, description: string} $meta */
    public function decorate(string $html, string $path, array $meta, bool $index = true): string
    {
        $copy = $this->documents()->where('kind', 'page')->firstWhere('path', $path);
        if ($copy?->heading) {
            $html = preg_replace_callback('~(<h1\\b[^>]*>).*?(</h1>)~s', fn ($match) => $match[1].htmlspecialchars($copy->heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$match[2], $html, 1);
        }
        if ($copy?->intro) {
            $html = preg_replace_callback('~(<p\\b(?=[^>]*data-public-intro)[^>]*>).*?(</p>)~s', fn ($match) => $match[1].htmlspecialchars($copy->intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$match[2], $html, 1);
        }
        $html = preg_replace('~<title>.*?</title>|<meta\b[^>]*(?:name="(?:description|robots|twitter:[^"]+)"|property="og:[^"]+")[^>]*>|<link\b[^>]*rel="canonical"[^>]*>|<script\b[^>]*type="application/ld\+json"[^>]*>.*?</script>~si', '', $html);

        return str_replace('</head>', $this->head($path, $meta, index: $index).'</head>', $html);
    }
}
