<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Suppliers', [
            'suppliers' => Supplier::withCount('purchaseOrders')
                ->withSum(['purchaseOrders as purchased' => fn ($q) => $q->whereIn('status', ['partial', 'received'])], 'total')
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier saved.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            $supplier->update(['is_active' => false]);

            return back()->with('success', 'This supplier has purchase orders, so it was hidden instead of deleted.');
        }
        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
    }
}
