<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Brands', ['brands' => Brand::withCount('products')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Brand::create($this->validated($request));

        return back()->with('success', 'Brand created.');
    }

    public function update(Request $request, Brand $brand)
    {
        $brand->update($this->validated($request, $brand));

        return back()->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return back()->with('success', 'Brand deleted.');
    }

    protected function validated(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('brands')->ignore($brand?->id)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);
        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if ($request->hasFile('logo')) {
            if ($brand?->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        } else {
            unset($data['logo']);
        }

        return $data;
    }
}
