<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Migrasi tambahan untuk kolom ukuran kertas
        Schema::table('templates', function (Blueprint $table) {
            $table->string('paper_size', 10)->default('a4')->after('content');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('paper_size', 10)->default('a4')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('paper_size');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('paper_size');
        });
    }
};
