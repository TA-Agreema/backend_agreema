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
            ['field_key' => 'nilai_kontrak', 'field_label' => 'Nilai Kontrak', 'field_type' => 'number', 'is_required' => true, 'is_active' => true],
            ['field_key' => 'status_pembayaran', 'field_label' => 'Status Pembayaran', 'field_type' => 'text', 'is_required' => true, 'is_active' => true],
        ];

        foreach ($fields as $field) {
            FieldDefinition::firstOrCreate(['field_key' => $field['field_key']], $field);
        }
    }
}
