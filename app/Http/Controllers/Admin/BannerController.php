<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BannerController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Banners', ['banners' => Banner::orderBy('position')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        Banner::create($this->validated($request));

        return back()->with('success', 'Banner created.');
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validated($request, $banner));

        return back()->with('success', 'Banner updated.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();

        return back()->with('success', 'Banner deleted.');
    }

    protected function validated(Request $request, ?Banner $banner = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255'],
            'position' => ['required', Rule::in(['slider', 'side'])],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['bg_color'] = ($data['bg_color'] ?? null) ?: '#081621';
        if ($request->hasFile('image')) {
            if ($banner?->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('banners', 'public');
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
