<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateContractSigners extends Migration
{
    public function up(): void
    {
        Schema::table('contract_signers', function (Blueprint $table) {
            // drop kolom lama
            $table->dropForeign(['party_id']);
            $table->dropColumn('review_status');
            $table->dropColumn('review_note');
            $table->dropColumn('reviewed_at');
            $table->dropColumn('sign_status');
            $table->dropColumn('signature_type');
            $table->dropColumn('signature_path');
            $table->dropColumn('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('contract_signers', function (Blueprint $table) {
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('review_status')->default('pending'); // pending, approved, rejected
            $table->text('review_note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('sign_status')->default('pending'); // pending, signed, rejected
            $table->string('signature_type')->nullable(); // draw, upload, typed
            $table->string('signature_path')->nullable();
            $table->dateTime('signed_at')->nullable();
        });
    }
}
