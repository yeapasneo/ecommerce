<?php

namespace App\Ai;

use App\Jobs\WriteProductDraft;
use App\Models\AiDraft;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Which products most need AI-written texts: thin descriptions, best sellers and most viewed first. */
class DraftQueue
{
    public function candidates(): Builder
    {
        $sold = DB::table('order_items')->selectRaw('product_id, sum(quantity) as sold')->groupBy('product_id');

        return Product::query()
            ->leftJoinSub($sold, 'sales', 'sales.product_id', '=', 'products.id')
            ->where(fn ($q) => $q->whereNull('products.description')->orWhereRaw("length(regexp_replace(products.description, '<[^>]+>', '', 'g')) < 200")
                ->orWhereNull('products.description_bn'))
            ->whereNotIn('products.id', AiDraft::whereIn('status', ['pending', 'approved'])
                ->orWhere(fn ($q) => $q->where('status', 'failed')->where('updated_at', '>', now()->subDay()))->select('product_id'))
            ->where('products.is_active', true)
            ->orderByRaw('coalesce(sales.sold, 0) desc')->orderByDesc('products.views')->orderBy('products.id')
            ->select('products.*');
    }

    /** Queue drafts for the next $count products. Returns how many were queued. */
    public function queue(int $count): int
    {
        $products = $this->candidates()->take($count)->get();
        foreach ($products as $product) {
            WriteProductDraft::dispatch($product);
        }

        return $products->count();
    }
}
