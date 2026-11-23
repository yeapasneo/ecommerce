<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::active()->where('slug', $slug)
            ->with(['brand:id,name,slug', 'category', 'images', 'specs'])->firstOrFail();
        $product->increment('views');

        $reviews = $product->reviews()->where('is_approved', true)->with('user:id,name')->latest()->get();
        $user = auth()->user();

        return Inertia::render('Product/Show', [
            'product' => $product,
            'breadcrumbs' => $product->category->breadcrumbs(),
            'specGroups' => $product->specs->groupBy('group')->map(fn ($g, $name) => ['group' => $name, 'items' => $g->values()])->values(),
            'reviews' => $reviews,
            'rating' => ['average' => round((float) $reviews->avg('rating'), 1), 'count' => $reviews->count()],
            'questions' => $product->questions()->with('user:id,name')->latest()->get(),
            'related' => Product::active()->where('category_id', $product->category_id)->whereKeyNot($product->id)
                ->inRandomOrder()->take(6)->get()->map->toCard(),
            'inWishlist' => $user ? $user->wishlist()->where('product_id', $product->id)->exists() : false,
            'inCompare' => in_array($product->id, session('compare', [])),
            'hasReviewed' => $user ? $product->reviews()->where('user_id', $user->id)->exists() : false,
        ]);
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        Review::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data + ['is_approved' => false]
        );

        return back()->with('success', 'Thanks! Your review will appear after moderation.');
    }

    public function question(Request $request, Product $product)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:1000']]);
        Question::create($data + ['product_id' => $product->id, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Your question has been submitted. We will answer it soon.');
    }
}
