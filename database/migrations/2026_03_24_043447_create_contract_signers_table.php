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
        Schema::create('contract_signers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->enum('signer_type', ['internal', 'external']);
            $table->integer('sequence')->default(1);
            $table->enum('sign_status', ['pending', 'signed', 'declined'])->default('pending');
            $table->enum('signature_type', ['digital', 'manual_upload'])->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_signers');
    }
};
