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
        Schema::create('contract_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_version_id')->constrained('contract_versions')->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained('fields_definitions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['contract_version_id', 'field_definition_id'], 'cfv_contract_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_field_values');
    }
};
