<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Quorum quản trị · TrustGate</title>
        @vite(['resources/css/app.css', 'resources/js/quorum.jsx'])
    </head>
    <body class="min-h-screen bg-slate-50">
        <div id="quorum-root" data-user-name="{{ auth()->user()->name }}"></div>
    </body>
</html>
