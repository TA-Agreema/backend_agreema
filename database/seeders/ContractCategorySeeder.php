<?php

namespace Database\Seeders;

use App\Models\ContractCategory;
use Illuminate\Database\Seeder;

class ContractCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Perjanjian Kerja',
                'description' => 'Kontrak hubungan kerja antara perusahaan dan karyawan.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Perjanjian Jasa',
                'description' => 'Kontrak pengadaan layanan atau jasa dari pihak ketiga.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Perjanjian Sewa',
                'description' => 'Kontrak sewa aset, properti, atau peralatan.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Perjanjian Jual Beli',
                'description' => 'Kontrak pengadaan barang atau pembelian aset.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Nota Kesepahaman (MoU)',
                'description' => 'Dokumen kesepakatan awal sebelum perjanjian resmi ditandatangani.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Perjanjian Kerahasiaan (NDA)',
                'description' => 'Kontrak untuk menjaga kerahasiaan informasi sensitif.',
                'is_active'   => true,
            ],
        ];

        foreach ($categories as $category) {
            ContractCategory::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
