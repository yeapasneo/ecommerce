<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\FlashSales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * Product catalogue feed in Google's RSS 2.0 format, which Google Merchant Center
 * (Shopping) and Meta Commerce Manager (Facebook / Instagram shops) both read.
 * Products with variants become one item per variant, grouped by item_group_id.
 */
class ProductFeed
{
    public static function path(): string
    {
        return storage_path('app/feeds/products.xml');
    }

    /** Write the feed file. Returns how many items it holds. */
    public function generate(): int
    {
        Product::$flashPrices = false; // the feed shows base and sale prices itself
        File::ensureDirectoryExists(dirname(self::path()));
        $tmp = self::path().'.tmp';
        $site = (string) Setting::get('site_name');
        $paths = Category::all(['id', 'parent_id', 'name'])->keyBy('id');
        $typeOf = function (?int $id) use ($paths): string {
            $trail = [];
            while ($id && ($c = $paths[$id] ?? null) && count($trail) < 6) {
                array_unshift($trail, $c->name);
                $id = $c->parent_id;
            }

            return implode(' > ', $trail);
        };
        $flash = app(FlashSales::class)->active();

        $x = new XMLWriter;
        $x->openUri($tmp);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('rss');
        $x->writeAttribute('version', '2.0');
        $x->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $x->startElement('channel');
        $x->writeElement('title', $site);
        $x->writeElement('link', url('/'));
        $x->writeElement('description', (string) (Setting::get('seo_description') ?: Setting::get('tagline') ?: $site));

        $count = 0;
        Product::query()->where('is_active', true)->where('price', '>', 0)->whereNotNull('image')
            ->with(['brand:id,name', 'images:id,product_id,path,sort_order', 'variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('id')->chunk(500, function ($products) use ($x, $typeOf, $flash, &$count) {
                foreach ($products as $p) {
                    $base = [
                        'title' => $p->name,
                        'description' => $this->description($p),
                        'link' => url("/product/{$p->slug}"),
                        'image' => $this->absolute($p->image_url),
                        'images' => $p->images->sortBy('sort_order')->take(10)->map(fn ($i) => $this->absolute($i->url))->all(),
                        'brand' => $p->brand?->name,
                        'type' => $typeOf($p->category_id),
                        'mpn' => $p->model ?: $p->sku,
                    ];
                    if ($p->has_variants && $p->variants->isNotEmpty()) {
                        foreach ($p->variants as $v) {
                            $v->setRelation('product', $p);
                            $this->item($x, array_merge($base, [
                                'id' => $v->sku ?: "{$p->sku}-{$v->id}", 'group' => $p->sku,
                                'title' => "{$p->name} - {$v->name}", 'link' => url("/product/{$p->slug}?variant={$v->id}"),
                                'image' => $v->image_url ? $this->absolute($v->image_url) : $base['image'],
                                'availability' => $v->stock > 0 ? 'in_stock' : ($p->stock_status === 'pre_order' ? 'preorder' : 'out_of_stock'),
                                'options' => $v->options ?? [],
                            ]), $v->basePrice(), (float) $v->regular_price, isset($flash[$p->id]) && $p->basePrice() > 0 ? round($v->basePrice() * $flash[$p->id]['price'] / $p->basePrice()) : null, $flash[$p->id] ?? null);
                            $count++;
                        }

                        continue;
                    }
                    $this->item($x, $base + [
                        'id' => $p->sku ?: (string) $p->id,
                        'availability' => match ($p->stock_status) {
                            'in_stock' => $p->stock > 0 ? 'in_stock' : 'out_of_stock',
                            'pre_order' => 'preorder', 'up_coming' => 'backorder', default => 'out_of_stock',
                        },
                    ], $p->basePrice(), (float) $p->regular_price, $flash[$p->id]['price'] ?? null, $flash[$p->id] ?? null);
                    $count++;
                }
            });

        $x->endElement();
        $x->endElement();
        $x->endDocument();
        $x->flush();
        rename($tmp, self::path());
        Setting::put(['feed_generated_at' => now()->toIso8601String(), 'feed_count' => (string) $count]);

        return $count;
    }

    protected function item(XMLWriter $x, array $d, float $price, float $regular, ?float $flashPrice, ?array $flash): void
    {
        // Google/Meta want the normal price in g:price and the discounted one in g:sale_price.
        $list = max($price, $regular);
        $sale = $flashPrice && $flashPrice < $list ? $flashPrice : ($regular > $price ? $price : null);

        $x->startElement('item');
        $x->writeElement('g:id', Str::limit($d['id'], 50, ''));
        if (! empty($d['group'])) {
            $x->writeElement('g:item_group_id', $d['group']);
        }
        $x->writeElement('g:title', Str::limit($d['title'], 150, ''));
        $x->writeElement('g:description', $d['description']);
        $x->writeElement('g:link', $d['link']);
        $x->writeElement('g:image_link', $d['image']);
        foreach (array_slice($d['images'], 0, 10) as $img) {
            $x->writeElement('g:additional_image_link', $img);
        }
        $x->writeElement('g:availability', $d['availability']);
        $x->writeElement('g:price', number_format($list, 2, '.', '').' BDT');
        if ($sale) {
            $x->writeElement('g:sale_price', number_format($sale, 2, '.', '').' BDT');
            if ($flashPrice && $flash) {
                $x->writeElement('g:sale_price_effective_date', Carbon::parse($flash['starts_at'])->toIso8601String().'/'.Carbon::parse($flash['ends_at'])->toIso8601String());
            }
        }
        $x->writeElement('g:condition', 'new');
        if ($d['brand']) {
            $x->writeElement('g:brand', $d['brand']);
        }
        $x->writeElement('g:mpn', Str::limit((string) $d['mpn'], 70, ''));
        $x->writeElement('g:identifier_exists', 'no');
        if ($d['type']) {
            $x->writeElement('g:product_type', $d['type']);
        }
        foreach ($d['options'] ?? [] as $name => $value) {
            $key = match (strtolower((string) $name)) {
                'colour', 'color' => 'g:color', 'size' => 'g:size', 'material' => 'g:material', 'pattern' => 'g:pattern', default => null,
            };
            if ($key) {
                $x->writeElement($key, (string) $value);
            }
        }
        $x->endElement();
    }

    protected function description(Product $p): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ["\n", "\n", '; '], (string) $p->description)))));
        if (mb_strlen($text) < 30 && $p->key_features) {
            $text = trim($text.' '.implode('. ', $p->key_features));
        }

        return Str::limit($text !== '' ? $text : $p->name, 4900, '…');
    }

    protected function absolute(?string $url): string
    {
        return $url && ! Str::startsWith($url, ['http://', 'https://']) ? url($url) : (string) $url;
    }
}
