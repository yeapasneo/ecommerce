<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PcComponent;
use App\Models\Product;
use App\Models\SavedPc;
use App\Services\CartService;
use App\Services\ProductListing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PcBuilderController extends Controller
{
    protected function selection(): array
    {
        return session('pc_builder', []);
    }

    public function index()
    {
        $selection = $this->selection();
        $products = Product::whereIn('id', array_values($selection))->get()->keyBy('id');

        $components = PcComponent::orderBy('sort_order')->get()->map(function ($c) use ($selection, $products) {
            $pid = $selection[$c->slug] ?? null;

            return [
                'name' => $c->name,
                'slug' => $c->slug,
                'section' => $c->section,
                'is_required' => $c->is_required,
                'product' => $pid && isset($products[$pid]) ? $products[$pid]->toCard() : null,
            ];
        });

        $total = $components->sum(fn ($c) => $c['product']['price'] ?? 0);

        seo()->title('PC Builder - Build Your Own Computer')->description('Pick a processor, motherboard, RAM, storage and more, check compatibility and get the best price for your custom PC.');

        return Inertia::render('PcBuilder/Index', [
            'components' => $components,
            'total' => $total,
            'savedPcs' => auth()->check() ? auth()->user()->savedPcs()->latest()->get() : [],
        ]);
    }

    public function choose(Request $request, string $component)
    {
        $comp = PcComponent::where('slug', $component)->firstOrFail();
        $category = $comp->category_id ? Category::find($comp->category_id) : null;
        $base = Product::active()->whereIn('stock_status', ['in_stock', 'pre_order'])
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->descendantIds()));

        return Inertia::render('PcBuilder/Choose', [
            'component' => $comp->only('name', 'slug'),
            ...(new ProductListing($request))->build($base, $category?->effectiveFilterAttributes() ?? []),
        ]);
    }

    public function select(string $component, Product $product)
    {
        PcComponent::where('slug', $component)->firstOrFail();
        session(['pc_builder' => array_merge($this->selection(), [$component => $product->id])]);

        return redirect()->route('pc-builder.index');
    }

    public function remove(string $component)
    {
        $sel = $this->selection();
        unset($sel[$component]);
        session(['pc_builder' => $sel]);

        return back();
    }

    public function clear()
    {
        session()->forget('pc_builder');

        return back();
    }

    public function addToCart(CartService $cart)
    {
        $sel = $this->selection();
        $missing = PcComponent::where('is_required', true)->whereNotIn('slug', array_keys($sel))->pluck('name');
        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Please choose required components: '.$missing->join(', '));
        }
        foreach (Product::whereIn('id', array_values($sel))->get() as $p) {
            if ($p->isPurchasable()) {
                $cart->add($p->id);
            }
        }

        return redirect()->route('cart.index')->with('success', 'PC components added to your cart.');
    }

    public function save(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $sel = $this->selection();
        if (! $sel) {
            return back()->with('error', 'Choose at least one component first.');
        }
        $total = Product::whereIn('id', array_values($sel))->sum('price');
        $request->user()->savedPcs()->create(['name' => $data['name'], 'items' => $sel, 'total' => $total]);

        return back()->with('success', 'Your PC build has been saved.');
    }

    public function load(SavedPc $savedPc)
    {
        abort_unless($savedPc->user_id === auth()->id(), 404);
        session(['pc_builder' => $savedPc->items]);

        return redirect()->route('pc-builder.index');
    }

    public function destroy(SavedPc $savedPc)
    {
        abort_unless($savedPc->user_id === auth()->id(), 404);
        $savedPc->delete();

        return back()->with('success', 'Saved PC removed.');
    }
}
