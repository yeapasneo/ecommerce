<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description_bn')->nullable()->after('description');
            $table->json('key_features_bn')->nullable()->after('key_features');
        });

        // Every call to the AI provider: for the daily limit and the usage table.
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 30);
            $table->string('provider', 20);
            $table->string('model', 100)->nullable();
            $table->string('status', 10);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Product texts written by AI, waiting for staff to approve.
        Schema::create('ai_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('fields')->nullable();
            $table->string('status', 12)->default('pending')->index();
            $table->string('error', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_drafts');
        Schema::dropIfExists('ai_logs');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['description_bn', 'key_features_bn']));
    }
};
