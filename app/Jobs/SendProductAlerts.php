<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Back-in-stock or price-drop alerts for one product, sent in the background. */
class SendProductAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $type, public int $productId, public ?int $variantId = null, public ?float $oldPrice = null) {}

    public function handle(ProductAlerts $alerts): void
    {
        Product::$flashPrices = false;
        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }
        $this->type === 'back_in_stock'
            ? $alerts->sendBackInStock($product, $this->variantId)
            : $alerts->sendPriceDrop($product, (float) $this->oldPrice);
    }
}
