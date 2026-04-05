<?php

namespace Database\Seeders;

use App\Models\Party;
use App\Models\PartyCompanyDetail;
use App\Models\PartyIndividualDetail;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    public function run(): void
    {
        // ── Perusahaan ───────────────────────────────────────
        $companies = [
            [
                'company_name' => 'PT Maju Bersama Indonesia',
                'address'      => 'Jl. Sudirman No. 45, Jakarta Pusat, DKI Jakarta 10220',
            ],
            [
                'company_name' => 'CV Teknologi Nusantara',
                'address'      => 'Jl. Gatot Subroto No. 12, Bandung, Jawa Barat 40111',
            ],
            [
                'company_name' => 'PT Solusi Digital Utama',
                'address'      => 'Jl. Thamrin No. 8, Jakarta Pusat, DKI Jakarta 10340',
            ],
        ];

        foreach ($companies as $company) {
            $party = Party::create(['party_type' => 'company']);
            PartyCompanyDetail::create(array_merge(['party_id' => $party->id], $company));
        }

        // ── Individu ─────────────────────────────────────────
        $individuals = [
            [
                'full_name'       => 'Hendra Kusuma',
                'identity_number' => '3174012501880001',
                'birth_place'     => 'Jakarta',
                'birth_date'      => '1988-01-25',
                'gender'          => 'male',
                'phone'           => '081234567890',
                'email'           => 'hendra.kusuma@email.com',
            ],
            [
                'full_name'       => 'Rina Wulandari',
                'identity_number' => '3578016508920002',
                'birth_place'     => 'Surabaya',
                'birth_date'      => '1992-08-15',
                'gender'          => 'female',
                'phone'           => '082345678901',
                'email'           => 'rina.wulandari@email.com',
            ],
            [
                'full_name'       => 'Doni Setiawan',
                'identity_number' => '3271030303950003',
                'birth_place'     => 'Bandung',
                'birth_date'      => '1995-03-03',
                'gender'          => 'male',
                'phone'           => '083456789012',
                'email'           => 'doni.setiawan@email.com',
            ],
        ];

        foreach ($individuals as $individual) {
            $party = Party::create(['party_type' => 'individual']);
            PartyIndividualDetail::create(array_merge(['party_id' => $party->id], $individual));
        }
    }
}
