<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

class FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // Field umum
            ['field_key' => 'contract_value',    'field_label' => 'Nilai Kontrak (Rp)',   'field_type' => 'number',  'is_required' => true,  'is_active' => true],
            ['field_key' => 'payment_terms',     'field_label' => 'Syarat Pembayaran',    'field_type' => 'text',    'is_required' => false, 'is_active' => true],
            ['field_key' => 'delivery_location', 'field_label' => 'Lokasi Pengiriman',    'field_type' => 'text',    'is_required' => false, 'is_active' => true],
            ['field_key' => 'warranty_period',   'field_label' => 'Masa Garansi (bulan)', 'field_type' => 'number',  'is_required' => false, 'is_active' => true],
            ['field_key' => 'penalty_clause',    'field_label' => 'Klausul Penalti',      'field_type' => 'textarea', 'is_required' => false, 'is_active' => true],
            ['field_key' => 'governing_law',     'field_label' => 'Hukum yang Berlaku',   'field_type' => 'text',    'is_required' => false, 'is_active' => true],
            // Field khusus perjanjian kerja
            ['field_key' => 'position',          'field_label' => 'Jabatan',              'field_type' => 'text',    'is_required' => true,  'is_active' => true],
            ['field_key' => 'basic_salary',      'field_label' => 'Gaji Pokok (Rp)',      'field_type' => 'number',  'is_required' => true,  'is_active' => true],
            ['field_key' => 'work_location',     'field_label' => 'Lokasi Kerja',         'field_type' => 'text',    'is_required' => true,  'is_active' => true],
            ['field_key' => 'probation_period',  'field_label' => 'Masa Percobaan (hari)', 'field_type' => 'number',  'is_required' => false, 'is_active' => true],
            // Field khusus sewa
            ['field_key' => 'rental_price',      'field_label' => 'Harga Sewa (Rp/bln)', 'field_type' => 'number',  'is_required' => true,  'is_active' => true],
            ['field_key' => 'rental_object',     'field_label' => 'Objek Sewa',           'field_type' => 'text',    'is_required' => true,  'is_active' => true],
            ['field_key' => 'deposit_amount',    'field_label' => 'Uang Jaminan (Rp)',    'field_type' => 'number',  'is_required' => false, 'is_active' => true],
        ];

        foreach ($fields as $field) {
            FieldDefinition::firstOrCreate(['field_key' => $field['field_key']], $field);
        }
    }
}
