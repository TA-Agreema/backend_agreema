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
        Schema::table('contract_categories', function (Blueprint $table) {
            // Short code used as prefix in contract number, e.g. "PKS", "NDA", "PS"
            $table->string('number_prefix', 10)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_categories', function (Blueprint $table) {
            $table->dropColumn('number_prefix');
        });
    }
};
