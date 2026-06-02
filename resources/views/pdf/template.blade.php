@php
    $renderedContent = \App\Support\PdfContentNormalizer::normalize($content ?? '');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>{{ $template->name }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: {{ ($template->paper_size ?? 'a4') === 'f4' ? '21.5cm 33cm' : 'A4' }};
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

        .template-body {
            width: 100%;
        }

        .template-body p {
            margin: 0 0 0.45em;
            min-height: 1.6em;
        }

        .template-body p:empty::before {
            content: "\00a0";
        }

        .template-body h1,
        .template-body h2,
        .template-body h3 {
            margin: 0.75em 0 0.45em;
            font-weight: 700;
            line-height: 1.35;
        }

        .template-body h1 { font-size: 1.5em; }
        .template-body h2 { font-size: 1.25em; }
        .template-body h3 { font-size: 1.1em; }

        .template-body strong { font-weight: 700; }
        .template-body em { font-style: italic; }
        .template-body u { text-decoration: underline; }
        .template-body s { text-decoration: line-through; }

        .template-body ul,
        .template-body ol {
            margin: 0 0 0.45em 1.5em;
            padding-left: 1em;
        }

        .template-body li {
            margin: 0.2em 0;
        }

        .template-body [style*="text-align: center"] { text-align: center; }
        .template-body [style*="text-align: right"] { text-align: right; }
        .template-body [style*="text-align: left"] { text-align: left; }
        .template-body [style*="text-align: justify"] { text-align: justify; }

        .template-body img {
            max-width: 100%;
            height: auto;
        }

        .template-body .pdf-image-container {
            page-break-inside: avoid;
            margin-bottom: 0.45em;
        }

        .template-body .pdf-image-container img {
            display: block;
            width: 100%;
            height: auto;
        }

        .template-body table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0.5em 0;
            font-size: 0.9em;
            border: 1px solid #000000;
        }

        .template-body table td,
        .template-body table th {
            border: 1px solid #000000;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .template-body table th {
            background-color: transparent;
            font-weight: 700;
        }

        .template-body table[data-border-type="none"],
        .template-body table[data-border-type="none"] td,
        .template-body table[data-border-type="none"] th {
            border: none !important;
        }

        .template-body table[data-border-type="outside"] {
            border: 1px solid #000000 !important;
        }

        .template-body table[data-border-type="outside"] td,
        .template-body table[data-border-type="outside"] th {
            border: none !important;
        }

        .template-body table[data-border-type="inside"] {
            border: none !important;
        }

        .template-body table[data-border-type="inside"] td,
        .template-body table[data-border-type="inside"] th {
            border: 1px solid #000000 !important;
        }

        .template-body table td[data-border-top="none"],
        .template-body table th[data-border-top="none"] { border-top: none !important; }
        .template-body table td[data-border-right="none"],
        .template-body table th[data-border-right="none"] { border-right: none !important; }
        .template-body table td[data-border-bottom="none"],
        .template-body table th[data-border-bottom="none"] { border-bottom: none !important; }
        .template-body table td[data-border-left="none"],
        .template-body table th[data-border-left="none"] { border-left: none !important; }

        .template-body .page-break {
            page-break-after: always;
            break-after: page;
            height: 0;
            margin: 0;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="template-body">
        {!! $renderedContent !!}
    </div>
</body>
</html>

