<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use App\Support\Analytics;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index()
    {
        return Inertia::render('Cart', ['cart' => $this->cart->summary()]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'buy_now' => ['nullable', 'boolean'],
            'variant_id' => ['nullable', 'integer'],
        ]);
        $product = Product::findOrFail($data['product_id']);
        $variant = null;
        if ($product->has_variants) {
            $variant = $product->variants()->where('is_active', true)->find($request->integer('variant_id'));
            if (! $variant) {
                return redirect()->route('product.show', $product->slug)->with('error', "Choose an option for {$product->name} first.");
            }
            $variant->setRelation('product', $product);
            if (! $variant->isPurchasable()) {
                return back()->with('error', "{$product->name} ({$variant->name}) is out of stock.");
            }
        } elseif (! $product->isPurchasable()) {
            return back()->with('error', "{$product->name} is currently not available.");
        }

        $this->cart->add($product->id, $data['quantity'] ?? 1, $variant?->id);
        $product->loadMissing('brand:id,name', 'category:id,name');
        $item = Analytics::item($product, $data['quantity'] ?? 1);
        session()->flash('analytics_skip_view', $product->id); // the redirect back isn't a new product view
        analytics()->push('add_to_cart', ['currency' => 'BDT', 'value' => $item['price'] * $item['quantity'], 'items' => [$item]]);

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        return back()->with('success', "{$product->name} added to your cart.");
    }

    public function update(Request $request, int $item)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $this->cart->update($item, $data['quantity']);

        return back();
    }

    public function remove(int $item)
    {
        $this->cart->remove($item);

        return back()->with('success', 'Item removed from cart.');
    }

    public function applyCoupon(Request $request)
    {
        $code = strtoupper(trim($request->validate(['code' => ['required', 'string', 'max:50']])['code']));
        $coupon = Coupon::whereRaw('upper(code) = ?', [$code])->first();
        if (! $coupon) {
            return back()->withErrors(['code' => 'Invalid coupon code.']);
        }
        if ($error = $coupon->validationError($this->cart->subtotal())) {
            return back()->withErrors(['code' => $error]);
        }
        session(['coupon' => $coupon->code]);

        return back()->with('success', 'Coupon applied.');
    }

    public function removeCoupon()
    {
        session()->forget('coupon');

        return back();
    }
}
