<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->category = Category::create(['name' => 'Phones', 'slug' => 'phones']);
    }

    protected function phone(): Product
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'name' => 'Galaxy S99', 'sku' => 'S99', 'category_id' => $this->category->id, 'brand_id' => Brand::create(['name' => 'Samsung', 'slug' => 'samsung'])->id,
            'price' => 0, 'stock' => 0, 'stock_status' => 'in_stock', 'is_active' => true, 'is_featured' => false,
            'variants' => [
                ['options' => ['Storage' => '128GB', 'Colour' => 'Black'], 'sku' => 'S99-128-BK', 'price' => 90000, 'stock' => 3, 'is_active' => 1],
                ['options' => ['Storage' => '256GB', 'Colour' => 'Black'], 'sku' => 'S99-256-BK', 'price' => 100000, 'regular_price' => 110000, 'stock' => 0, 'is_active' => 1],
                ['options' => ['Storage' => '256GB', 'Colour' => 'Blue'], 'sku' => 'S99-256-BL', 'price' => 101000, 'stock' => 2, 'is_active' => 1],
            ],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        return Product::where('sku', 'S99')->first();
    }

    public function test_variants_set_the_product_price_and_stock(): void
    {
        $p = $this->phone();
        $this->assertSame([true, 90000.0, 5, 'in_stock', 3], [$p->has_variants, $p->price, $p->stock, $p->stock_status, $p->variants()->count()]);

        $this->get('/product/galaxy-s99')->assertInertia(fn (Assert $page) => $page
            ->has('variants', 3)
            ->where('variants.1.name', '256GB / Black')
            ->where('variants.1.available', false)
            ->where('variants.2.available', true));
    }

    public function test_an_option_must_be_chosen_and_variant_stock_is_used(): void
    {
        $p = $this->phone();
        $blue = ProductVariant::where('sku', 'S99-256-BL')->first();

        $this->post('/cart', ['product_id' => $p->id])->assertRedirect('/product/galaxy-s99');
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => ProductVariant::where('sku', 'S99-256-BK')->value('id')])->assertSessionHas('error');
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $blue->id, 'quantity' => 2])->assertSessionHas('success');

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.items.0.variant.name', '256GB / Blue')->where('cart.items.0.total', 202000));

        $this->post('/checkout', ['first_name' => 'A', 'phone' => '01712345678', 'address' => 'Road 1', 'city' => 'Dhaka', 'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'agree' => true])
            ->assertSessionHasNoErrors();
        $order = Order::with('items')->first();
        $this->assertSame(['256GB / Blue', 'S99-256-BL', 101000.0], [$order->items[0]->variant_name, $order->items[0]->sku, $order->items[0]->price]);
        $this->assertSame([0, 3], [$blue->fresh()->stock, $p->fresh()->stock]);

        app(OrderService::class)->changeStatus($order, 'cancelled');
        $this->assertSame([2, 5], [$blue->fresh()->stock, $p->fresh()->stock]);
    }

    public function test_variants_can_be_removed(): void
    {
        $p = $this->phone();
        $this->actingAs($this->admin)->put("/admin/products/{$p->id}", [
            'name' => 'Galaxy S99', 'sku' => 'S99', 'category_id' => $this->category->id, 'price' => 95000, 'stock' => 4,
            'stock_status' => 'in_stock', 'is_active' => true, 'is_featured' => false, 'variants_cleared' => 1,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame([false, 0, 95000.0], [$p->has_variants, $p->variants()->count(), $p->price]);
    }

    public function test_staff_search_lists_each_variant(): void
    {
        $this->phone();
        $this->actingAs($this->admin)->getJson('/admin/products/search?q=galaxy')->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('1.variant_name', '256GB / Black')
            ->assertJsonPath('1.purchasable', false);
    }
}
