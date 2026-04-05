<?php

namespace Database\Seeders;

use App\Models\ContractCategory;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'manager@agreema.com')->first();
        $catKerja = ContractCategory::where('name', 'Perjanjian Kerja')->first();
        $catJasa  = ContractCategory::where('name', 'Perjanjian Jasa')->first();
        $catSewa  = ContractCategory::where('name', 'Perjanjian Sewa')->first();
        $catNda   = ContractCategory::where('name', 'Perjanjian Kerahasiaan (NDA)')->first();

        $templates = [
            [
                'name'        => 'Template Perjanjian Kerja Tetap',
                'category_id' => $catKerja->id,
                'is_active'   => true,
                'content'     => <<<'HTML'
<h2>PERJANJIAN KERJA TETAP</h2>
<p>Pada hari ini, <strong>{{start_date}}</strong>, telah disepakati Perjanjian Kerja antara:</p>
<p><strong>Pihak Pertama</strong>: {{party_1_name}}, selanjutnya disebut <em>Perusahaan</em>.</p>
<p><strong>Pihak Kedua</strong>: {{party_2_name}}, selanjutnya disebut <em>Karyawan</em>.</p>
<h3>Pasal 1 – Jabatan dan Penempatan</h3>
<p>Karyawan diterima bekerja sebagai <strong>{{position}}</strong> dan ditempatkan di <strong>{{work_location}}</strong>.</p>
<h3>Pasal 2 – Kompensasi</h3>
<p>Perusahaan memberikan gaji pokok sebesar <strong>Rp {{basic_salary}}</strong> per bulan.</p>
<h3>Pasal 3 – Masa Percobaan</h3>
<p>Karyawan menjalani masa percobaan selama <strong>{{probation_period}} hari</strong> terhitung sejak tanggal mulai kerja.</p>
<h3>Pasal 4 – Hukum yang Berlaku</h3>
<p>Perjanjian ini tunduk pada hukum <strong>{{governing_law}}</strong>.</p>
HTML,
            ],
            [
                'name'        => 'Template Perjanjian Kerja Kontrak (PKWT)',
                'category_id' => $catKerja->id,
                'is_active'   => true,
                'content'     => <<<'HTML'
<h2>PERJANJIAN KERJA WAKTU TERTENTU (PKWT)</h2>
<p>Perjanjian ini dibuat pada <strong>{{start_date}}</strong> dan berlaku hingga <strong>{{end_date}}</strong>.</p>
<p><strong>Pihak Pertama</strong>: {{party_1_name}} (Perusahaan)</p>
<p><strong>Pihak Kedua</strong>: {{party_2_name}} (Karyawan)</p>
<h3>Pasal 1 – Posisi dan Lokasi</h3>
<p>Karyawan dipekerjakan sebagai <strong>{{position}}</strong> di <strong>{{work_location}}</strong>.</p>
<h3>Pasal 2 – Gaji</h3>
<p>Gaji pokok: <strong>Rp {{basic_salary}}</strong> per bulan dengan syarat pembayaran <strong>{{payment_terms}}</strong>.</p>
HTML,
            ],
            [
                'name'        => 'Template Perjanjian Pengadaan Jasa',
                'category_id' => $catJasa->id,
                'is_active'   => true,
                'content'     => <<<'HTML'
<h2>PERJANJIAN PENGADAAN JASA</h2>
<p>Dibuat pada <strong>{{start_date}}</strong> antara:</p>
<p><strong>Pemberi Jasa</strong>: {{party_1_name}}</p>
<p><strong>Penerima Jasa</strong>: {{party_2_name}}</p>
<h3>Pasal 1 – Nilai Kontrak</h3>
<p>Total nilai kontrak sebesar <strong>Rp {{contract_value}}</strong> dengan syarat pembayaran <strong>{{payment_terms}}</strong>.</p>
<h3>Pasal 2 – Penalti</h3>
<p>{{penalty_clause}}</p>
HTML,
            ],
            [
                'name'        => 'Template Perjanjian Sewa Aset',
                'category_id' => $catSewa->id,
                'is_active'   => true,
                'content'     => <<<'HTML'
<h2>PERJANJIAN SEWA ASET</h2>
<p>Perjanjian sewa berlaku dari <strong>{{start_date}}</strong> hingga <strong>{{end_date}}</strong>.</p>
<p><strong>Pihak Penyewa</strong>: {{party_1_name}}</p>
<p><strong>Pihak Yang Menyewakan</strong>: {{party_2_name}}</p>
<h3>Pasal 1 – Objek Sewa</h3>
<p>Objek yang disewakan: <strong>{{rental_object}}</strong></p>
<h3>Pasal 2 – Harga Sewa</h3>
<p>Harga sewa sebesar <strong>Rp {{rental_price}}</strong> per bulan. Uang jaminan: <strong>Rp {{deposit_amount}}</strong>.</p>
HTML,
            ],
            [
                'name'        => 'Template Non-Disclosure Agreement (NDA)',
                'category_id' => $catNda->id,
                'is_active'   => true,
                'content'     => <<<'HTML'
<h2>PERJANJIAN KERAHASIAAN (NON-DISCLOSURE AGREEMENT)</h2>
<p>Perjanjian ini ditandatangani pada <strong>{{start_date}}</strong> oleh:</p>
<p><strong>Pihak Pengungkap</strong>: {{party_1_name}}</p>
<p><strong>Pihak Penerima</strong>: {{party_2_name}}</p>
<h3>Pasal 1 – Informasi Rahasia</h3>
<p>Pihak Penerima wajib menjaga kerahasiaan seluruh informasi yang diperoleh dari Pihak Pengungkap.</p>
<h3>Pasal 2 – Jangka Waktu</h3>
<p>Kewajiban kerahasiaan berlaku sejak <strong>{{start_date}}</strong> hingga <strong>{{end_date}}</strong>.</p>
<h3>Pasal 3 – Hukum yang Berlaku</h3>
<p>{{governing_law}}</p>
HTML,
            ],
        ];

        foreach ($templates as $template) {
            Template::firstOrCreate(
                ['name' => $template['name']],
                array_merge($template, ['created_by' => $admin->id])
            );
        }
    }
}
