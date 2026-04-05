<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('field_key')->unique();
            $table->string('field_label');
            $table->string('field_type'); // text, number, date, select, etc.
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields_definitions');
    }
};
