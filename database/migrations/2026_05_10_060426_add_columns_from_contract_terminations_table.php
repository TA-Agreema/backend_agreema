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
            $table->string('termination_number')->unique()->after('contract_id');
            $table->string('title')->nullable()->after('termination_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_terminations', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->dropColumn('termination_number');
        });
    }
};
