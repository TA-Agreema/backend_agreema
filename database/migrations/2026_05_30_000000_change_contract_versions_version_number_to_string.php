<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE contract_versions MODIFY version_number VARCHAR(20) NOT NULL DEFAULT "V1"');
        DB::statement("UPDATE contract_versions SET version_number = CONCAT('V', version_number) WHERE version_number NOT LIKE 'V%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE contract_versions SET version_number = CAST(SUBSTRING(version_number, 2) AS UNSIGNED) WHERE version_number LIKE 'V%'");
        DB::statement('ALTER TABLE contract_versions MODIFY version_number INT NOT NULL DEFAULT 1');
    }
};
