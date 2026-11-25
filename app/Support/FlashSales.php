<?php

namespace App\Support;

use App\Models\FlashSaleItem;
use Illuminate\Support\Facades\Cache;

/** Sale prices from running flash sales, looked up once per request (cached for a minute). */
class FlashSales
{
    protected ?array $map = null;

    public function active(): array
    {
        return $this->map ??= Cache::remember('flash_sales:active', 60, fn () => FlashSaleItem::query()
            ->join('flash_sales', 'flash_sales.id', '=', 'flash_sale_items.flash_sale_id')
            ->where('flash_sales.is_active', true)
            ->where('flash_sales.ends_at', '>', now())
            ->where('flash_sales.starts_at', '<=', now()->addMinute())
            ->orderBy('flash_sale_items.sale_price')
            ->get(['flash_sale_items.product_id', 'flash_sale_items.sale_price', 'flash_sales.starts_at', 'flash_sales.ends_at', 'flash_sales.title'])
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'price' => (float) $rows[0]->sale_price,
                'starts_at' => (string) $rows[0]->starts_at,
                'ends_at' => (string) $rows[0]->ends_at,
                'title' => $rows[0]->title,
            ])->all());
    }

    /** The running sale for a product, if any. */
    public function for(int $productId): ?array
    {
        $sale = $this->active()[$productId] ?? null;
        if (! $sale || now()->lt($sale['starts_at']) || now()->gte($sale['ends_at'])) {
            return null;
        }

        return $sale;
    }

    public static function forget(): void
    {
        Cache::forget('flash_sales:active');
        app()->forgetInstance(self::class);
    }
}
