<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock history. Every stock change on a product or variant is recorded by the
 * models' "updated" events; Stock::as() says why (sale, purchase, return…).
 * Products with variants are tracked per variant, so nothing is counted twice.
 */
class Stock
{
    /** @var array<int, array{reason: string, reference: ?Model, note: ?string}> */
    protected static array $context = [];

    /** Run $callback with every stock change inside it recorded under $reason. */
    public static function as(string $reason, ?Model $reference, callable $callback, ?string $note = null): mixed
    {
        self::$context[] = ['reason' => $reason, 'reference' => $reference, 'note' => $note];
        try {
            return $callback();
        } finally {
            array_pop(self::$context);
        }
    }

    public static function changed(Product $product, ?ProductVariant $variant, int $change, int $after, bool $created = false): void
    {
        if ($change === 0) {
            return;
        }
        $ctx = end(self::$context) ?: null;
        // Opening stock is only recorded when a person adds a product (not for catalog imports and seeding).
        if ($created && ! $ctx && ! auth()->check()) {
            return;
        }
        StockMovement::create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'change' => $change,
            'stock_after' => $after,
            'reason' => $ctx['reason'] ?? ($created ? 'initial' : 'adjustment'),
            'reference_type' => $ctx && $ctx['reference'] ? $ctx['reference']->getMorphClass() : null,
            'reference_id' => $ctx && $ctx['reference'] ? $ctx['reference']->getKey() : null,
            'note' => $ctx['note'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    /** Hooked into Product and ProductVariant events. */
    public static function watch(Model $model, bool $created = false): void
    {
        if (! $created && ! $model->wasChanged('stock')) {
            return;
        }
        $after = (int) $model->stock;
        $change = $created ? $after : $after - (int) $model->getOriginal('stock');
        if ($model instanceof ProductVariant) {
            self::changed($model->product()->first() ?? new Product, $model, $change, $after, $created);
        } elseif ($model instanceof Product && ! $model->has_variants) {
            self::changed($model, null, $change, $after, $created);
        }
    }
}
