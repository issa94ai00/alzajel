<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'DamaTech') }}</title>

        <meta name="theme-color" content="#ffffff">

        <script>
            // يطابق useTheme.js — يُطبَّق قبل الرسم لتجنّب وميض الوضع الخاطئ
            (function () {
                var mode = null, accent = null;
                try {
                    mode = localStorage.getItem('theme');
                    accent = localStorage.getItem('theme-accent');
                } catch (e) {}
                var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                var root = document.documentElement;
                root.classList.toggle('dark', dark);
                if (accent) root.setAttribute('data-accent', accent);
                if (dark) document.querySelector('meta[name="theme-color"]').setAttribute('content', '#0f172a');
            })();
        </script>

        <link rel="icon" type="image/png" href="/favicon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
