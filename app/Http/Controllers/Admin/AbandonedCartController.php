<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Setting;
use App\Services\AbandonedCarts;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AbandonedCartController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $since = now()->subDays(30);

        return Inertia::render('Admin/AbandonedCarts', [
            'carts' => AbandonedCheckout::with('order:id,order_number')
                ->when($status === 'open', fn ($q) => $q->whereNull('recovered_at'))
                ->when($status === 'recovered', fn ($q) => $q->whereNotNull('recovered_at'))
                ->latest('updated_at')->paginate($this->perPage())->withQueryString(),
            'status' => $status,
            'stats' => [
                'open' => AbandonedCheckout::whereNull('recovered_at')->where('updated_at', '>=', $since)->count(),
                'openValue' => (float) AbandonedCheckout::whereNull('recovered_at')->where('updated_at', '>=', $since)->sum('total'),
                'recovered' => AbandonedCheckout::whereNotNull('recovered_at')->where('recovered_at', '>=', $since)->count(),
                'recoveredValue' => (float) AbandonedCheckout::whereNotNull('recovered_at')->where('recovered_at', '>=', $since)->sum('total'),
            ],
            'enabled' => Setting::enabled('abandoned_cart_enabled'),
        ]);
    }

    public function remind(AbandonedCheckout $checkout, AbandonedCarts $carts)
    {
        abort_if($checkout->recovered_at, 422, 'This cart was already ordered.');
        $channels = $carts->remind($checkout);

        return back()->with($channels ? 'success' : 'error', $channels
            ? 'Reminder sent by '.implode(' and ', array_map(fn ($c) => $c === 'sms' ? 'SMS' : 'email', $channels)).'.'
            : 'Could not send a reminder. Check the SMS and email settings.');
    }

    public function destroy(AbandonedCheckout $checkout)
    {
        $checkout->delete();

        return back()->with('success', 'Removed.');
    }
}
