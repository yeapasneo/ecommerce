<?php

namespace App\Jobs;

use App\Ai\ProductWriter;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Write an AI draft for one product (in the background, for staff to review). */
class WriteProductDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Product $product) {}

    public function handle(ProductWriter $writer): void
    {
        $writer->draft($this->product);
    }
}
