<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'ManicERP') }} - Citizen portal</title>
    @vite(['resources/css/app.css', 'resources/js/portal.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @inertia
</body>
</html>
