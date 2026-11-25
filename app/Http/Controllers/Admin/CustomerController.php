<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\FraudCheck;
use App\Support\Phone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withCount('orders')->withSum(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")->orWhere('phone', 'ilike', "%{$s}%")))
            ->when($request->role === 'admin', fn ($q) => $q->where('is_admin', true))
            ->when($request->role === 'customer', fn ($q) => $q->where('is_admin', false))
            ->latest()->paginate($this->perPage())->withQueryString();

        return Inertia::render('Admin/Customers/Index', ['users' => $users, 'filters' => $request->only('search', 'role')]);
    }

    public function show(User $user)
    {
        return Inertia::render('Admin/Customers/Show', [
            'customer' => $user,
            'orders' => $user->orders()->latest()->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => ['boolean'], 'is_admin' => ['boolean']]);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own access.');
        }
        // Granting or removing admin access is for staff managers only (Staff page).
        if (array_key_exists('is_admin', $data) && (bool) $data['is_admin'] !== (bool) $user->is_admin) {
            abort_unless($request->user()->hasPermission('staff'), 403, 'Only owners can change admin access. Use the Staff page.');
            $data['role'] = $data['is_admin'] ? 'manager' : null;
        }
        // Order managers can block customers, but not other staff.
        if ($user->is_admin && ! $request->user()->hasPermission('staff')) {
            abort(403, 'Only owners can change staff accounts.');
        }
        $user->update($data);

        return back()->with('success', 'Customer updated.');
    }

    /** Fill in a staff order from the customer's account or last order (JSON). */
    public function lookup(Request $request, FraudCheck $fraud)
    {
        $phone = (string) $request->query('phone');
        if (! Phone::isValid($phone)) {
            return response()->json(['found' => false]);
        }
        $key = Phone::key($phone);
        $user = User::whereRaw("right(regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'), 10) = ?", [$key])->first(['id', 'name', 'email', 'address', 'city']);
        $last = Order::whereRaw("right(regexp_replace(phone, '[^0-9]', '', 'g'), 10) = ?", [$key])->latest('id')->first(['first_name', 'last_name', 'email', 'address', 'city']);

        return response()->json([
            'found' => (bool) ($user || $last),
            'user' => $user,
            'last' => $last,
            'validity' => $fraud->report($phone),
        ]);
    }
}
