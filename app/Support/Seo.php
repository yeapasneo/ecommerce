<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Per-request SEO data. Controllers fill it with seo()->title(...)->description(...);
 * the same tag list is printed in the first HTML response (for crawlers) and shared
 * with Inertia so client-side navigation updates the head with identical tags.
 */
class Seo
{
    protected ?string $title = null;

    protected ?string $description = null;

    protected ?string $keywords = null;

    protected ?string $canonical = null;

    protected ?string $image = null;

    protected string $type = 'website';

    protected ?string $robots = null;

    protected array $meta = [];

    protected array $schemas = [];

    /** Paths that should never be indexed (private or duplicate content). */
    public const NOINDEX_PATHS = [
        'admin*', 'account*', 'cart*', 'checkout*', 'compare*', 'search*', 'track-order*',
        'login', 'register', 'forgot-password', 'reset-password*', 'pc-builder/choose*', 'payment*',
    ];

    public function title(?string $title): static
    {
        $this->title = $title ? trim($title) : null;

        return $this;
    }

    public function description(?string $description): static
    {
        if ($description) {
            $this->description = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 160, '…');
        }

        return $this;
    }

    public function keywords(?string $keywords): static
    {
        $this->keywords = $keywords ?: $this->keywords;

        return $this;
    }

    public function canonical(?string $url): static
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url): static
    {
        if ($url) {
            $this->image = Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
        }

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(): static
    {
        $this->robots = 'noindex, follow';

        return $this;
    }

    /** Extra <meta property=...> tags, e.g. product:price:amount. */
    public function meta(string $property, ?string $content): static
    {
        if ($content !== null && $content !== '') {
            $this->meta[$property] = $content;
        }

        return $this;
    }

    public function schema(array $data): static
    {
        $this->schemas[] = ['@context' => 'https://schema.org'] + $data;

        return $this;
    }

    public function breadcrumbs(array $crumbs): static
    {
        $items = [['name' => 'Home', 'url' => url('/')], ...$crumbs];

        return $this->schema([
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($c, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['url'] ?? null,
            ]))->all(),
        ]);
    }

    public function fullTitle(): string
    {
        $site = Setting::get('site_name', config('app.name'));
        if ($this->title) {
            return Str::contains($this->title, $site) ? $this->title : "{$this->title} | {$site}";
        }

        return Setting::get('seo_title') ?: trim($site.' - '.Setting::get('tagline'), ' -');
    }

    public function robots(): string
    {
        if (Setting::enabled('seo_noindex')) {
            return 'noindex, nofollow';
        }
        if ($this->robots) {
            return $this->robots;
        }
        $request = request();
        if ($request->is(...self::NOINDEX_PATHS)) {
            return 'noindex, follow';
        }
        // Filtered or sorted listings duplicate the plain category page.
        if (count(array_diff(array_keys($request->query()), ['page'])) > 0) {
            return 'noindex, follow';
        }

        return 'index, follow, max-image-preview:large';
    }

    public function canonicalUrl(): string
    {
        if ($this->canonical) {
            return $this->canonical;
        }
        $request = request();
        $page = (int) $request->query('page', 1);

        return $request->url().($page > 1 ? '?page='.$page : '');
    }

    /** Head tags as [tag, key, attributes, content] for Blade and React to render the same way. */
    public function tags(): array
    {
        $title = $this->fullTitle();
        $description = $this->description ?: Setting::get('seo_description');
        $image = $this->image ?: (Setting::get('seo_og_image') ? url(media_url(Setting::get('seo_og_image'))) : null);
        $site = Setting::get('site_name', config('app.name'));
        $url = $this->canonicalUrl();

        $tags = [
            ['meta', 'description', ['name' => 'description', 'content' => $description]],
            ['meta', 'keywords', ['name' => 'keywords', 'content' => $this->keywords ?: Setting::get('seo_keywords')]],
            ['meta', 'robots', ['name' => 'robots', 'content' => $this->robots()]],
            ['link', 'canonical', ['rel' => 'canonical', 'href' => $url]],
            ['meta', 'og:site_name', ['property' => 'og:site_name', 'content' => $site]],
            ['meta', 'og:type', ['property' => 'og:type', 'content' => $this->type]],
            ['meta', 'og:title', ['property' => 'og:title', 'content' => $title]],
            ['meta', 'og:description', ['property' => 'og:description', 'content' => $description]],
            ['meta', 'og:url', ['property' => 'og:url', 'content' => $url]],
            ['meta', 'og:locale', ['property' => 'og:locale', 'content' => 'en_US']],
            ['meta', 'og:image', ['property' => 'og:image', 'content' => $image]],
            ['meta', 'twitter:card', ['name' => 'twitter:card', 'content' => $image ? 'summary_large_image' : 'summary']],
            ['meta', 'twitter:title', ['name' => 'twitter:title', 'content' => $title]],
            ['meta', 'twitter:description', ['name' => 'twitter:description', 'content' => $description]],
            ['meta', 'twitter:image', ['name' => 'twitter:image', 'content' => $image]],
            ['meta', 'google-site-verification', ['name' => 'google-site-verification', 'content' => Setting::get('google_site_verification')]],
            ['meta', 'msvalidate.01', ['name' => 'msvalidate.01', 'content' => Setting::get('bing_site_verification')]],
            ['meta', 'facebook-domain-verification', ['name' => 'facebook-domain-verification', 'content' => Setting::get('facebook_domain_verification')]],
        ];
        foreach ($this->meta as $property => $content) {
            $tags[] = ['meta', $property, ['property' => $property, 'content' => $content]];
        }

        $tags = array_values(array_filter($tags, fn ($t) => ($t[2]['content'] ?? $t[2]['href'] ?? '') !== ''));
        $tags = array_map(fn ($t) => ['tag' => $t[0], 'key' => $t[1], 'attrs' => $t[2]], $tags);

        foreach ($this->schemas as $i => $schema) {
            $tags[] = [
                'tag' => 'script',
                'key' => 'ld-'.$i,
                'attrs' => ['type' => 'application/ld+json'],
                // JSON_HEX_TAG keeps "</script>" in product names from closing the tag.
                'html' => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            ];
        }

        return $tags;
    }

    public function toArray(): array
    {
        return ['title' => $this->fullTitle(), 'explicit' => $this->title !== null, 'tags' => $this->tags()];
    }
}
