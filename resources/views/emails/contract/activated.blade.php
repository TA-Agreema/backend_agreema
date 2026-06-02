@component('mail::message')
# Kontrak Telah Aktif

Halo **{{ $recipientName }}**,

Kami ingin memberitahukan bahwa kontrak berikut telah resmi **aktif** dan berlaku setelah semua pihak memberikan persetujuan.

@component('mail::table')
| | |
|:---|:---|
| **Judul Kontrak** | {{ $contract->title }} |
| **Nomor Kontrak** | {{ $contract->contract_number ?? '-' }} |
| **Tanggal Aktif** | {{ now()->locale('id')->isoFormat('D MMMM YYYY') }} |
@if($contract->start_date)
| **Mulai Berlaku** | {{ \Carbon\Carbon::parse($contract->start_date)->locale('id')->isoFormat('D MMMM YYYY') }} |
@endif
@if($contract->end_date)
| **Berakhir** | {{ \Carbon\Carbon::parse($contract->end_date)->locale('id')->isoFormat('D MMMM YYYY') }} |
@endif
@endcomponent

@if($contract->signed_document_path)
📎 **Dokumen kontrak yang telah ditandatangani terlampir** dalam email ini.
@endif

Harap simpan email ini sebagai bukti bahwa kontrak telah aktif dan berlaku bagi semua pihak.

Terima kasih telah menggunakan layanan kami.

Salam,<br>
Tim **{{ config('app.name') }}**

---
<small>Email ini dikirim secara otomatis. Mohon tidak membalas email ini.</small>
@endcomponent
