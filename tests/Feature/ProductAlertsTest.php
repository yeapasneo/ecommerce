<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'GPU', 'slug' => 'gpu'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Acme RTX 5060', 'slug' => 'acme-rtx-5060', 'sku' => 'G1', 'price' => 50000, 'stock' => 0, 'stock_status' => 'out_of_stock',
        ]);
        Setting::put(['sms_provider' => 'bulksmsbd', 'sms_api_key' => 'KEY', 'sms_sender_id' => 'Shop']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        Mail::fake();
    }

    public function test_back_in_stock_alert_is_sent_once(): void
    {
        $this->post("/product/{$this->product->id}/notify", ['phone' => '123'])->assertSessionHasErrors('phone');
        $this->post("/product/{$this->product->id}/notify", [])->assertSessionHasErrors('phone');
        $this->post("/product/{$this->product->id}/notify", ['phone' => '01712345678'])->assertSessionHas('success');
        $this->post("/product/{$this->product->id}/notify", ['phone' => '+8801712345678'])->assertSessionHas('success'); // same person
        $this->post("/product/{$this->product->id}/notify", ['email' => 'karim@example.com'])->assertSessionHas('success');
        $this->assertSame(2, StockAlert::count());

        $this->actingAs($this->owner)->get('/admin/inventory?tab=waiting')->assertInertia(fn ($p) => $p->where('waiting.0.people', 2));

        // Stock arrives (e.g. a purchase order or a manual change).
        $this->product->update(['stock' => 3, 'stock_status' => 'in_stock']);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Acme RTX 5060 is back in stock') && str_contains($r['message'], '/product/acme-rtx-5060'));
        $this->assertSame(2, NotificationLog::where('event', 'back_in_stock')->where('status', 'sent')->count());
        $this->assertSame(0, StockAlert::whereNull('notified_at')->count());

        // Selling out and coming back again doesn't re-send old alerts.
        $this->product->update(['stock' => 0, 'stock_status' => 'out_of_stock']);
        $this->product->update(['stock' => 2, 'stock_status' => 'in_stock']);
        $this->assertSame(2, NotificationLog::where('event', 'back_in_stock')->count());
    }

    public function test_variant_back_in_stock(): void
    {
        $this->product->update(['has_variants' => true]);
        $v = $this->product->variants()->create(['name' => '8GB', 'options' => ['Memory' => '8GB'], 'price' => 50000, 'stock' => 0, 'is_active' => true]);
        $this->post("/product/{$this->product->id}/notify", ['phone' => '01712345678', 'variant_id' => $v->id])->assertSessionHas('success');
        $v->update(['stock' => 1]);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Acme RTX 5060 (8GB) is back in stock'));
    }

    public function test_price_drop_tells_wishlisters_once_a_week(): void
    {
        $fan = User::factory()->create(['name' => 'Karim Ahmed', 'phone' => '01812345678']);
        \DB::table('wishlists')->insert(['user_id' => $fan->id, 'product_id' => $this->product->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->product->update(['price' => 49800]); // under 2%: ignored
        $this->assertSame(0, NotificationLog::where('event', 'price_drop')->count());

        $this->product->update(['price' => 45000]);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Hi Karim, Acme RTX 5060 on your wishlist is now Tk 45,000 (was Tk 49,800)'));
        $this->assertEqualsCanonicalizing(['sms', 'email'], NotificationLog::where('event', 'price_drop')->pluck('channel')->all());

        // A second drop the same week isn't sent again.
        $this->product->update(['price' => 40000]);
        $this->assertSame(2, NotificationLog::where('event', 'price_drop')->count());
        $this->travel(8)->days();
        $this->product->update(['price' => 38000]);
        $this->assertSame(4, NotificationLog::where('event', 'price_drop')->count());
    }

    public function test_alerts_can_be_turned_off(): void
    {
        Setting::put(['back_in_stock_enabled' => '0']);
        $this->post("/product/{$this->product->id}/notify", ['phone' => '01712345678'])->assertNotFound();
    }
}
