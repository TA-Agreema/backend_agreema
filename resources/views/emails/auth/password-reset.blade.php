<x-mail::message>
# Reset Password Akun Agreema

Yth. {{ $user->name }},

Kami menerima permintaan untuk mengatur ulang password akun Agreema Anda.
Silakan tekan tombol berikut untuk membuat password baru.

<x-mail::button :url="$resetUrl" color="primary">
Reset Password
</x-mail::button>

Link ini berlaku selama 60 menit. Jika Anda tidak merasa meminta reset password,
abaikan email ini.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
