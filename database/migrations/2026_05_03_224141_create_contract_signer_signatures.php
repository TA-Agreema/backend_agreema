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
        Schema::create('contract_signer_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_signer_id')->constrained('contract_signers')->cascadeOnDelete();
            $table->foreignId('contract_version_id')->constrained('contract_versions');
            $table->integer('iteration')->default(1);
            $table->enum('signature_type', ['canvas', 'upload']);
            $table->text('signature_path')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
