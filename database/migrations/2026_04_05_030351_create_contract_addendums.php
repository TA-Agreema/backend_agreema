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
        Schema::create('contract_addendums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('addendum_number');
            $table->text('description')->nullable();
            $table->string('document_path')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('status')->default('draft'); // draft, approved, rejected
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_addendums');
    }
};
