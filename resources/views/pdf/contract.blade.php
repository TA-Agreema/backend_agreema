@php
    $renderedContent = \App\Support\PdfContentNormalizer::normalize($content ?? '');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>{{ $contract->title }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: {{ ($contract->paper_size ?? 'a4') === 'f4' ? '21.5cm 33cm' : 'A4' }};
            margin: 2.54cm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #000000;
        }

        .contract-body {
            width: 100%;
        }

        .contract-body p {
            margin: 0 0 0.45em;
            min-height: 1.6em;
        }

        .contract-body p:empty::before {
            content: "\00a0";
        }

        .contract-body h1,
        .contract-body h2,
        .contract-body h3 {
            margin: 0.75em 0 0.45em;
            font-weight: 700;
            line-height: 1.35;
        }

        .contract-body h1 { font-size: 1.5em; }
        .contract-body h2 { font-size: 1.25em; }
        .contract-body h3 { font-size: 1.1em; }

        .contract-body strong { font-weight: 700; }
        .contract-body em { font-style: italic; }
        .contract-body u { text-decoration: underline; }
        .contract-body s { text-decoration: line-through; }

        .contract-body ul,
        .contract-body ol {
            margin: 0 0 0.45em 1.5em;
            padding-left: 1em;
        }

        .contract-body li {
            margin: 0.2em 0;
        }

        .contract-body [style*="text-align: center"] { text-align: center; }
        .contract-body [style*="text-align: right"] { text-align: right; }
        .contract-body [style*="text-align: left"] { text-align: left; }
        .contract-body [style*="text-align: justify"] { text-align: justify; }

        .contract-body img {
            max-width: 100%;
            height: auto;
        }

        .contract-body .pdf-image-container {
            page-break-inside: avoid;
            margin-bottom: 0.45em;
        }

        .contract-body .pdf-image-container img {
            display: block;
            width: 100%;
            height: auto;
        }

        .contract-body table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0.5em 0;
            font-size: 0.9em;
            border: 1px solid #000000;
        }

        .contract-body table td,
        .contract-body table th {
            border: 1px solid #000000;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .contract-body table th {
            background-color: transparent;
            font-weight: 700;
        }

        .contract-body table[data-border-type="none"],
        .contract-body table[data-border-type="none"] td,
        .contract-body table[data-border-type="none"] th {
            border: none !important;
        }

        .contract-body table[data-border-type="outside"] {
            border: 1px solid #000000 !important;
        }

        .contract-body table[data-border-type="outside"] td,
        .contract-body table[data-border-type="outside"] th {
            border: none !important;
        }

        .contract-body table[data-border-type="inside"] {
            border: none !important;
        }

        .contract-body table[data-border-type="inside"] td,
        .contract-body table[data-border-type="inside"] th {
            border: 1px solid #000000 !important;
        }

        .contract-body table td[data-border-top="none"],
        .contract-body table th[data-border-top="none"] { border-top: none !important; }
        .contract-body table td[data-border-right="none"],
        .contract-body table th[data-border-right="none"] { border-right: none !important; }
        .contract-body table td[data-border-bottom="none"],
        .contract-body table th[data-border-bottom="none"] { border-bottom: none !important; }
        .contract-body table td[data-border-left="none"],
        .contract-body table th[data-border-left="none"] { border-left: none !important; }

        .contract-body .page-break {
            page-break-after: always;
            break-after: page;
            height: 0;
            margin: 0;
            border: 0;
        }

        .signature-section {
            margin-top: 40px;
            border-top: 1px dashed #d1d5db;
            padding-top: 24px;
        }

        .signature-title {
            text-align: center;
            font-size: 10px;
            font-weight: 700;
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
            height: 90px;
            background-color: #ffffff;
            margin-bottom: 8px;
            text-align: center;
            padding: 4px;
        }

        .signature-box img {
            max-height: 80px;
            max-width: 100%;
        }

        .signature-box .unasigned {
            font-size: 10px;
            color: #d1d5db;
            line-height: 80px;
        }

        .signer-name {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }

        .signer-role,
        .signer-email {
            font-size: 10px;
            color: #6b7280;
        }

        .signer-date {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="contract-body">
        {!! $renderedContent !!}
    </div>

    @if($contract->signers && $contract->signers->count() > 0)
    <div class="signature-section">
        <p class="signature-title">Tanda Tangan</p>
        <div class="signature-grid">
            @foreach($contract->signers as $signer)
            <div class="signature-item">
                <div class="signature-box">
                    @if(isset($signatureImages[$signer->id]))
                        {{-- Gambar TTD di-embed sebagai base64 --}}
                        <img src="{{ $signatureImages[$signer->id] }}" alt="Tanda tangan">
                    @else
                        <span class="unsigned">Belum ditandatangani</span>
                    @endif
                </div>
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
                @if(isset($signatureDates[$signer->id]))
                    <p class="signer-date">{{ $signatureDates[$signer->id] }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</body>
</html>
