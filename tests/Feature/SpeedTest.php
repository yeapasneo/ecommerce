<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpeedTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Mouse', 'slug' => 'mouse'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Acme Mouse', 'slug' => 'acme-mouse', 'sku' => 'M1', 'price' => 1000, 'stock' => 5, 'stock_status' => 'in_stock',
        ]);
        Setting::put(['page_cache_enabled' => '1']);
    }

    protected function guest()
    {
        return $this->withCookie(config('session.cookie'), Str::random(40));
    }

    public function test_guest_pages_are_cached_and_refreshed_on_change(): void
    {
        $this->guest()->get('/product/acme-mouse')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $hit = $this->guest()->get('/product/acme-mouse')->assertOk()->assertHeader('X-Page-Cache', 'HIT');
        $this->assertStringContainsString('Acme Mouse', $hit->getContent());
        $this->assertSame(2, $this->product->fresh()->views); // views still counted on cache hits

        $this->product->update(['name' => 'Acme Silent Mouse']);
        $fresh = $this->guest()->get('/product/acme-mouse')->assertHeader('X-Page-Cache', 'MISS');
        $this->assertStringContainsString('Acme Silent Mouse', $fresh->getContent());
    }

    public function test_personal_pages_are_never_cached(): void
    {
        $this->guest()->get('/category/mouse');
        // Signed in.
        $this->actingAs(User::factory()->create())->get('/category/mouse')->assertHeaderMissing('X-Page-Cache');
        auth()->logout();
        // Something in the cart.
        $session = Str::random(40);
        $this->withCookie(config('session.cookie'), $session);
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);
        $page = $this->get('/category/mouse')->assertHeaderMissing('X-Page-Cache');
        $this->assertStringContainsString('"cartCount":1', $page->getContent());
    }

    public function test_cache_can_be_turned_off(): void
    {
        Setting::put(['page_cache_enabled' => '0']);
        $this->guest()->get('/')->assertHeaderMissing('X-Page-Cache');
        $this->guest()->get('/')->assertHeaderMissing('X-Page-Cache');
    }

    public function test_installable_app_manifest(): void
    {
        $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')->assertJsonPath('icons.2.purpose', 'maskable')->assertJsonPath('name', Setting::get('site_name'));
        $this->guest()->get('/')->assertSee('rel="manifest"', false);
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }
}
