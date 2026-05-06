<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

class FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // FIELD UMUM
            ['field_key' => 'nomor_kontrak',   'field_label' => 'Nomor Kontrak',   'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'judul_kontrak',   'field_label' => 'Judul Kontrak',   'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'tanggal_dibuat',  'field_label' => 'Tanggal Dibuat',  'field_type' => 'date', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'tanggal_mulai',   'field_label' => 'Tanggal Mulai',   'field_type' => 'date', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'tanggal_selesai', 'field_label' => 'Tanggal Selesai', 'field_type' => 'date', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'tempat_dibuat',   'field_label' => 'Tempat Dibuat',   'field_type' => 'text', 'is_required' => true, 'is_active' => true],

            // DATA PIHAK
            ['field_key' => 'pihak_1_nama',           'field_label' => 'Pihak 1 - Nama',           'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_1_alamat',         'field_label' => 'Pihak 1 - Alamat',         'field_type' => 'textarea', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_1_penandatangan', 'field_label' => 'Pihak 1 - Penandatangan', 'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_1_jabatan',        'field_label' => 'Pihak 1 - Jabatan',        'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_2_nama',           'field_label' => 'Pihak 2 - Nama',           'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_2_alamat',         'field_label' => 'Pihak 2 - Alamat',         'field_type' => 'textarea', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_2_penandatangan', 'field_label' => 'Pihak 2 - Penandatangan', 'field_type' => 'text', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'pihak_2_jabatan',        'field_label' => 'Pihak 2 - Jabatan',        'field_type' => 'text', 'is_required' => true, 'is_active' => true],
        ];

        foreach ($fields as $field) {
            FieldDefinition::firstOrCreate(['field_key' => $field['field_key']], $field);
        }
    }
}
