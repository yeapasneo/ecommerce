<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Pages', ['pages' => Page::orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        Page::create($this->validated($request));

        return back()->with('success', 'Page created.');
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page));

        return back()->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return back()->with('success', 'Page deleted.');
    }

    protected function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages')->ignore($page?->id)],
            'content' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ]);
        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['title']);

        return $data;
    }
}
