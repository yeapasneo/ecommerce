<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Returns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $customer;

    protected Product $product;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->withCredentials();
        $this->owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->customer = User::factory()->create(['phone' => '01712345678']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Phone', 'slug' => 'phone'])->id,
            'brand_id' => Brand::create(['name' => 'Acme', 'slug' => 'acme'])->id,
            'name' => 'Acme X1 Phone', 'slug' => 'acme-x1', 'sku' => 'X1', 'price' => 20000, 'cost_price' => 16000, 'stock' => 5, 'stock_status' => 'in_stock', 'warranty_months' => 12,
        ]);
        $service = app(OrderService::class);
        $this->order = $service->place([['product_id' => $this->product->id, 'quantity' => 2]], fn ($subtotal) => [
            'user_id' => $this->customer->id, 'first_name' => 'Rahim', 'phone' => '01712345678', 'address' => 'Road 1', 'city' => 'Dhaka',
            'delivery_method' => 'home_delivery', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'processing',
            'subtotal' => $subtotal, 'delivery_charge' => 60, 'discount' => 0, 'total' => $subtotal + 60,
        ], ['notify' => false]);
        $service->changeStatus($this->order, 'delivered'); // COD delivered = paid
        $this->order->refresh();
    }

    protected function item()
    {
        return $this->order->items()->first();
    }

    public function test_full_return_flow_with_refund_and_restock(): void
    {
        $item = $this->item();
        $this->assertSame([12, 40060.0], [$item->warranty_months, $this->order->paid_amount]);

        // Customer requests a return of one unit.
        $this->actingAs($this->customer)->get("/account/orders/{$this->order->order_number}/return")
            ->assertInertia(fn (Assert $p) => $p->component('Account/ReturnForm')->where("claimable.{$item->id}.return", 2)->where("claimable.{$item->id}.warranty", 2));
        $this->actingAs($this->customer)->post("/account/orders/{$this->order->order_number}/return", [
            'type' => 'return', 'reason' => 'defective', 'details' => 'Screen flickers all the time.', 'items' => [$item->id => ['quantity' => 1, 'serial' => ' ab-12 3 ']],
        ])->assertRedirect('/account/returns');
        $r = ReturnRequest::with('items')->firstOrFail();
        $this->assertSame(['RMA-00001', 'requested', 'AB-123'], [$r->number, $r->status, $r->items[0]->serial]);

        // Only one more unit can be claimed for return now.
        $this->actingAs($this->customer)->post("/account/orders/{$this->order->order_number}/return", [
            'type' => 'return', 'reason' => 'defective', 'details' => 'Second phone too, same issue.', 'items' => [$item->id => ['quantity' => 2]],
        ])->assertSessionHasErrors("items.{$item->id}.quantity");

        // Staff approve, receive (restock) and refund.
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'approve', 'note' => 'Please send it to our shop.'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'receive', 'restock' => [$r->items[0]->id => true]])->assertSessionHasNoErrors();
        $this->assertSame(4, $this->product->fresh()->stock); // 5 − 2 sold + 1 back
        $this->assertSame('return', StockMovement::latest('id')->first()->reason);

        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'refund', 'amount' => 50000, 'method' => 'bkash'])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'refund', 'amount' => 20000, 'method' => 'bkash', 'reference' => 'TX1'])->assertSessionHasNoErrors();
        $this->order->refresh();
        $this->assertSame([20000.0, 'paid'], [$this->order->refunded_amount, $this->order->payment_status]); // partial refund keeps "paid"
        $this->assertSame('refunded', $r->fresh()->status);

        // Reports: refund comes off revenue; the restocked phone keeps its cost.
        $this->actingAs($this->owner)->get('/admin/reports')->assertInertia(fn (Assert $p) => $p
            ->where('totals.refunds', 20000)
            ->where('totals.netRevenue', 20060)
            ->where('totals.profit', 8000 - 20000 + 16000));

        // The customer sees it in their account.
        $this->actingAs($this->customer)->get('/account/returns')->assertInertia(fn (Assert $p) => $p->where('returns.0.status', 'refunded')->where('returns.0.staff_note', 'Please send it to our shop.'));
    }

    public function test_return_window_and_warranty_limits(): void
    {
        Setting::put(['return_days' => '7']);
        $this->travel(10)->days();
        $item = $this->item();
        $this->actingAs($this->customer)->post("/account/orders/{$this->order->order_number}/return", [
            'type' => 'return', 'reason' => 'changed_mind', 'details' => 'Do not need it any more.', 'items' => [$item->id => ['quantity' => 1]],
        ])->assertSessionHasErrors("items.{$item->id}.quantity");
        // Still under the 12-month warranty.
        $this->actingAs($this->customer)->post("/account/orders/{$this->order->order_number}/return", [
            'type' => 'warranty', 'reason' => 'defective', 'details' => 'Battery drains in an hour.', 'items' => [$item->id => ['quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->travel(13)->months();
        $this->actingAs($this->customer)->post("/account/orders/{$this->order->order_number}/return", [
            'type' => 'warranty', 'reason' => 'defective', 'details' => 'Other phone broke too.', 'items' => [$item->id => ['quantity' => 1]],
        ])->assertSessionHasErrors("items.{$item->id}.quantity");
        // Staff can still open one.
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/returns", ['type' => 'warranty', 'reason' => 'defective', 'items' => [$item->id => ['quantity' => 1]]])->assertRedirect();
        $this->assertSame(2, ReturnRequest::count());
    }

    public function test_reject_and_cancel(): void
    {
        $item = $this->item();
        $open = fn () => app(Returns::class)->open($this->order, 'return', [$item->id => ['quantity' => 1]], 'changed_mind', 'x', $this->customer->id);
        $r = $open();
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'reject', 'note' => 'Box was opened and used.'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put("/admin/returns/{$r->id}", ['action' => 'refund', 'amount' => 10, 'method' => 'cash'])->assertSessionHasErrors('status');

        $r2 = $open(); // a rejected request frees the items again
        $this->actingAs($this->customer)->post("/account/returns/{$r2->number}/cancel")->assertSessionHas('success');
        $this->assertSame('cancelled', $r2->fresh()->status);
        $this->actingAs(User::factory()->create())->post("/account/returns/{$r2->number}/cancel")->assertNotFound();
    }

    public function test_serials_and_warranty_lookup(): void
    {
        $item = $this->item();
        $this->actingAs($this->owner)->put("/admin/orders/{$this->order->id}/items/{$item->id}/serials", ['serials' => "imei-111\nIMEI-222\nIMEI-333"])->assertSessionHasErrors('serials');
        $this->actingAs($this->owner)->put("/admin/orders/{$this->order->id}/items/{$item->id}/serials", ['serials' => "imei-111\n imei-222 "])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(['IMEI-111', 'IMEI-222'], $item->fresh()->serials);

        $this->actingAs($this->owner)->get('/admin/warranty?serial=imei-222')->assertInertia(fn (Assert $p) => $p
            ->has('results', 1)->where('results.0.order.order_number', $this->order->order_number)->where('results.0.warranty.valid', true)->where('results.0.warranty.months', 12));

        // The same serial can't be sold twice.
        $other = app(OrderService::class)->place([['product_id' => $this->product->id, 'quantity' => 1]], fn ($s) => [
            'first_name' => 'K', 'phone' => '01800000000', 'address' => 'A', 'city' => 'Dhaka', 'delivery_method' => 'home_delivery', 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'status' => 'processing', 'subtotal' => $s, 'delivery_charge' => 0, 'discount' => 0, 'total' => $s], ['notify' => false]);
        $this->actingAs($this->owner)->put("/admin/orders/{$other->id}/items/{$other->items()->first()->id}/serials", ['serials' => 'IMEI-111'])->assertSessionHasErrors('serials');
    }

    public function test_orders_permission_needed(): void
    {
        $catalog = User::factory()->create(['is_admin' => true, 'role' => 'catalog']);
        $this->actingAs($catalog)->get('/admin/returns')->assertForbidden();
        $this->actingAs($catalog)->get('/admin/warranty')->assertForbidden();
    }
}
