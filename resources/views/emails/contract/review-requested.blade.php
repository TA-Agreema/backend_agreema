<x-mail::message>
# Kontrak Membutuhkan Review Anda

Yth. Bapak/Ibu,

Sebuah kontrak baru telah disubmit oleh HRD dan membutuhkan review Anda sebelum diteruskan ke pihak kedua.

**Nomor Kontrak:** {{ $contract->contract_number }}
**Judul:** {{ $contract->title }}
**Dibuat oleh:** {{ $contract->creator?->name ?? '-' }}
**Tanggal Submit:** {{ now()->format('d-m-Y H:i') }}

Silakan login ke platform untuk mereview kontrak ini.

<x-mail::button :url="$reviewUrl" color="primary">
Buka & Review Kontrak
</x-mail::button>

> **Catatan:** Anda dapat menyetujui kontrak atau meminta revisi kepada HRD disertai catatan perbaikan.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
