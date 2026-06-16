@component('mail::message')
# Kontrak Telah Disahkan

Halo, **{{ $recipientName }}**!

Kami ingin memberitahukan bahwa kontrak berikut telah resmi **disahkan** dan akan berlaku pada tanggal yang telah ditentukan sesuai di bawah ini:

@component('mail::table')
| | |
|:---|:---|
| **Judul Kontrak** | {{ $contract->title }} |
| **Nomor Kontrak** | {{ $contract->contract_number ?? '-' }} |
| **Tanggal Disahkan** | {{ now()->locale('id')->isoFormat('D MMMM YYYY') }} |
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

Harap pastikan untuk membaca kembali isi kontrak dan menyimpan email ini sebagai bukti bahwa kontrak telah sah.

Terima kasih telah bekerja sama dengan kami.

Salam,<br>
**PT. Solutionlabs Grup Indonesia**

---
<small>Email ini dikirim secara otomatis. Mohon tidak membalas email ini.</small>
@endcomponent
