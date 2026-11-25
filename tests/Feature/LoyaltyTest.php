<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Loyalty;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        Setting::put(['loyalty_enabled' => '1', 'loyalty_earn_per' => '100', 'loyalty_point_value' => '1', 'loyalty_min_redeem' => '100', 'loyalty_max_percent' => '20']);
        $this->customer = User::factory()->create(['phone' => '01712345678']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'SSD', 'slug' => 'ssd'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Acme 1TB SSD', 'slug' => 'acme-ssd', 'sku' => 'SSD1', 'price' => 10000, 'stock' => 20, 'stock_status' => 'in_stock',
        ]);
    }

    protected function checkout(array $extra = [])
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);

        return $this->post('/checkout', $extra + [
            'first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'agree' => true,
        ]);
    }

    public function test_points_are_earned_on_delivery_and_used_at_checkout(): void
    {
        $this->actingAs($this->customer);
        $this->checkout()->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertSame(0, $this->customer->fresh()->points); // not until delivered

        app(OrderService::class)->changeStatus($order, 'delivered');
        $this->assertSame([100, 100], [$this->customer->fresh()->points, $order->fresh()->points_earned]); // ৳10,000 / 100
        app(OrderService::class)->changeStatus($order->fresh(), 'delivered'); // no double award
        $this->assertSame(100, $this->customer->fresh()->points);

        // Checkout offers the points: max 20% of ৳10,000 = 2,000 points, but only 100 owned.
        $this->post('/cart', ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->get('/checkout')->assertInertia(fn (Assert $p) => $p->where('loyalty.redeemable', 100)->where('loyalty.balance', 100));
        $this->post('/checkout', ['first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'agree' => true, 'use_points' => true])->assertRedirect();
        $second = Order::latest('id')->first();
        $this->assertSame([100, 100.0], [$second->points_redeemed, $second->points_discount]);
        $this->assertEquals(10000 - 100 + $second->delivery_charge, $second->total);
        $this->assertSame(0, $this->customer->fresh()->points);

        // Cancelling gives the points back.
        app(OrderService::class)->changeStatus($second, 'cancelled');
        $this->assertSame(100, $this->customer->fresh()->points);
        $this->assertSame(['earn', 'redeem', 'refund'], PointTransaction::orderBy('id')->pluck('type')->all());

        $this->get('/account/points')->assertInertia(fn (Assert $p) => $p->component('Account/Points')->where('balance', 100)->has('history', 3));
    }

    public function test_points_below_the_minimum_cannot_be_used(): void
    {
        $this->customer->forceFill(['points' => 50])->save();
        $this->assertSame(0, Loyalty::redeemable($this->customer, 10000));
        $this->customer->forceFill(['points' => 5000])->save();
        $this->assertSame(2000, Loyalty::redeemable($this->customer, 10000)); // capped at 20%
    }

    public function test_referral_bonus_after_the_first_delivered_order(): void
    {
        Setting::put(['referral_enabled' => '1', 'referral_bonus' => '200', 'referral_welcome' => '100']);
        $code = $this->customer->referralCode();
        $this->get("/?ref={$code}")->assertCookie('ref', $code);

        $this->withCookie('ref', $code)->post('/register', ['name' => 'Karim Uddin', 'email' => 'karim@example.com', 'phone' => '01812345678', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!'])->assertRedirect();
        $friend = User::where('email', 'karim@example.com')->first();
        $this->assertSame($this->customer->id, $friend->referred_by);
        $this->assertSame(0, $this->customer->fresh()->points); // nothing for a bare sign-up

        $this->checkout()->assertRedirect();
        app(OrderService::class)->changeStatus(Order::latest('id')->first(), 'delivered');
        $this->assertSame(100 + 100, $friend->fresh()->points); // 100 earned + 100 welcome
        $this->assertSame(200, $this->customer->fresh()->points);

        // Only the first delivered order counts.
        $this->checkout()->assertRedirect();
        app(OrderService::class)->changeStatus(Order::latest('id')->first(), 'delivered');
        $this->assertSame(200, $this->customer->fresh()->points);
    }

    public function test_staff_can_adjust_points(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->actingAs($owner)->post("/admin/customers/{$this->customer->id}/points", ['points' => 50])->assertSessionHasErrors('note');
        $this->actingAs($owner)->post("/admin/customers/{$this->customer->id}/points", ['points' => 50, 'note' => 'Sorry for the late delivery'])->assertSessionHas('success');
        $this->actingAs($owner)->post("/admin/customers/{$this->customer->id}/points", ['points' => -500, 'note' => 'Correction'])->assertSessionHas('success');
        $this->assertSame(0, $this->customer->fresh()->points); // never below zero
        $this->assertSame([50, -50], PointTransaction::orderBy('id')->pluck('points')->all());
    }
}
