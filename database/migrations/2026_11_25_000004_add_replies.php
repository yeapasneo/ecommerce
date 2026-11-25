<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's public reply under a review.
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
        });
        // The emailed reply to a contact-form message.
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['reply', 'replied_at']));
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn(['reply', 'replied_at']);
        });
    }
};
