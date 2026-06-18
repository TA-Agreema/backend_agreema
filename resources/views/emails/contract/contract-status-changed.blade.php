<!DOCTYPE html>
<html>
<body style="font-family:sans-serif;background:#f9fafb;padding:32px;">
  <div style="max-width:480px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e5e7eb;">
    <h2 style="font-size:16px;color:#111827;margin-bottom:4px;">Status Kontrak Diperbarui</h2>
    <p style="color:#6b7280;font-size:14px;">
      Halo, <strong>{{ $recipientName ?? $actor->name }}</strong>.
    </p>
    <p style="color:#374151;font-size:14px;">
      {{ $notifMessage }}
    </p>
    <div style="margin:20px 0;padding:16px;background:#f3f4f6;border-radius:8px;">
      <p style="margin:4px 0;font-size:13px;color:#6b7280;">
        Nomor Kontrak: <strong style="color:#111827;">{{ $contract->contract_number ?? '-' }}</strong>
      </p>
      <p style="margin:4px 0;font-size:13px;color:#6b7280;">
        Judul: <strong style="color:#111827;">{{ $contract->title }}</strong>
      </p>
      <p style="margin:4px 0;font-size:13px;color:#6b7280;">
        Dibuat oleh: <strong style="color:#111827;">{{ $contract->creator->name ?? '-' }}</strong>
      </p>
      @php
        $externalSigners = $contract->signers->where('signer_type', 'external');
      @endphp
      @if($externalSigners->isNotEmpty())
      <p style="margin:4px 0;font-size:13px;color:#6b7280;">
        Mitra/Eksternal: <strong style="color:#111827;">{{ $externalSigners->map(fn($s) => $s->signer_name ?? $s->external_email)->join(', ') }}</strong>
      </p>
      @endif
    </div>
    @if($actionUrl)
    <a href="{{ $actionUrl }}"
       style="display:inline-block;background:#059669;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">
      Buka Kontrak
    </a>
    @endif
    <p style="font-size:11px;color:#9ca3af;margin-top:24px;">
      Email ini dikirim otomatis oleh sistem Agreema. Mohon tidak membalas.
    </p>
  </div>
</body>
</html>
