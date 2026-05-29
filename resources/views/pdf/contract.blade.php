<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $contract->title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            line-height: 1.7;
            padding: 40px 50px;
        }

        /* Header info kontrak */
        .contract-meta {
            border-bottom: 2px solid #059669;
            padding-bottom: 14px;
            margin-bottom: 24px;
        }

        .contract-meta h1 {
            font-size: 16px;
            font-weight: bold;
            color: #064e3b;
            margin-bottom: 4px;
        }

        .contract-meta p {
            font-size: 11px;
            color: #6b7280;
        }

        /* Isi konten HTML dari editor */
        .contract-body {
            margin-bottom: 40px;
        }

        .contract-body p {
            margin-bottom: 6px;
        }

        .contract-body strong {
            font-weight: bold;
        }

        .contract-body em {
            font-style: italic;
        }

        .contract-body h1, .contract-body h2, .contract-body h3 {
            margin-top: 14px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .contract-body ul, .contract-body ol {
            margin-left: 20px;
            margin-bottom: 6px;
        }

        .contract-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .contract-body table td,
        .contract-body table th {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
        }

        .contract-body table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        /* Blok tanda tangan */
        .signature-section {
            margin-top: 40px;
            border-top: 1px dashed #d1d5db;
            padding-top: 24px;
        }

        .signature-title {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 20px;
        }

        .signature-grid {
            display: table;
            width: 100%;
        }

        .signature-item {
            display: table-cell;
            width: 50%;
            padding: 0 20px;
            text-align: center;
            vertical-align: top;
        }

        .signature-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            height: 90px;
            background-color: #f9fafb;
            margin-bottom: 8px;
        }

        .signer-name {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
        }

        .signer-role {
            font-size: 10px;
            color: #6b7280;
        }

        .signer-email {
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    {{-- Header info kontrak --}}
    <div class="contract-meta">
        <h1>{{ $contract->title }}</h1>
        <p>Nomor: {{ $contract->contract_number ?? '-' }}</p>
        <p>Dibuat oleh: {{ $contract->creator?->name ?? '-' }}</p>
        @if($contract->start_date)
            <p>Tanggal Mulai: {{ $contract->start_date->format('d/m/Y') }}</p>
        @endif
        @if($contract->end_date)
            <p>Tanggal Selesai: {{ $contract->end_date->format('d/m/Y') }}</p>
        @endif
    </div>

    {{-- Isi konten kontrak dari TipTap (HTML) --}}
    <div class="contract-body">
        {!! $content !!}
    </div>

    {{-- Blok tanda tangan --}}
    @if($contract->signers && $contract->signers->count() > 0)
    <div class="signature-section">
        <p class="signature-title">Tanda Tangan</p>
        <div class="signature-grid">
            @foreach($contract->signers as $signer)
            <div class="signature-item">
                <div class="signature-box"></div>
                <p class="signer-name">
                    {{ $signer->signer_type === 'internal'
                        ? ($signer->user?->name ?? '-')
                        : ($signer->signer_name ?? '-') }}
                </p>
                <p class="signer-role">
                    {{ $signer->signer_type === 'internal'
                        ? ($signer->user?->job_title ?? '')
                        : ($signer->signer_role ?? '') }}
                </p>
                @if($signer->signer_type === 'external' && $signer->external_email)
                    <p class="signer-email">{{ $signer->external_email }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</body>
</html>
