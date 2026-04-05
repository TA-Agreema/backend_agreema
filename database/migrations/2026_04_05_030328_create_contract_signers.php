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
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_email')->nullable();
            $table->string('signer_type'); // internal, external
            $table->integer('sequence')->default(1);
            $table->string('review_status')->default('pending'); // pending, approved, rejected
            $table->text('review_note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('sign_status')->default('pending'); // pending, signed, rejected
            $table->string('signature_type')->nullable(); // draw, upload, typed
            $table->string('signature_path')->nullable();
            $table->dateTime('signed_at')->nullable();
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
