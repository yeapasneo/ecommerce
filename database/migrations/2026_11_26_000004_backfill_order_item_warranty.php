<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Orders placed before warranty was recorded per sale get the product's current warranty. */
    public function up(): void
    {
        DB::statement('update order_items set warranty_months = products.warranty_months from products
            where products.id = order_items.product_id and order_items.warranty_months is null and products.warranty_months is not null');
    }

    public function down(): void {}
};
