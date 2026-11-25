<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            // Serial / IMEI numbers of the units sold, and the warranty when they were sold.
            $t->jsonb('serials')->nullable();
            $t->unsignedSmallInteger('warranty_months')->nullable();
        });
        Schema::table('orders', fn (Blueprint $t) => $t->decimal('refunded_amount', 12, 2)->default(0)->after('paid_amount'));

        Schema::create('return_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 10)->default('return'); // return | warranty
            $t->string('status', 12)->default('requested')->index();
            $t->string('reason', 30);
            $t->text('details')->nullable();
            $t->string('resolution', 20)->nullable(); // refund | replacement | repair
            $t->text('staff_note')->nullable(); // shown to the customer
            $t->decimal('refund_amount', 12, 2)->default(0);
            $t->string('refund_method', 20)->nullable();
            $t->string('refund_reference')->nullable();
            $t->timestamp('refunded_at')->nullable()->index();
            $t->boolean('restocked')->default(false);
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('return_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('serial', 100)->nullable();
            $t->boolean('restock')->default(true);
        });
        Schema::create('return_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12);
            $t->text('comment')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_events');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('refunded_amount'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['serials', 'warranty_months']));
    }
};
