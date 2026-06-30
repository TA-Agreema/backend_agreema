<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Kontrak Kedaluwarsa</title>
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
        .info-box { background: #fecaca; border: 1px solid #b91c1c; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; color: #374151; }
        .info-box strong { color: #b91c1c; }

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
            <h1>⚠️ Kontrak Telah Kedaluwarsa</h1>
        </div>

        <div class="body">
            <p>Yth. Bapak/Ibu {{ $recipientName ?? 'Pengguna' }},</p>

            @if($role === 'creator')
                <p>
                    Kami ingin memberitahukan bahwa kontrak yang Bapak/Ibu buat berikut ini telah melewati
                    tanggal berakhirnya dan statusnya kini berubah menjadi <strong>Kedaluwarsa</strong>.
                    Sebagai pembuat kontrak, mohon segera melakukan tindak lanjut perpanjangan apabila diperlukan.
                </p>
            @elseif($role === 'internal')
                <p>
                    Kami ingin memberitahukan bahwa kontrak berikut, yang telah Bapak/Ibu setujui dan tanda tangani,
                    telah melewati tanggal berakhirnya dan statusnya kini <strong>Kedaluwarsa</strong>.
                    Apabila diperlukan perpanjangan, mohon menghubungi pembuat kontrak terkait untuk tindak lanjut.
                </p>
            @else
                <p>
                    Kami ingin memberitahukan bahwa kontrak kerja sama berikut antara Bapak/Ibu dengan kami
                    telah melewati tanggal berakhirnya. Apabila Bapak/Ibu berkenan untuk memperpanjang
                    kerja sama ini, mohon menghubungi kontak kami agar dapat segera ditindaklanjuti.
                </p>
            @endif

            <div class="info-box">
                <p><strong>Judul Kontrak</strong></p>
                <p>{{ $contract->title }}</p>
                <br/>
                <p><strong>Nomor Kontrak</strong></p>
                <p>{{ $contract->contract_number }}</p>
                <br/>
                <p><strong>Tanggal Berakhir</strong></p>
                <p>{{ $endDate }}</p>
            </div>

            @if($role === 'external')
                <p>
                    Untuk informasi lebih lanjut, silakan menghubungi tim kami secara langsung,
                    karena akses sistem hanya tersedia untuk pihak internal.
                </p>
            @else
                <p>
                    Silakan login ke sistem Agreema untuk melihat detail kontrak dan melakukan
                    tindak lanjut yang diperlukan.
                </p>
            @endif

            <p>Terima kasih atas perhatian dan kerja samanya.</p>
        </div>

        <div class="footer">
            <p class="company">PT. Solutionlab Grup Indonesia</p>
            <p>Email ini dikirim otomatis oleh sistem Agreema. Mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>