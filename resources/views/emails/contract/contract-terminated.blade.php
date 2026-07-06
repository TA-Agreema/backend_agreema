<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Kontrak Resmi Dihentikan</title>
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
        .badge { display: inline-block; background: #fec7c7; color: #d90606; font-weight: bold; padding: 4px 12px; border-radius: 999px; font-size: 14px; margin-bottom: 16px; }

        .info-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; color: #374151; }
        .info-box strong { color: #047857; }

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
            <h1>⚠️ Pemberitahuan Terminasi Kontrak</h1>
        </div>

        <div class="body">
            <p>Yth. Bapak/Ibu {{ $recipientName }}</p>

            <span class="badge">Kontrak Resmi Dihentikan</span>

            <p>
                Kami ingin memberitahukan bahwa kontrak berikut telah resmi dihentikan
                sesuai dengan tanggal efektif terminasi yang telah ditetapkan.
            </p>

            <div class="info-box">

                <p><strong>Judul Kontrak</strong></p>
                <p>{{ $contract->title }}</p>

                <br>

                <p><strong>Nomor Kontrak</strong></p>
                <p>{{ $contract->contract_number }}</p>

                <br>

                <p><strong>Tanggal Terminasi</strong></p>
                <p>{{ $effectiveDate }}</p>

                <br>

                <p><strong>Alasan Terminasi</strong></p>
                <p>{{ $reason }}</p>

            </div>

            <p>
                Per tanggal
                <strong>{{ $effectiveDate }}</strong>,
                kontrak tersebut telah berstatus
                <strong>dihentikan</strong> dan tidak lagi berlaku.
            </p>

            <p>
                Seluruh hak dan kewajiban yang timbul berdasarkan kontrak ini
                berakhir sesuai ketentuan yang berlaku serta kesepakatan para pihak.
            </p>

            <p>
                Apabila memerlukan informasi lebih lanjut terkait terminasi kontrak ini,
                silakan menghubungi pihak yang mengajukan kontrak.
            </p>

            <p>
                Terima kasih atas perhatian dan kerja samanya.
            </p>

        <div class="footer">
            <p class="company">PT. Solutionlab Grup Indonesia</p>
            <p>Email ini dikirim otomatis oleh sistem Agreema. Mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>
