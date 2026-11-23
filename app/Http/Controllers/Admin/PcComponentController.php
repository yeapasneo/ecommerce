<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PcComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PcComponentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/PcComponents', [
            'components' => PcComponent::with('category:id,name')->orderBy('sort_order')->get(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        PcComponent::create($this->validated($request));

        return back()->with('success', 'Component created.');
    }

    public function update(Request $request, PcComponent $pcComponent)
    {
        $pcComponent->update($this->validated($request, $pcComponent));

        return back()->with('success', 'Component updated.');
    }

    public function destroy(PcComponent $pcComponent)
    {
        $pcComponent->delete();

        return back()->with('success', 'Component deleted.');
    }

    protected function validated(Request $request, ?PcComponent $c = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('pc_components')->ignore($c?->id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_required' => ['boolean'],
            'section' => ['required', Rule::in(['core', 'peripherals'])],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
