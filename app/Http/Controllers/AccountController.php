<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Loyalty;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Account/Dashboard', [
            'stats' => [
                'orders' => $user->orders()->count(),
                'pending' => $user->orders()->whereIn('status', ['pending', 'processing', 'shipped'])->count(),
                'wishlist' => $user->wishlist()->count(),
                'savedPcs' => $user->savedPcs()->count(),
            ],
            'recentOrders' => $user->orders()->latest()->take(5)->get(),
        ]);
    }

    public function orders(Request $request)
    {
        return Inertia::render('Account/Orders', [
            'orders' => $request->user()->orders()->withCount('items')->latest()->paginate(10),
        ]);
    }

    public function order(Request $request, string $number)
    {
        $order = $request->user()->orders()->where('order_number', $number)->with('items.product:id,slug,image', 'histories')->firstOrFail();

        return Inertia::render('Account/OrderShow', [
            'order' => $order,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'deliveryMethods' => Order::DELIVERY_METHODS,
            'canReturn' => $order->status === 'delivered' && Setting::enabled('returns_enabled'),
            'returns' => $order->returns()->get(['id', 'number', 'type', 'status', 'created_at']),
            'returnStatuses' => ReturnRequest::STATUSES,
        ]);
    }

    public function cancelOrder(Request $request, string $number)
    {
        $order = $request->user()->orders()->where('order_number', $number)->firstOrFail();
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be cancelled.');
        }
        app(OrderService::class)->changeStatus($order, 'cancelled', 'Cancelled by customer.');

        return back()->with('success', 'Your order has been cancelled.');
    }

    public function profile(Request $request)
    {
        return Inertia::render('Account/Profile', ['user' => $request->user()->only('name', 'email', 'phone', 'address', 'city')]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);
        $request->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password changed.');
    }

    public function wishlist(Request $request)
    {
        return Inertia::render('Account/Wishlist', [
            'items' => $request->user()->wishlist()->with('product')->latest()->get()
                ->filter(fn ($w) => $w->product)->map(fn ($w) => $w->product->toCard())->values(),
        ]);
    }

    public function toggleWishlist(Request $request)
    {
        $id = (int) $request->validate(['product_id' => ['required', 'exists:products,id']])['product_id'];
        $existing = Wishlist::where('user_id', $request->user()->id)->where('product_id', $id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Removed from wishlist.');
        }
        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $id]);

        return back()->with('success', 'Added to wishlist.');
    }

    public function savedPcs(Request $request)
    {
        return Inertia::render('Account/SavedPcs', ['savedPcs' => $request->user()->savedPcs()->latest()->get()]);
    }

    /** Loyalty points balance, history and the customer's referral link. */
    public function points(Request $request)
    {
        abort_unless(Loyalty::enabled() || Setting::enabled('referral_enabled'), 404);
        $user = $request->user()->fresh();

        return Inertia::render('Account/Points', [
            'balance' => (int) $user->points,
            'value' => Loyalty::pointValue(),
            'earnPer' => Loyalty::earnPer(),
            'minRedeem' => (int) Setting::get('loyalty_min_redeem', 100),
            'maxPercent' => (int) Setting::get('loyalty_max_percent', 20),
            'loyalty' => Loyalty::enabled(),
            'history' => $user->pointTransactions()->with('order:id,order_number')->take(50)->get(),
            'types' => PointTransaction::TYPES,
            'referral' => Setting::enabled('referral_enabled') ? [
                'link' => url('/?ref='.$user->referralCode()),
                'code' => $user->referralCode(),
                'bonus' => (int) Setting::get('referral_bonus', 200),
                'welcome' => (int) Setting::get('referral_welcome', 100),
                'invited' => User::where('referred_by', $user->id)->count(),
            ] : null,
        ]);
    }
}
