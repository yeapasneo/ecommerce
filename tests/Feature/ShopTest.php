<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep one guest session across requests, like a browser does.
        $this->withCookie(config('session.cookie'), Str::random(40));
        $parent = Category::create(['name' => 'Component', 'slug' => 'component']);
        $this->category = Category::create(['name' => 'RAM', 'slug' => 'ram', 'parent_id' => $parent->id, 'filter_attributes' => ['Capacity']]);
    }

    protected function product(array $attrs = [], string $capacity = '8GB'): Product
    {
        static $n = 0;
        $n++;
        $p = Product::create($attrs + [
            'category_id' => $this->category->id,
            'brand_id' => Brand::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme'])->id,
            'name' => "Test RAM {$n}",
            'slug' => "test-ram-{$n}",
            'sku' => "RAM-{$n}",
            'price' => 5000,
            'stock' => 10,
            'stock_status' => 'in_stock',
        ]);
        $p->specs()->create(['group' => 'Basic', 'name' => 'Capacity', 'value' => $capacity]);

        return $p;
    }

    protected function checkoutPayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Test', 'phone' => '01700000001', 'address' => 'Road 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'agree' => true,
        ];
    }

    public function test_home_and_category_pages_render(): void
    {
        $this->product();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home'));
        $this->get('/category/component')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Listing')
            ->has('products.data', 1));
        $this->get('/category/ram')->assertInertia(fn (Assert $page) => $page->where('filters.facets.0.name', 'Capacity'));
    }

    public function test_category_filters_by_spec_and_price(): void
    {
        $this->product(['price' => 3000], '8GB');
        $this->product(['price' => 9000], '16GB');
        $this->get('/category/ram?spec[Capacity][]=16GB')->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.price', 9000));
        $this->get('/category/ram?max_price=4000')->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.price', 3000));
    }

    public function test_search_finds_products(): void
    {
        $p = $this->product();
        $this->get('/search?q='.urlencode($p->name))->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
        $this->getJson('/search/suggest?q=Test')->assertOk()->assertJsonCount(1);
    }

    public function test_guest_checkout_creates_order_and_decrements_stock(): void
    {
        $p = $this->product(['stock' => 3]);
        $this->post('/cart', ['product_id' => $p->id, 'quantity' => 2])->assertRedirect();
        $this->post('/checkout', $this->checkoutPayload())->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals(10000, $order->subtotal);
        $this->assertEquals(60, $order->delivery_charge);
        $this->assertEquals(10060, $order->total);
        $this->assertEquals(1, $p->fresh()->stock);
        $this->assertCount(1, $order->items);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.items', 0));
    }

    public function test_checkout_rejects_quantity_above_stock(): void
    {
        $p = $this->product(['stock' => 1]);
        $this->post('/cart', ['product_id' => $p->id, 'quantity' => 2]);
        $this->post('/checkout', $this->checkoutPayload())->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
    }

    public function test_out_of_stock_product_cannot_be_added(): void
    {
        $p = $this->product(['stock_status' => 'out_of_stock', 'stock' => 0]);
        $this->post('/cart', ['product_id' => $p->id])->assertSessionHas('error');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.items', 0));
    }

    public function test_coupon_applies_discount_and_counts_usage(): void
    {
        $p = $this->product(['price' => 20000]);
        Coupon::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'max_discount' => 1500]);
        $this->post('/cart', ['product_id' => $p->id]);
        $this->post('/cart/coupon', ['code' => 'save10'])->assertSessionHasNoErrors();
        $this->post('/checkout', $this->checkoutPayload());

        $order = Order::first();
        $this->assertEquals(1500, $order->discount);
        $this->assertEquals(20000 - 1500 + 60, $order->total);
        $this->assertSame(1, Coupon::first()->used);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $p = $this->product();
        Coupon::create(['code' => 'OLD', 'type' => 'fixed', 'value' => 100, 'expires_at' => now()->subDay()]);
        $this->post('/cart', ['product_id' => $p->id]);
        $this->post('/cart/coupon', ['code' => 'OLD'])->assertSessionHasErrors('code');
    }

    public function test_guest_cart_is_merged_on_login(): void
    {
        $p = $this->product();
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/cart', ['product_id' => $p->id]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'product_id' => $p->id]);
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        $p = $this->product(['stock' => 5]);
        $this->actingAs($user)->post('/cart', ['product_id' => $p->id, 'quantity' => 2]);
        $this->actingAs($user)->post('/checkout', $this->checkoutPayload());
        $order = Order::first();
        $this->assertEquals(3, $p->fresh()->stock);

        $this->actingAs($user)->post("/account/orders/{$order->order_number}/cancel")->assertSessionHas('success');
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(5, $p->fresh()->stock);
    }

    public function test_users_cannot_see_other_customers_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $p = $this->product();
        $this->actingAs($owner)->post('/cart', ['product_id' => $p->id]);
        $this->actingAs($owner)->post('/checkout', $this->checkoutPayload());
        $number = Order::first()->order_number;

        $this->actingAs($other)->get("/account/orders/{$number}")->assertNotFound();
    }

    public function test_order_tracking_requires_matching_phone(): void
    {
        $p = $this->product();
        $this->post('/cart', ['product_id' => $p->id]);
        $this->post('/checkout', $this->checkoutPayload());
        $number = Order::first()->order_number;

        $this->get("/track-order?order_number={$number}&phone=01700000001")->assertInertia(fn (Assert $page) => $page->where('order.order_number', $number));
        $this->get("/track-order?order_number={$number}&phone=000")->assertInertia(fn (Assert $page) => $page->where('order', null));
    }

    public function test_compare_and_wishlist(): void
    {
        $user = User::factory()->create();
        $a = $this->product();
        $b = $this->product();
        $this->post('/compare', ['product_id' => $a->id]);
        $this->post('/compare', ['product_id' => $b->id]);
        $this->get('/compare')->assertInertia(fn (Assert $page) => $page->has('products', 2)->has('rows', 1));

        $this->actingAs($user)->post('/account/wishlist', ['product_id' => $a->id]);
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $a->id]);
    }

    public function test_reviews_need_approval_before_showing(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $this->actingAs($user)->post("/product/{$p->id}/review", ['rating' => 5, 'comment' => 'Great'])->assertSessionHas('success');
        $this->get("/product/{$p->slug}")->assertInertia(fn (Assert $page) => $page->has('reviews', 0));
        $p->reviews()->update(['is_approved' => true]);
        $this->get("/product/{$p->slug}")->assertInertia(fn (Assert $page) => $page->has('reviews', 1)->where('rating.average', 5));
    }
}
