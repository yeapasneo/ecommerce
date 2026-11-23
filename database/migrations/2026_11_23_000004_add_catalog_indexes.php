<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PostgreSQL does not index foreign keys on its own; with a full imported
// catalog these lookups otherwise scan whole tables.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->index(['brand_id', 'is_active']));
        Schema::table('product_specs', fn (Blueprint $t) => $t->index('product_id'));
        Schema::table('product_images', fn (Blueprint $t) => $t->index('product_id'));
        Schema::table('reviews', fn (Blueprint $t) => $t->index('product_id'));
        Schema::table('questions', fn (Blueprint $t) => $t->index('product_id'));
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex(['brand_id', 'is_active']));
        Schema::table('product_specs', fn (Blueprint $t) => $t->dropIndex(['product_id']));
        Schema::table('product_images', fn (Blueprint $t) => $t->dropIndex(['product_id']));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropIndex(['product_id']));
        Schema::table('questions', fn (Blueprint $t) => $t->dropIndex(['product_id']));
    }
};
