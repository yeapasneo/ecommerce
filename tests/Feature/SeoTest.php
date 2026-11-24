<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'Processor', 'slug' => 'processor']);
        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => Brand::create(['name' => 'AMD', 'slug' => 'amd'])->id,
            'name' => 'AMD Ryzen 5 7600 Processor',
            'slug' => 'amd-ryzen-5-7600',
            'sku' => 'R5-7600',
            'price' => 21500,
            'stock' => 5,
            'stock_status' => 'in_stock',
            'key_features' => ['6 Cores, 12 Threads', 'Up to 5.1GHz'],
        ]);
    }

    public function test_product_page_html_has_meta_tags_and_structured_data(): void
    {
        $html = $this->get('/product/amd-ryzen-5-7600')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>AMD Ryzen 5 7600 Processor Price in Bangladesh | TechNest</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/product/amd-ryzen-5-7600').'"', $html);
        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('property="product:price:amount" content="21500.00"', $html);
        $this->assertStringContainsString('name="robots" content="index, follow', $html);

        preg_match_all('#<script inertia="ld-\d+" type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $schemas = collect($m[1])->map(fn ($j) => json_decode($j, true))->keyBy('@type');
        $this->assertSame('R5-7600', $schemas['Product']['sku']);
        $this->assertSame('21500.00', $schemas['Product']['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schemas['Product']['offers']['availability']);
        $this->assertSame('AMD', $schemas['Product']['brand']['name']);
        $this->assertCount(3, $schemas['BreadcrumbList']['itemListElement']);
    }

    public function test_custom_meta_fields_are_used(): void
    {
        $this->product->update(['meta_title' => 'Ryzen 5 7600 Best Price', 'meta_description' => 'Custom description for search.', 'meta_keywords' => 'ryzen, amd']);
        $html = $this->get('/product/amd-ryzen-5-7600')->getContent();

        $this->assertStringContainsString('<title inertia>Ryzen 5 7600 Best Price | TechNest</title>', $html);
        $this->assertStringContainsString('name="description" content="Custom description for search."', $html);
        $this->assertStringContainsString('name="keywords" content="ryzen, amd"', $html);
    }

    public function test_json_ld_cannot_break_out_of_its_script_tag(): void
    {
        $this->product->update(['name' => 'Evil </script><script>alert(1)</script>']);
        $html = $this->get('/product/amd-ryzen-5-7600')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_filtered_listings_and_private_pages_are_noindex(): void
    {
        $this->get('/category/processor')->assertSee('content="index, follow', false);
        $this->get('/category/processor?page=2')->assertSee('href="'.url('/category/processor').'?page=2"', false);

        $sorted = $this->get('/category/processor?sort=price_asc')->getContent();
        $this->assertStringContainsString('content="noindex, follow"', $sorted);
        $this->assertStringContainsString('rel="canonical" href="'.url('/category/processor').'"', $sorted);

        $this->get('/search?q=ryzen')->assertSee('content="noindex, follow"', false);
        $this->get('/cart')->assertSee('content="noindex, follow"', false);
    }

    public function test_robots_and_sitemaps(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/sitemap-products-1.xml'), false);

        $this->get('/sitemap-products-1.xml')->assertOk()
            ->assertSee('<loc>'.url('/product/amd-ryzen-5-7600').'</loc>', false);
        $this->get('/sitemap-categories.xml')->assertOk()->assertSee(url('/category/processor'), false);
        $this->get('/sitemap-products-9.xml')->assertNotFound();
    }

    public function test_noindex_switch_hides_the_whole_site(): void
    {
        Setting::put(['seo_noindex' => '1']);

        $this->get('/robots.txt')->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
        $this->get('/product/amd-ryzen-5-7600')->assertSee('content="noindex, nofollow"', false);
    }

    public function test_tracking_scripts_render_on_the_storefront_only(): void
    {
        Setting::put(['gtm_container_id' => 'GTM-ABC1234', 'ga_measurement_id' => 'G-XYZ98765', 'facebook_pixel_id' => '123456789012345']);

        $this->get('/')->assertSee('googletagmanager.com/gtm.js', false)
            ->assertSee('"GTM-ABC1234"', false)
            ->assertSee('gtag/js?id=G-XYZ98765', false)
            ->assertSee("fbq('init',\"123456789012345\")", false);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('googletagmanager', false)->assertDontSee('fbevents', false);
    }

    public function test_tracking_ids_must_be_well_formed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = Setting::all_settings();

        $this->actingAs($admin)->put('/admin/settings', ['gtm_container_id' => "GTM-1');alert(1);//"] + $payload)
            ->assertSessionHasErrors('gtm_container_id');
        $this->actingAs($admin)->put('/admin/settings', ['facebook_pixel_id' => '<script>'] + $payload)
            ->assertSessionHasErrors('facebook_pixel_id');
    }

    public function test_gateway_secrets_are_encrypted_and_never_shared(): void
    {
        Setting::put(['bkash_app_secret' => 'top-secret-value', 'sslcommerz_store_password' => 'store-pass']);

        $stored = DB::table('settings')->where('key', 'bkash_app_secret')->value('value');
        $this->assertNotSame('top-secret-value', $stored);
        $this->assertSame('top-secret-value', Setting::get('bkash_app_secret'));

        $page = $this->get('/')->getContent();
        $this->assertStringNotContainsString('top-secret-value', $page);
        $this->assertStringNotContainsString('bkash_app_key', $page);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertDontSee('top-secret-value')
            ->assertDontSee('store-pass');
    }

    public function test_blank_secret_keeps_the_saved_value(): void
    {
        Setting::put(['bkash_app_secret' => 'keep-me']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/settings', array_merge(Setting::all_settings(), ['bkash_app_secret' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('keep-me', Setting::get('bkash_app_secret'));
    }

    public function test_admin_saves_product_seo_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $p = $this->product;

        $this->actingAs($admin)->put("/admin/products/{$p->id}", [
            'name' => $p->name, 'sku' => $p->sku, 'category_id' => $p->category_id, 'price' => 21500, 'stock' => 5,
            'stock_status' => 'in_stock', 'is_active' => true, 'is_featured' => false,
            'slug' => 'ryzen-5-7600', 'meta_title' => 'Ryzen 5 7600', 'meta_description' => 'Desc', 'meta_keywords' => 'ryzen, amd',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(['ryzen-5-7600', 'Ryzen 5 7600', 'ryzen, amd'], [$p->slug, $p->meta_title, $p->meta_keywords]);
    }
}
