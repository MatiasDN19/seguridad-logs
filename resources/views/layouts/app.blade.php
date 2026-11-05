<!DOCTYPE html>
<html lang="es" class="bg-ink">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('head')
    <title>@yield('title', 'Seguridad') · Logs de seguridad</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink bg-grid font-sans text-slate-200 antialiased">
    @yield('content')
</body>
</html>
