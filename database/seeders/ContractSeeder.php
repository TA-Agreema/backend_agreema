<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractParty;
use App\Models\ContractSigner;
use App\Models\ContractStatusLog;
use App\Models\ContractVersion;
use App\Models\FieldDefinition;
use App\Models\Notification;
use App\Models\Party;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('email', 'manager@agreema.com')->first();
        $legal   = User::where('email', 'legal@agreema.com')->first();
        $signer  = User::where('email', 'manager@agreema.com')->first();

        $tplKerjaTetap = Template::where('name', 'Template Perjanjian Kerja Tetap')->first();
        $tplJasa       = Template::where('name', 'Template Perjanjian Pengadaan Jasa')->first();
        $tplSewa       = Template::where('name', 'Template Perjanjian Sewa Aset')->first();
        $tplNda        = Template::where('name', 'Template Non-Disclosure Agreement (NDA)')->first();

        $companyPT  = Party::whereHas('companyDetail', fn($q) => $q->where('company_name', 'PT Maju Bersama Indonesia'))->first();
        $companyCV  = Party::whereHas('companyDetail', fn($q) => $q->where('company_name', 'CV Teknologi Nusantara'))->first();
        $companySDU = Party::whereHas('companyDetail', fn($q) => $q->where('company_name', 'PT Solusi Digital Utama'))->first();
        $indHendra  = Party::whereHas('individualDetail', fn($q) => $q->where('full_name', 'Hendra Kusuma'))->first();
        $indRina    = Party::whereHas('individualDetail', fn($q) => $q->where('full_name', 'Rina Wulandari'))->first();
        $indDoni    = Party::whereHas('individualDetail', fn($q) => $q->where('full_name', 'Doni Setiawan'))->first();

        // ── Kontrak 1: Perjanjian Kerja Tetap (Active) ───────
        $c1 = Contract::create([
            'contract_number'    => 'PKT-2024-001',
            'title'              => 'Perjanjian Kerja Tetap – Hendra Kusuma',
            'start_date'         => '2024-01-15',
            'end_date'           => null,
            'status'             => 'active',
            'template_id'        => $tplKerjaTetap->id,
            'created_by'         => $manager->id,
            'parent_contract_id' => null,
        ]);
        $cv1 = ContractVersion::create([
            'contract_id'    => $c1->id,
            'version_number' => 1,
            'content'        => $tplKerjaTetap->content,
            'created_by'     => $manager->id,
        ]);
        $this->fillFields($cv1->id, [
            'position'        => 'Senior Software Engineer',
            'work_location'   => 'Jakarta Pusat',
            'basic_salary'    => '15000000',
            'probation_period' => '90',
            'governing_law'   => 'Hukum Republik Indonesia',
        ]);
        ContractParty::create(['contract_id' => $c1->id, 'party_id' => $companyPT->id, 'party_order' => 1, 'role_description' => 'Pihak Pertama (Perusahaan)']);
        ContractParty::create(['contract_id' => $c1->id, 'party_id' => $indHendra->id,  'party_order' => 2, 'role_description' => 'Pihak Kedua (Karyawan)']);
        ContractSigner::create([
            'contract_id'    => $c1->id,
            'user_id'        => $signer->id,
            'signer_type'    => 'internal',
            'sequence'       => 1,
            'review_status'  => 'approved',
            'sign_status'    => 'signed',
            'signature_type' => 'draw',
            'signed_at'      => now()->subDays(30),
        ]);
        ContractStatusLog::create(['contract_id' => $c1->id, 'old_status' => 'draft',  'new_status' => 'review', 'changed_by' => $manager->id]);
        ContractStatusLog::create(['contract_id' => $c1->id, 'old_status' => 'review', 'new_status' => 'active', 'changed_by' => $legal->id]);

        // ── Kontrak 2: Pengadaan Jasa (Under Review) ─────────
        $c2 = Contract::create([
            'contract_number' => 'PJ-2024-002',
            'title'           => 'Pengadaan Jasa IT Support – CV Teknologi Nusantara',
            'start_date'      => '2024-03-01',
            'end_date'        => '2025-02-28',
            'status'          => 'review',
            'template_id'     => $tplJasa->id,
            'created_by'      => $manager->id,
        ]);
        $cv2 = ContractVersion::create([
            'contract_id'    => $c2->id,
            'version_number' => 1,
            'content'        => $tplJasa->content,
            'created_by'     => $manager->id,
        ]);
        $this->fillFields($cv2->id, [
            'contract_value' => '120000000',
            'payment_terms'  => 'Net 30 hari setelah invoice diterima',
            'penalty_clause' => 'Keterlambatan penyelesaian pekerjaan dikenakan denda 0,1% per hari dari nilai kontrak.',
            'governing_law'  => 'Hukum Republik Indonesia',
        ]);
        ContractParty::create(['contract_id' => $c2->id, 'party_id' => $companyPT->id, 'party_order' => 1, 'role_description' => 'Pihak Pertama (Pemberi Pekerjaan)']);
        ContractParty::create(['contract_id' => $c2->id, 'party_id' => $companyCV->id, 'party_order' => 2, 'role_description' => 'Pihak Kedua (Penyedia Jasa)']);
        ContractSigner::create([
            'contract_id'   => $c2->id,
            'user_id'       => $legal->id,
            'signer_type'   => 'internal',
            'sequence'      => 1,
            'review_status' => 'pending',
            'sign_status'   => 'pending',
        ]);
        ContractStatusLog::create(['contract_id' => $c2->id, 'old_status' => 'draft', 'new_status' => 'review', 'changed_by' => $manager->id]);
        Notification::create([
            'user_id'     => $legal->id,
            'contract_id' => $c2->id,
            'type'        => 'review_needed',
            'message'     => 'Kontrak PJ-2024-002 memerlukan review Anda.',
            'is_read'     => false,
        ]);

        // ── Kontrak 3: Sewa Aset (Draft) ─────────────────────
        $c3 = Contract::create([
            'contract_number' => 'PSA-2024-003',
            'title'           => 'Perjanjian Sewa Gedung Kantor – PT Solusi Digital Utama',
            'start_date'      => '2024-06-01',
            'end_date'        => '2027-05-31',
            'status'          => 'draft',
            'template_id'     => $tplSewa->id,
            'created_by'      => $manager->id,
        ]);
        $cv3 = ContractVersion::create([
            'contract_id'    => $c3->id,
            'version_number' => 1,
            'content'        => $tplSewa->content,
            'created_by'     => $manager->id,
        ]);
        $this->fillFields($cv3->id, [
            'rental_object' => 'Gedung Perkantoran lt. 5, Jl. Thamrin No. 8 Jakarta',
            'rental_price'  => '25000000',
            'deposit_amount' => '75000000',
        ]);
        ContractParty::create(['contract_id' => $c3->id, 'party_id' => $companyPT->id,  'party_order' => 1, 'role_description' => 'Pihak Penyewa']);
        ContractParty::create(['contract_id' => $c3->id, 'party_id' => $companySDU->id, 'party_order' => 2, 'role_description' => 'Pihak Yang Menyewakan']);

        // ── Kontrak 4: NDA (Active, dengan versi 2) ──────────
        $c4 = Contract::create([
            'contract_number' => 'NDA-2024-004',
            'title'           => 'Non-Disclosure Agreement – Doni Setiawan',
            'start_date'      => '2024-02-01',
            'end_date'        => '2026-01-31',
            'status'          => 'active',
            'template_id'     => $tplNda->id,
            'created_by'      => $manager->id,
        ]);
        $cv4a = ContractVersion::create([
            'contract_id'    => $c4->id,
            'version_number' => 1,
            'content'        => $tplNda->content,
            'created_by'     => $manager->id,
        ]);
        $this->fillFields($cv4a->id, [
            'governing_law' => 'Hukum Republik Indonesia',
        ]);
        // Versi 2 setelah revisi
        $cv4b = ContractVersion::create([
            'contract_id'    => $c4->id,
            'version_number' => 2,
            'content'        => $tplNda->content . "\n<p><em>Revisi: Cakupan informasi rahasia diperluas.</em></p>",
            'created_by'     => $legal->id,
        ]);
        $this->fillFields($cv4b->id, [
            'governing_law' => 'Hukum Republik Indonesia – Revisi Feb 2024',
        ]);
        ContractParty::create(['contract_id' => $c4->id, 'party_id' => $companyPT->id, 'party_order' => 1, 'role_description' => 'Pihak Pengungkap']);
        ContractParty::create(['contract_id' => $c4->id, 'party_id' => $indDoni->id,   'party_order' => 2, 'role_description' => 'Pihak Penerima']);
        ContractSigner::create([
            'contract_id'    => $c4->id,
            'user_id'        => $signer->id,
            'signer_type'    => 'internal',
            'sequence'       => 1,
            'review_status'  => 'approved',
            'sign_status'    => 'signed',
            'signature_type' => 'typed',
            'signed_at'      => now()->subDays(60),
        ]);
        // External signer (Doni tidak punya akun)
        ContractSigner::create([
            'contract_id'   => $c4->id,
            'party_id'      => $indDoni->id,
            'external_email' => 'doni.setiawan@email.com',
            'signer_type'   => 'external',
            'sequence'      => 2,
            'review_status' => 'approved',
            'sign_status'   => 'signed',
            'signature_type' => 'draw',
            'signed_at'     => now()->subDays(58),
        ]);
        ContractStatusLog::create(['contract_id' => $c4->id, 'old_status' => 'draft',  'new_status' => 'review', 'changed_by' => $manager->id]);
        ContractStatusLog::create(['contract_id' => $c4->id, 'old_status' => 'review', 'new_status' => 'active', 'changed_by' => $legal->id]);

        // ── Kontrak 5: Perjanjian Kerja (Expired) ────────────
        $c5 = Contract::create([
            'contract_number' => 'PKWT-2023-001',
            'title'           => 'PKWT – Rina Wulandari (Proyek Alpha)',
            'start_date'      => '2023-01-01',
            'end_date'        => '2023-12-31',
            'status'          => 'expired',
            'template_id'     => $tplKerjaTetap->id,
            'created_by'      => $manager->id,
        ]);
        $cv5 = ContractVersion::create([
            'contract_id'    => $c5->id,
            'version_number' => 1,
            'content'        => $tplKerjaTetap->content,
            'created_by'     => $manager->id,
        ]);
        $this->fillFields($cv5->id, [
            'position'      => 'Project Coordinator',
            'work_location' => 'Bandung',
            'basic_salary'  => '8000000',
            'governing_law' => 'Hukum Republik Indonesia',
        ]);
        ContractParty::create(['contract_id' => $c5->id, 'party_id' => $companyCV->id, 'party_order' => 1, 'role_description' => 'Pihak Pertama (Perusahaan)']);
        ContractParty::create(['contract_id' => $c5->id, 'party_id' => $indRina->id,   'party_order' => 2, 'role_description' => 'Pihak Kedua (Karyawan)']);
        ContractStatusLog::create(['contract_id' => $c5->id, 'old_status' => 'active', 'new_status' => 'expired', 'changed_by' => $manager->id]);
    }

    /**
     * Helper: isi field values berdasarkan field_key => value.
     */
    private function fillFields(int $versionId, array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            $field = FieldDefinition::where('field_key', $key)->first();
            if ($field) {
                \App\Models\ContractFieldValue::create([
                    'contract_version_id' => $versionId,
                    'field_definition_id' => $field->id,
                    'value'               => $value,
                ]);
            }
        }
    }
}
