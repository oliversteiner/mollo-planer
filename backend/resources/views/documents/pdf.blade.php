<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: {{ $font ?? 'dejavusans' }}, sans-serif; font-size: 10pt; line-height: 1.45; color: #1a1a1a; }
    h1 { font-size: 20pt; margin: 0 0 4pt; }
    h2 { font-size: 15pt; margin: 16pt 0 4pt; }
    h3 { font-size: 12.5pt; margin: 12pt 0 4pt; }
    h4 { font-size: 11pt; margin: 10pt 0 4pt; }
    p { margin: 0 0 7pt; }
    .meta { color: #666; font-size: 9pt; margin-bottom: 14pt; border-bottom: 0.5pt solid #bbb; padding-bottom: 6pt; }
    table { border-collapse: collapse; width: 100%; margin: 6pt 0 10pt; }
    th, td { border: 0.5pt solid #999; padding: 3pt 5pt; text-align: left; vertical-align: top; }
    th { background-color: #eee; }
    pre { background-color: #f3f3f3; padding: 6pt; font-size: 8.5pt; margin: 6pt 0 10pt; }
    code { font-family: dejavusansmono, monospace; font-size: 9pt; background-color: #f3f3f3; }
    pre code { background-color: transparent; }
    blockquote { margin: 6pt 0 8pt 0; padding-left: 8pt; border-left: 2pt solid #bbb; color: #444; }
    hr { border: 0; border-top: 0.5pt solid #bbb; margin: 12pt 0; }
    a { color: #1d4ed8; }
    ul, ol { margin: 0 0 7pt; }
</style>
</head>
<body>
<h1>{{ $title }}</h1>
<div class="meta">{{ $meta }}</div>
{!! $body !!}
</body>
</html>
