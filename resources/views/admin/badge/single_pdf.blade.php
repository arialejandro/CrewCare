<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('admin.badge._pdf_fonts')
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        .gft-card { page-break-inside: avoid; }
    </style>
</head>
<body>
    @include('admin.badge._card', ['user' => $user, 'tpl' => $tpl, 'forPdf' => true])
</body>
</html>
