<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="description" content="Sistem absensi guru SMK Budi Mulia berbasis QR code" />
    <meta name="theme-color" content="#0e57a6" />

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png" />
    <link rel="manifest" href="/manifest.json" />

    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="Absensi Guru" />

    <title>Absensi Guru - SMK Budi Mulia</title>

    @routes
    @viteReactRefresh
    @vite(['resources/js/main.tsx'])
    @inertiaHead
</head>
<body class="h-full">
    @inertia
</body>
</html>
