<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('channel', 10);        // sms | email
            $t->string('event', 40);
            $t->string('recipient');
            $t->text('message')->nullable();
            $t->string('status', 10);         // sent | failed
            $t->string('error', 500)->nullable();
            $t->timestamps();
            $t->index(['order_id', 'created_at']);
        });

        Schema::create('blocked_phones', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 20)->unique(); // normalized 01XXXXXXXXX
            $t->string('reason', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->string('source', 20)->default('website')->after('status'); // website, phone, facebook, in_shop, other
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::dropIfExists('blocked_phones');
        Schema::dropIfExists('notification_logs');
    }
};
