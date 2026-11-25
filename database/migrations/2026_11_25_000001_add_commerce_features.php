<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // VAT and partial payments
        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('tax', 12, 2)->default(0)->after('discount');
            $t->decimal('paid_amount', 12, 2)->default(0)->after('total');
        });
        DB::table('orders')->where('payment_status', 'paid')->update(['paid_amount' => DB::raw('total')]);

        // Product variants (e.g. Storage: 256GB, Colour: Black)
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');                    // "256GB / Black"
            $t->json('options');                   // {"Storage": "256GB", "Colour": "Black"}
            $t->string('sku')->nullable();
            $t->decimal('price', 12, 2);
            $t->decimal('regular_price', 12, 2)->nullable();
            $t->unsignedInteger('stock')->default(0);
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['product_id', 'is_active']);
        });
        Schema::table('cart_items', fn (Blueprint $t) => $t->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->cascadeOnDelete());
        Schema::table('order_items', function (Blueprint $t) {
            $t->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $t->string('variant_name')->nullable()->after('name');
        });

        // Staff roles and activity log
        Schema::table('users', fn (Blueprint $t) => $t->string('role', 30)->nullable()->after('is_admin'));
        DB::table('users')->where('is_admin', true)->update(['role' => 'owner']);
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);              // created | updated | deleted | …
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('description', 500);
            $t->json('changes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
            $t->index('created_at');
        });

        // Flash sales
        Schema::create('flash_sales', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('flash_sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('sale_price', 12, 2);
            $t->unique(['flash_sale_id', 'product_id']);
        });

        // Abandoned checkouts
        Schema::create('abandoned_checkouts', function (Blueprint $t) {
            $t->id();
            $t->string('session_id')->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->json('items');                     // [{product_id, variant_id, quantity, name, price}]
            $t->decimal('total', 12, 2)->default(0);
            $t->string('token', 64)->unique();
            $t->timestamp('reminded_at')->nullable();
            $t->timestamp('recovered_at')->nullable();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index('updated_at');
        });

        // Typo-tolerant search
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS products_name_trgm ON products USING gin (lower(name) gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS products_sku_trgm ON products USING gin (lower(sku) gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_name_trgm');
        DB::statement('DROP INDEX IF EXISTS products_sku_trgm');
        Schema::dropIfExists('abandoned_checkouts');
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('activity_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('variant_id');
            $t->dropColumn('variant_name');
        });
        Schema::table('cart_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('variant_id'));
        Schema::dropIfExists('product_variants');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['tax', 'paid_amount']));
    }
};
