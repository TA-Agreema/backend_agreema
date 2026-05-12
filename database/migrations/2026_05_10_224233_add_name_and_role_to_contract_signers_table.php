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
        Schema::table('contract_signers', function (Blueprint $table) {
            $table->string('signer_name')->nullable()->after('signer_type')->comment('Nama signer (terutama untuk eksternal)');
            $table->string('signer_role')->nullable()->after('signer_name')->comment('Jabatan signer (terutama untuk eksternal)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_signers', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'signer_role']);
        });
    }
};
