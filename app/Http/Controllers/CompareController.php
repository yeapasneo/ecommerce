<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompareController extends Controller
{
    public const MAX = 3;

    public function index()
    {
        $ids = session('compare', []);
        $products = Product::active()->whereIn('id', $ids)->with('specs', 'brand:id,name')->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))->values();

        $specNames = $products->flatMap(fn ($p) => $p->specs->map(fn ($s) => $s->group.'|'.$s->name))->unique()->values();
        $rows = $specNames->map(function ($key) use ($products) {
            [$group, $name] = explode('|', $key, 2);

            return [
                'group' => $group,
                'name' => $name,
                'values' => $products->map(fn ($p) => optional($p->specs->first(fn ($s) => $s->group === $group && $s->name === $name))->value)->all(),
            ];
        });

        return Inertia::render('Compare', [
            'products' => $products->map(fn ($p) => $p->toCard() + ['brand' => $p->brand?->name, 'sku' => $p->sku, 'model' => $p->model]),
            'rows' => $rows,
        ]);
    }

    public function add(Request $request)
    {
        $id = (int) $request->validate(['product_id' => ['required', 'exists:products,id']])['product_id'];
        $ids = session('compare', []);
        if (! in_array($id, $ids)) {
            if (count($ids) >= self::MAX) {
                array_shift($ids);
            }
            $ids[] = $id;
        }
        session(['compare' => array_values($ids)]);

        return back()->with('success', 'Product added to compare.');
    }

    public function remove(int $id)
    {
        session(['compare' => array_values(array_diff(session('compare', []), [$id]))]);

        return back();
    }
}
