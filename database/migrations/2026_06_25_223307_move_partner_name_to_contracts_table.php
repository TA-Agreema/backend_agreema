<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'partner_name')) {
                $table->string('partner_name')->nullable()->after('title');
            }
        });

        if (
            Schema::hasTable('contract_parties') &&
            Schema::hasTable('parties') &&
            Schema::hasTable('party_company_details') &&
            Schema::hasTable('party_individual_details')
        ) {
            $partners = DB::table('contract_parties')
                ->leftJoin('parties', 'contract_parties.party_id', '=', 'parties.id')
                ->leftJoin('party_company_details', 'parties.id', '=', 'party_company_details.party_id')
                ->leftJoin('party_individual_details', 'parties.id', '=', 'party_individual_details.party_id')
                ->select(
                    'contract_parties.contract_id',
                    'contract_parties.party_order',
                    'party_company_details.company_name',
                    'party_individual_details.full_name'
                )
                ->orderByDesc('contract_parties.party_order')
                ->get();

            foreach ($partners as $partner) {
                $partnerName = $partner->company_name ?: $partner->full_name;

                if (!$partnerName) {
                    continue;
                }

                DB::table('contracts')
                    ->where('id', $partner->contract_id)
                    ->whereNull('partner_name')
                    ->update(['partner_name' => $partnerName]);
            }
        }

        Schema::dropIfExists('contract_parties');
        Schema::dropIfExists('party_company_details');
        Schema::dropIfExists('party_individual_details');
        Schema::dropIfExists('parties');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->enum('party_type', ['individual', 'company']);
            $table->timestamps();
        });

        Schema::create('party_individual_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('identity_number')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('party_company_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('company_name');
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->integer('party_order')->default(1);
            $table->string('role_description')->nullable();
            $table->timestamps();
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'partner_name')) {
                $table->dropColumn('partner_name');
            }
        });
    }
};