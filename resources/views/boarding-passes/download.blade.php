<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boarding pass {{ $boardingPass->pass_reference }}</title>
    <style>
        body { max-width: 42rem; margin: 2rem auto; padding: 1.5rem; color: #182333; font: 16px/1.5 Arial, sans-serif; }
        h1 { margin-bottom: .4rem; }
        .pass-details > div { display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid #d6dce4; }
        .pass-details dt { color: #596579; font-weight: 600; }
        .pass-details dd { margin: 0; font-weight: 600; }
        .boarding-pass-qr { display: block; width: 12.5rem; height: 12.5rem; margin: 1.5rem auto 0; }
        @media print { body { margin: 0 auto; } }
    </style>
</head>
<body>
    <h1>Digital boarding pass</h1>
    <p>Present this pass when boarding your flight.</p>
    @include('boarding-passes._details')
</body>
</html>
