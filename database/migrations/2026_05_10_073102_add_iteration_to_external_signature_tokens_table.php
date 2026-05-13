<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_signature_tokens', function (Blueprint $table) {
            // Track which review iteration this token belongs to
            $table->integer('iteration')->default(1)->after('token');
            // 'review_result' enum: pending | approved | revised
            $table->string('review_status')->default('pending')->after('iteration');
            // Notes from external when they request revision
            $table->text('review_notes')->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('external_signature_tokens', function (Blueprint $table) {
            $table->dropColumn(['iteration', 'review_status', 'review_notes']);
        });
    }
};
