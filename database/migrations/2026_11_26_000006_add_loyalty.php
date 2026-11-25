<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->integer('points')->default(0);
            $t->string('referral_code', 12)->nullable()->unique();
            $t->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedInteger('points_redeemed')->default(0);
            $t->decimal('points_discount', 12, 2)->default(0);
            $t->unsignedInteger('points_earned')->default(0);
        });
        // Every change to a customer's points balance.
        Schema::create('point_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('points');
            $t->integer('balance_after');
            $t->string('type', 12); // earn, redeem, refund, reverse, referral, welcome, adjust
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['points_redeemed', 'points_discount', 'points_earned']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('referred_by');
            $t->dropColumn(['points', 'referral_code']);
        });
    }
};
