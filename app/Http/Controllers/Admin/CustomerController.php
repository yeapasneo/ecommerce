<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $user->update($data);

        return back()->with('success', 'Customer updated.');
    }
}
