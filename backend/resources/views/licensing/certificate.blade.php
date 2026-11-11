<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #102a2a; margin: 42px; }
        .frame { border: 4px double #176b63; min-height: 680px; padding: 34px; text-align: center; }
        .eyebrow { color: #176b63; letter-spacing: 2px; text-transform: uppercase; font-size: 12px; }
        h1 { font-size: 28px; margin: 12px 0; }
        .holder { font-size: 24px; font-weight: bold; margin: 28px 0; }
        .details { margin: 28px auto; width: 80%; text-align: left; border-collapse: collapse; }
        .details td { padding: 8px; border-bottom: 1px solid #d8e5e2; }
        .conditions { text-align: left; margin: 26px auto; width: 80%; }
        .verify { border-top: 1px solid #9bbab5; margin: 38px auto 0; padding-top: 14px; width: 80%; font-size: 11px; word-wrap: break-word; }
        .qr { width: 120px; height: 120px; margin: 12px auto; }
    </style>
</head>
<body>
<main class="frame">
    <div class="eyebrow">{{ $council->name }}</div>
    <h1>Licence Certificate</h1>
    <p>This certificate records an instrument issued by the council.</p>
    <div class="holder">{{ $certificate->holder_name }}</div>
    <p>{{ $certificate->licence_type_name }}</p>
    <table class="details">
        <tr><td>Certificate reference</td><td>{{ $certificate->certificate_reference }}</td></tr>
        <tr><td>Licence reference</td><td>{{ $certificate->licence?->reference ?? '' }}</td></tr>
        <tr><td>Issued</td><td>{{ $certificate->issued_on->toFormattedDateString() }}</td></tr>
        <tr><td>Expires</td><td>{{ $certificate->expires_on->toFormattedDateString() }}</td></tr>
    </table>
    @if(count($certificate->conditions ?? []))
        <section class="conditions"><strong>Conditions</strong><ul>@foreach($certificate->conditions as $condition)<li>{{ is_string($condition) ? $condition : json_encode($condition) }}</li>@endforeach</ul></section>
    @endif
    <div class="verify">
        <strong>Verify authenticity and current status:</strong><br>
        <img class="qr" src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR code for public certificate verification"><br>
        {{ $verificationUrl }}<br>
        Verification identifier: {{ $certificate->verification_id }}
    </div>
</main>
</body>
</html>
