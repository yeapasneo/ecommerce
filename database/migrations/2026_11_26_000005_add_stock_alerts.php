<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Notify me" requests (back in stock) and sent price-drop alerts.
        Schema::create('stock_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 15); // back_in_stock | price_drop
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->decimal('price', 12, 2)->nullable(); // price when sent (price drops)
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->index(['product_id', 'type', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
