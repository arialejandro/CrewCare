<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('admin.badge._pdf_fonts')
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        .badge-page { page-break-after: always; }
        .badge-page:last-child { page-break-after: auto; }
        .gft-card { page-break-inside: avoid; }
    </style>
</head>
<body>
    @foreach ($users as $user)
        <div class="badge-page">
            @include('admin.badge._card', ['user' => $user, 'tpl' => $tpl, 'forPdf' => true])
        </div>
    @endforeach
</body>
</html>
