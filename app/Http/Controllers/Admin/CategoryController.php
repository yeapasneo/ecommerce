<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories', [
            'categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));
        Cache::forget('category_menu');

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        if (($data['parent_id'] ?? null) && in_array((int) $data['parent_id'], $category->descendantIds(), true)) {
            return back()->withErrors(['parent_id' => 'A category cannot be placed inside itself.']);
        }
        $category->update($data);
        Cache::forget('category_menu');

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Move or delete its products and sub-categories first.');
        }
        $category->delete();
        Cache::forget('category_menu');

        return back()->with('success', 'Category deleted.');
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories')->ignore($category?->id)],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'filter_attributes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);
        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['filter_attributes'] = collect(explode(',', (string) ($data['filter_attributes'] ?? '')))
            ->map(fn ($v) => trim($v))->filter()->values()->all() ?: null;

        return $data;
    }
}
