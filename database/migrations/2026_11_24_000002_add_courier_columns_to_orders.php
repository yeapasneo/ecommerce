<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('courier')->nullable()->after('paid_at');            // steadfast | pathao
            $t->string('courier_consignment_id')->nullable()->after('courier');
            $t->string('courier_tracking_code')->nullable()->after('courier_consignment_id');
            $t->string('courier_status')->nullable()->after('courier_tracking_code');
            $t->decimal('courier_fee', 10, 2)->nullable()->after('courier_status');
            $t->decimal('courier_cod', 12, 2)->nullable()->after('courier_fee'); // amount the courier collects
            $t->timestamp('courier_sent_at')->nullable()->after('courier_cod');
            $t->timestamp('courier_status_at')->nullable()->after('courier_sent_at');
            $t->index(['courier', 'courier_consignment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['courier', 'courier_consignment_id']);
            $t->dropColumn(['courier', 'courier_consignment_id', 'courier_tracking_code', 'courier_status', 'courier_fee', 'courier_cod', 'courier_sent_at', 'courier_status_at']);
        });
    }
};
