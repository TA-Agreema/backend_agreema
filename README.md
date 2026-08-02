# Agreema Backend

Backend Agreema adalah REST API berbasis Laravel untuk mengelola siklus hidup kontrak perusahaan, mulai dari pembuatan draft, review manager, revisi, approval, tanda tangan, aktivasi, addendum, terminasi, notifikasi, sampai laporan dashboard.

Project ini digunakan oleh frontend `frontend-agreema` melalui endpoint `/api` dan autentikasi token Laravel Sanctum.

## Fitur Utama

- Autentikasi user dengan login, logout, forgot password, dan reset password.
- Role dan permission berbasis Spatie Laravel Permission.
- Manajemen user, role, dan permission untuk admin.
- Manajemen kategori kontrak dan template kontrak.
- Editor kontrak berbasis HTML content, field dinamis, ukuran kertas A4/F4, dan versi kontrak.
- Review kontrak oleh manager dengan alur approve, reject, request revision, dan catatan.
- Versioning otomatis ketika kontrak dibuat atau disubmit ulang.
- Penandatangan internal dan eksternal, termasuk token akses public untuk pihak eksternal.
- Download dokumen kontrak/template ke PDF menggunakan DomPDF.
- Pengelolaan addendum untuk kontrak aktif.
- Pengajuan terminasi untuk kontrak aktif.
- Notifikasi in-app dan email untuk status kontrak, review, tanda tangan, kontrak aktif, kontrak expired, kontrak expiring soon, dan terminasi.
- Dashboard berbeda untuk admin, HRD, manager, dan user internal.
- Scheduler untuk aktivasi kontrak, terminasi otomatis, dan pengingat masa berlaku kontrak.

## Tech Stack

- PHP `^8.2`
- Laravel `^12.0`
- Laravel Sanctum `^4.0`
- Spatie Laravel Permission `^6.24`
- Barryvdh Laravel DomPDF `^3.1`
- SQLite atau MySQL/MariaDB
- Composer
- Node.js dan npm untuk asset Vite Laravel

## Struktur Folder Penting

```text
backend-agreema/
|-- app/
|   |-- Console/Commands/      # command scheduler kontrak
|   |-- Http/Controllers/API/  # controller REST API
|   |-- Http/Requests/         # validasi request
|   |-- Http/Resources/        # transformasi response API
|   |-- Mail/                  # template email notifikasi
|   |-- Models/                # model Eloquent
|   |-- Policies/              # policy otorisasi
|   |-- Services/              # service PDF dan notifikasi
|   `-- Support/               # helper pendukung PDF/content
|-- database/
|   |-- migrations/            # skema database
|   `-- seeders/               # data awal role, permission, user, kategori, field
|-- postman/                   # koleksi API Postman
|-- routes/
|   |-- api.php                # endpoint REST API
|   `-- console.php            # jadwal command
`-- storage/                   # file upload, log, cache, dan output storage Laravel
```

## Prasyarat

Pastikan sudah terpasang:

- PHP 8.2 atau lebih baru
- Composer
- Node.js dan npm
- SQLite atau MySQL/MariaDB
- Ekstensi PHP umum Laravel, seperti `pdo`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, dan `ctype`

## Instalasi

Masuk ke folder backend:

```bash
cd backend-agreema
```

Install dependency PHP:

```bash
composer install
```

Install dependency Node untuk asset Laravel:

```bash
npm install
```

Salin file environment:

```bash
Copy-Item .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Konfigurasi database di `.env`.

Contoh SQLite:

```env
DB_CONNECTION=sqlite
```

Lalu buat file database jika belum ada:

```bash
New-Item database/database.sqlite -ItemType File
```

Contoh MySQL/MariaDB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agreema
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migrasi dan seeder:

```bash
php artisan migrate --seed
```

Buat symbolic link storage agar file upload dapat diakses:

```bash
php artisan storage:link
```

Jalankan server:

```bash
php artisan serve
```

API akan berjalan di:

```text
http://localhost:8000/api
```

## Konfigurasi Environment

Variabel penting:

```env
APP_NAME=Agreema
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=sqlite

QUEUE_CONNECTION=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Catatan:

- `FRONTEND_URL` digunakan untuk link yang dikirim ke frontend, termasuk alur eksternal dan reset password.
- `MAIL_MAILER=log` cocok untuk development karena email masuk ke log Laravel.
- Untuk production, ubah konfigurasi mail sesuai SMTP yang digunakan.

## Akun Default Seeder

Seeder membuat tiga akun awal:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@agreema.com` | `password123` |
| Manager | `manager@agreema.com` | `password123` |
| HRD | `hrd@agreema.com` | `password123` |

Seeder yang dijalankan oleh `DatabaseSeeder`:

- `PermissionSeeder`
- `RoleSeeder`
- `UserSeeder`
- `ContractCategorySeeder`
- `FieldDefinitionSeeder`

## Script Composer

```bash
composer run setup
```

Menjalankan instalasi dependency, setup `.env`, generate key, migrasi, install npm, dan build asset.

```bash
composer run dev
```

Menjalankan Laravel server, queue listener, log pail, dan Vite secara bersamaan.

```bash
composer run test
```

Membersihkan config lalu menjalankan test Laravel.

## Endpoint API Utama

Base URL:

```text
/api
```

### Auth

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `POST` | `/login` | Login dan membuat token Sanctum |
| `POST` | `/forgot-password` | Mengirim permintaan reset password |
| `POST` | `/reset-password` | Reset password |
| `POST` | `/logout` | Logout user login |
| `GET` | `/me` | Mengambil profil user login |

Endpoint selain login, forgot password, reset password, dan external contract berada di middleware `auth:sanctum`.

### Dashboard dan Notifikasi

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/dashboard` | Data dashboard sesuai role user |
| `GET` | `/notifications` | Daftar notifikasi user |
| `PATCH` | `/notifications/read-all` | Tandai semua notifikasi sebagai dibaca |
| `PATCH` | `/notifications/{id}/read` | Tandai satu notifikasi sebagai dibaca |
| `DELETE` | `/notifications/{id}` | Hapus notifikasi |

### Admin

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/users` | Daftar user |
| `POST` | `/users/add-user` | Tambah user |
| `GET` | `/users/show-user/{id}` | Detail user |
| `PATCH` | `/users/update-user/{id}` | Update user |
| `DELETE` | `/users/delete-user/{id}` | Hapus user |
| `PATCH` | `/users/update-user-roles/{id}` | Update role user |
| `GET` | `/users/roles` | Daftar role |
| `POST` | `/users/add-roles` | Tambah role |
| `GET` | `/users/show-roles/{id}` | Detail role |
| `PATCH` | `/users/update-roles/{id}` | Update role |
| `DELETE` | `/users/delete-roles/{id}` | Hapus role |
| `GET` | `/users/permissions` | Daftar permission |

### Kategori, Template, dan Field Kontrak

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/category` | Daftar kategori |
| `POST` | `/category` | Tambah kategori |
| `GET` | `/category/{id}` | Detail kategori |
| `PATCH` | `/category/{id}` | Update kategori |
| `DELETE` | `/category/{id}` | Hapus kategori |
| `PATCH` | `/category/{id}/toggle-status` | Aktif/nonaktif kategori |
| `GET` | `/templates` | Daftar template |
| `POST` | `/templates` | Tambah template |
| `GET` | `/templates/{id}` | Detail template |
| `PATCH` | `/templates/{id}` | Update template |
| `DELETE` | `/templates/{id}` | Hapus template |
| `GET` | `/templates/{id}/download` | Download template PDF |
| `PATCH` | `/templates/{id}/toggle-status` | Aktif/nonaktif template |
| `GET` | `/field-definitions` | Daftar field dinamis |
| `POST` | `/field-definitions` | Tambah field dinamis |
| `PATCH` | `/field-definitions/{id}` | Update field dinamis |
| `DELETE` | `/field-definitions/{id}` | Hapus field dinamis |

### Kontrak HRD

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/contracts/generate-number` | Generate nomor kontrak |
| `GET` | `/contracts` | Daftar kontrak |
| `POST` | `/contracts` | Buat kontrak |
| `GET` | `/contracts/{id}` | Detail kontrak |
| `PATCH` | `/contracts/{id}` | Update kontrak |
| `DELETE` | `/contracts/{id}` | Hapus kontrak |
| `POST` | `/contracts/{id}/submit` | Submit kontrak ke review |
| `PATCH` | `/contracts/{id}/toggle-status` | Ubah status kontrak |
| `GET` | `/contracts/{id}/download` | Download kontrak PDF |
| `POST` | `/contracts/{id}/resend-signing` | Kirim ulang token tanda tangan eksternal |
| `GET` | `/partners` | Daftar pihak/partner |
| `GET` | `/signers/internal` | Daftar penandatangan internal |
| `GET` | `/partner-contracts` | Daftar kontrak mitra eksternal |
| `POST` | `/partner-contracts` | Tambah kontrak mitra eksternal |
| `DELETE` | `/partner-contracts/{id}` | Hapus kontrak mitra eksternal |

### Review Manager

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/manager/contracts` | Daftar kontrak untuk manager |
| `GET` | `/manager/contracts/archive` | Arsip review manager |
| `GET` | `/manager/contracts/{id}` | Detail review kontrak |
| `POST` | `/manager/contracts/{id}/review` | Approve, request revision, atau reject |
| `POST` | `/manager/contracts/{id}/sign` | Tanda tangan manager |
| `POST` | `/manager/contracts/{id}/upload-signed` | Upload dokumen yang sudah ditandatangani |
| `GET` | `/manager/contracts/{id}/download` | Download kontrak PDF |

### Addendum dan Terminasi

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/contracts/{id}/addendums` | Daftar addendum kontrak |
| `POST` | `/contracts/{id}/addendums` | Tambah addendum |
| `DELETE` | `/contracts/{contractId}/addendums/{addendumId}` | Hapus addendum |
| `GET` | `/contracts/{id}/terminations` | Daftar terminasi kontrak |
| `POST` | `/contracts/{id}/terminations` | Ajukan terminasi |
| `DELETE` | `/contracts/{contractId}/terminations/{terminationId}` | Hapus terminasi |

Validasi upload addendum dan terminasi pada implementasi saat ini:

- File wajib diisi.
- Tipe file: `pdf`.
- Ukuran maksimal: 10 MB.
- Nomor addendum dan nomor terminasi harus unik.

### External Contract

Endpoint ini tidak membutuhkan login dan digunakan oleh pihak eksternal melalui token/link.

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/external/contracts/preview` | Preview kontrak eksternal |
| `GET` | `/external/contracts/download` | Download PDF kontrak eksternal |
| `POST` | `/external/contracts/review` | Review pihak eksternal |
| `POST` | `/external/contracts/sign` | Tanda tangan pihak eksternal |

## Status Kontrak

Status kontrak yang digunakan:

- `draft`
- `review`
- `revision`
- `approved`
- `signed`
- `active`
- `expired`
- `terminated`
- `rejected`

Alur umum:

1. HRD membuat kontrak sebagai `draft`.
2. HRD submit kontrak menjadi `review`.
3. Manager melakukan review.
4. Jika manager meminta revisi, status menjadi `revision` dan HRD dapat submit ulang.
5. Setiap submit ulang membuat versi kontrak baru.
6. Jika disetujui, status menjadi `approved`.
7. Setelah ditandatangani, kontrak menjadi `signed`.
8. Scheduler mengaktifkan kontrak menjadi `active` ketika `start_date` tercapai.
9. Kontrak aktif dapat memiliki addendum atau pengajuan terminasi.
10. Scheduler mengubah kontrak menjadi `terminated` ketika tanggal efektif terminasi tercapai.

## Scheduler dan Queue

Jadwal command berada di `routes/console.php` dan berjalan setiap hari pukul 08:00 zona waktu `Asia/Jakarta`.

Command kontrak:

```bash
php artisan contracts:activate
php artisan contracts:terminate
php artisan contracts:notify-expired
php artisan contracts:notify-expiring
php artisan contracts:send-started-notifications
```

Untuk menjalankan scheduler lokal:

```bash
php artisan schedule:work
```

Untuk queue lokal:

```bash
php artisan queue:listen --tries=1
```

## Testing

Jalankan test:

```bash
php artisan test
```

Atau melalui composer script:

```bash
composer run test
```

## Integrasi Frontend

Frontend membaca API dari environment:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

Pastikan backend berjalan sebelum menjalankan frontend.

## Catatan Development

- Gunakan `php artisan migrate:fresh --seed` jika perlu reset database development.
- Setelah mengubah permission, jalankan ulang seeder atau command sinkronisasi permission bila diperlukan.
- File upload dan PDF disimpan melalui storage Laravel.
- Collection API Postman tersedia di folder `postman/`.
