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
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['terminated_at']);
            $table->dropForeign(['parent_contract_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('terminated_at')->nullable()->after('status');
            $table->foreignId('parent_contract_id')->nullable()->constrained('contracts')->onDelete('set null');
        });
    }
};
