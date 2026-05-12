<x-mail::message>
# Permintaan Review & Penandatanganan Kontrak

Yth. Bapak/Ibu,

Anda menerima email ini karena Anda terdaftar sebagai pihak yang perlu menyetujui kontrak berikut:

**Nomor Kontrak:** {{ $contract->contract_number }}
**Judul:** {{ $contract->title }}
**Iterasi Review:** {{ $iteration }}

Silakan klik tombol di bawah ini untuk membuka dan mereview kontrak. Anda akan diarahkan ke halaman review di platform kami.

<x-mail::button :url="$signingUrl" color="success">
Review & Tanda Tangani Kontrak
</x-mail::button>

> **Perhatian:** Link ini bersifat rahasia dan hanya dapat digunakan **satu kali**. Link akan kadaluarsa dalam **7 hari**. Jangan bagikan link ini kepada siapa pun.

Jika Anda merasa tidak semestinya menerima email ini, abaikan pesan ini.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
