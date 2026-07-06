<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_terminations', function (Blueprint $table) {
            $table->unique('contract_id', 'contract_terminations_contract_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contract_terminations', function (Blueprint $table) {
            $table->dropUnique('contract_terminations_contract_id_unique');
        });
    }
};
