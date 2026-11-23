<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guests_and_customers_cannot_access_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_load(): void
    {
        $admin = $this->admin();
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/categories', '/admin/brands', '/admin/orders',
            '/admin/customers', '/admin/coupons', '/admin/banners', '/admin/offers', '/admin/pages', '/admin/reviews',
            '/admin/questions', '/admin/messages', '/admin/settings', '/admin/pc-components'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_product_with_specs_and_image(): void
    {
        Storage::fake('public');
        $cat = Category::create(['name' => 'Mouse', 'slug' => 'mouse']);
        $this->actingAs($this->admin())->post('/admin/products', [
            'name' => 'Super Mouse', 'sku' => 'MS-1', 'category_id' => $cat->id, 'price' => 1200, 'stock' => 5,
            'stock_status' => 'in_stock', 'is_active' => true, 'is_featured' => false,
            'key_features' => ['DPI: 16000', ''],
            'specs' => [['group' => 'Basic', 'name' => 'DPI', 'value' => '16000'], ['group' => '', 'name' => '', 'value' => '']],
            'image' => UploadedFile::fake()->image('mouse.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $p = Product::where('sku', 'MS-1')->firstOrFail();
        $this->assertSame('super-mouse', $p->slug);
        $this->assertSame(['DPI: 16000'], $p->key_features);
        $this->assertCount(1, $p->specs);
        Storage::disk('public')->assertExists($p->image);
    }

    public function test_admin_can_update_order_status_and_cancel_restocks(): void
    {
        $cat = Category::create(['name' => 'X', 'slug' => 'x']);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p', 'sku' => 'P', 'price' => 100, 'stock' => 1, 'stock_status' => 'in_stock']);
        $order = Order::create(['order_number' => '1', 'first_name' => 'A', 'phone' => '1', 'address' => 'a', 'city' => 'Dhaka', 'subtotal' => 200, 'total' => 200]);
        $order->items()->create(['product_id' => $p->id, 'name' => 'P', 'price' => 100, 'quantity' => 2, 'total' => 200]);

        $admin = $this->admin();
        $this->actingAs($admin)->put("/admin/orders/{$order->id}", ['status' => 'processing', 'payment_status' => 'unpaid'])->assertSessionHas('success');
        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs($admin)->put("/admin/orders/{$order->id}", ['status' => 'cancelled', 'payment_status' => 'unpaid']);
        $this->assertSame(3, $p->fresh()->stock);
        $this->assertCount(2, $order->histories);

        $this->actingAs($admin)->put("/admin/orders/{$order->id}", ['status' => 'pending', 'payment_status' => 'unpaid'])->assertSessionHas('error');
    }

    public function test_category_cannot_be_moved_inside_itself(): void
    {
        $parent = Category::create(['name' => 'A', 'slug' => 'a']);
        $child = Category::create(['name' => 'B', 'slug' => 'b', 'parent_id' => $parent->id]);
        $this->actingAs($this->admin())->put("/admin/categories/{$parent->id}", ['name' => 'A', 'parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_blocked_user_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/account')->assertRedirect('/login');
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'site_name' => 'My Shop', 'delivery_charge_inside' => 50, 'delivery_charge_outside' => 100,
            'express_delivery_charge' => 150, 'free_delivery_over' => 0,
        ])->assertSessionHas('success');
        $this->assertSame('My Shop', Setting::get('site_name'));
    }
}
