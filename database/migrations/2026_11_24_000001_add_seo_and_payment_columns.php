<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('meta_keywords')->nullable()->after('meta_description');
        });
        foreach (['categories', 'brands', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('meta_title')->nullable();
                $t->string('meta_description', 500)->nullable();
                $t->string('meta_keywords')->nullable();
            });
        }
        Schema::table('orders', function (Blueprint $t) {
            $t->string('gateway_ref')->nullable()->after('payment_status');   // gateway's payment/session id
            $t->string('transaction_id')->nullable()->after('gateway_ref');   // customer-facing transaction id
            $t->timestamp('paid_at')->nullable()->after('transaction_id');
            $t->index('gateway_ref');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('meta_keywords'));
        foreach (['categories', 'brands', 'pages'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['meta_title', 'meta_description', 'meta_keywords']));
        }
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['gateway_ref']);
            $t->dropColumn(['gateway_ref', 'transaction_id', 'paid_at']);
        });
    }
};
