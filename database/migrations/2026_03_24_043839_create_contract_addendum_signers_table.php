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
        Schema::create('contract_addendum_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_addendum_id')->constrained()->onDelete('cascade');
            $table->foreignId('signer_id')->constrained()->onDelete('cascade');
            $table->enum('signer_type', ['internal', 'external']);
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_addendum_signers');
    }
};
