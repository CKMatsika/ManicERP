<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Licence verification</title>
<style>body{font-family:system-ui,sans-serif;background:#f3f7f6;color:#183330;margin:0;padding:24px}.card{max-width:620px;margin:8vh auto;background:white;border:1px solid #d2e0dd;border-radius:12px;padding:28px}h1{margin-top:0}.ok{color:#087443}.bad{color:#a12828}dt{font-weight:650;margin-top:16px}dd{margin:4px 0}</style></head>
<body><main class="card"><h1>Certificate verification</h1>
@if($result['authentic'])
<p class="{{ $result['currently_valid'] ? 'ok' : 'bad' }}">Authentic certificate. Current validity: {{ $result['currently_valid'] ? 'VALID' : 'NOT CURRENTLY VALID' }}.</p>
<dl><dt>Certificate</dt><dd>{{ $result['certificate_reference'] }}</dd><dt>Licence type</dt><dd>{{ $result['licence_type'] }}</dd><dt>Holder</dt><dd>{{ $result['holder'] }}</dd><dt>Issued</dt><dd>{{ $result['issued_on'] }}</dd><dt>Expires</dt><dd>{{ $result['expires_on'] }}</dd><dt>Current status</dt><dd>{{ $result['current_status'] }}</dd></dl>
@else
<p class="bad">No authentic certificate was found.</p>
@endif
</main></body></html>
