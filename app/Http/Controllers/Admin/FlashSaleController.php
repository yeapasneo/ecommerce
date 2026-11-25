<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Support\FlashSales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FlashSaleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/FlashSales/Index', [
            'sales' => FlashSale::withCount('items')->orderByDesc('starts_at')->get()->map(fn ($s) => $s->only('id', 'title', 'starts_at', 'ends_at', 'is_active', 'items_count') + [
                'state' => ! $s->is_active ? 'off' : (now()->lt($s->starts_at) ? 'scheduled' : (now()->gte($s->ends_at) ? 'ended' : 'running')),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/FlashSales/Form', ['sale' => null]);
    }

    public function store(Request $request)
    {
        $sale = DB::transaction(function () use ($request) {
            $sale = FlashSale::create($this->validated($request));
            $this->syncItems($sale, $request->input('items', []));

            return $sale;
        });

        return redirect()->route('admin.flash-sales.index')->with('success', "Flash sale “{$sale->title}” saved.");
    }

    public function edit(FlashSale $flashSale)
    {
        $flashSale->load('items.product:id,name,sku,price,image,has_variants');

        return Inertia::render('Admin/FlashSales/Form', ['sale' => [
            ...$flashSale->only('id', 'title', 'is_active'),
            'starts_at' => $flashSale->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $flashSale->ends_at->format('Y-m-d\TH:i'),
            'items' => $flashSale->items->filter->product->map(fn ($i) => [
                'product_id' => $i->product_id, 'sale_price' => $i->sale_price,
                'meta' => ['name' => $i->product->name, 'sku' => $i->product->sku, 'price' => $i->product->basePrice(), 'image_url' => $i->product->image_url, 'has_variants' => $i->product->has_variants],
            ])->values(),
        ]]);
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        DB::transaction(function () use ($request, $flashSale) {
            $flashSale->update($this->validated($request));
            $this->syncItems($flashSale, $request->input('items', []));
        });

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale saved.');
    }

    public function destroy(FlashSale $flashSale)
    {
        $flashSale->delete();

        return back()->with('success', 'Flash sale deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.sale_price' => ['required', 'numeric', 'min:1'],
        ], ['items.required' => 'Add at least one product.', 'ends_at.after' => 'The sale must end after it starts.']);
        unset($data['items']);

        return $data;
    }

    protected function syncItems(FlashSale $sale, array $items): void
    {
        $sale->items()->delete();
        foreach ($items as $item) {
            $sale->items()->create(['product_id' => $item['product_id'], 'sale_price' => $item['sale_price']]);
        }
        FlashSales::forget();
    }
}
