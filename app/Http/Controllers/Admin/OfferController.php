<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Offers', ['offers' => Offer::latest()->get()]);
    }

    public function store(Request $request)
    {
        Offer::create($this->validated($request));

        return back()->with('success', 'Offer created.');
    }

    public function update(Request $request, Offer $offer)
    {
        $offer->update($this->validated($request, $offer));

        return back()->with('success', 'Offer updated.');
    }

    public function destroy(Offer $offer)
    {
        if ($offer->image) {
            Storage::disk('public')->delete($offer->image);
        }
        $offer->delete();

        return back()->with('success', 'Offer deleted.');
    }

    protected function validated(Request $request, ?Offer $offer = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $data['slug'] = $offer?->slug ?? Str::slug($data['title']).'-'.Str::lower(Str::random(4));
        if ($request->hasFile('image')) {
            if ($offer?->image) {
                Storage::disk('public')->delete($offer->image);
            }
            $data['image'] = $request->file('image')->store('offers', 'public');
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
