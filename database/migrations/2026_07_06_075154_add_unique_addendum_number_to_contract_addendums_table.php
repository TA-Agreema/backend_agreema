<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_addendums', function (Blueprint $table) {
            $table->unique('addendum_number', 'contract_addendums_addendum_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contract_addendums', function (Blueprint $table) {
            $table->dropUnique('contract_addendums_addendum_number_unique');
        });
    }
};
