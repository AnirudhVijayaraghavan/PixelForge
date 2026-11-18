<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->unsignedInteger('submission_attempts')->default(0);
            $table->timestamp('last_submission_attempt_at')->nullable();
            $table->timestamp('next_submission_attempt_at')->nullable();
            $table->string('submission_error_code')->nullable();
            $table->string('submission_error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn(['submission_attempts', 'last_submission_attempt_at', 'next_submission_attempt_at', 'submission_error_code', 'submission_error_message']);
        });
    }
};
