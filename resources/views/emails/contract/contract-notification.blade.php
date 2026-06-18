@component('mail::message')
# {{ $label }}

Halo **{{ $recipientName }}**,

{{ $message }}

@if($contractUrl)
@component('mail::button', ['url' => $contractUrl, 'color' => 'green'])
Lihat Kontrak
@endcomponent
@endif

Terima kasih,
**Tim Agreema**
@endcomponent
