<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Kontrak Membutuhkan Review</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        .brand { padding: 24px 32px 16px; text-align: center; }
        .brand-logo-img { width: 170px; height: auto; display: inline-block; }
        .brand-logo-fallback { width: 40px; height: 40px; background: #047857; border-radius: 8px; text-align: center; line-height: 40px; color: #ffffff; font-size: 20px; font-weight: bold; }

        .header { background: #047857; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 19px; }

        .body { padding: 32px; color: #374151; }
        .body p { margin: 0 0 16px; line-height: 1.6; }

        .info-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; color: #374151; }
        .info-box strong { color: #047857; }

        .button-wrap { text-align: center; margin: 24px 0; }
        .button { display: inline-block; background: #047857; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; font-size: 14px; }

        .note { background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #92400e; margin: 16px 0; }

        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 20px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
        .footer .company { font-weight: bold; color: #6b7280; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="brand">
            @if(file_exists(public_path('LogoAgreema.png')))
                <img src="{{ $message->embed(public_path('LogoAgreema.png')) }}" alt="Agreema" class="brand-logo-img">
            @else
                <div class="brand-logo-fallback">Agreema</div>
            @endif
        </div>

        <div class="header">
            <h1>📋 Kontrak Membutuhkan Review Anda</h1>
        </div>

        <div class="body">
            <p>Yth. Bapak/Ibu {{ $recipientName ?? 'Pengguna' }},</p>

            <p>
                Sebuah kontrak baru telah disubmit oleh HRD dan membutuhkan review Bapak/Ibu
                sebelum diteruskan ke pihak kedua.
            </p>

            <div class="info-box">
                <p><strong>Nomor Kontrak</strong></p>
                <p>{{ $contract->contract_number }}</p>
                <br/>
                <p><strong>Judul</strong></p>
                <p>{{ $contract->title }}</p>
                <br/>
                <p><strong>Dibuat oleh</strong></p>
                <p>{{ $contract->creator?->name ?? '-' }}</p>
                <br/>
                <p><strong>Tanggal Submit</strong></p>
                <p>{{ now()->format('d-m-Y H:i') }}</p>
            </div>

            <div class="button-wrap">
                <a href="{{ $reviewUrl }}" class="button">Buka &amp; Review Kontrak</a>
            </div>

            <div class="note">
                <strong>Catatan:</strong> Anda dapat menyetujui kontrak atau meminta revisi kepada HRD disertai catatan perbaikan.
            </div>

            <p>Terima kasih atas perhatian dan kerja samanya.</p>
        </div>

        <div class="footer">
            <p class="company">PT. Solutionlab Grup Indonesia</p>
            <p>Email ini dikirim otomatis oleh sistem Agreema. Mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>
