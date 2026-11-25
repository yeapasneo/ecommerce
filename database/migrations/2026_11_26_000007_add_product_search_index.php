<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** One trigram index over name + SKU + model, so every search word can use an index (see ProductSearch::TEXT). */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("create index if not exists products_search_trgm on products using gin (lower(name || ' ' || sku || ' ' || coalesce(model, '')) gin_trgm_ops)");
            DB::statement('drop index if exists products_sku_trgm'); // covered by the new index
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists products_search_trgm');
            DB::statement('create index if not exists products_sku_trgm on products using gin (lower(sku) gin_trgm_ops)');
        }
    }
};
