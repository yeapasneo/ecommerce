<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->decimal('cost_price', 12, 2)->nullable()->after('regular_price'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->decimal('cost_price', 12, 2)->nullable()->after('regular_price'));
        // What each unit cost when it was sold, so later cost changes don't rewrite past profit.
        Schema::table('order_items', fn (Blueprint $t) => $t->decimal('cost_price', 12, 2)->nullable()->after('price'));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('cost_price'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn('cost_price'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('cost_price'));
    }
};
