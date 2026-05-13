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
        Schema::table('contract_terminations', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropForeign(['requested_by']);
            $table->dropColumn('requested_by');
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_terminations', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
        });
    }
};
