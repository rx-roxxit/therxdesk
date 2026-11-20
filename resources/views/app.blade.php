@php($theme = data_get($page, 'props.auth.user.theme', 'light'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir ?? 'ltr' }}" data-theme="{{ $theme }}" @class(['dark' => $theme === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.gtm')

        {{-- Resolves the "system" theme before the first paint and follows the
             OS setting while the page is open. Explicit themes are rendered by
             the server; resources/js/preferences.ts keeps both in step. --}}
        <script>
            (function () {
                var root = document.documentElement;
                var media = window.matchMedia('(prefers-color-scheme: dark)');
                var apply = function () {
                    if (root.dataset.theme === 'system') {
                        root.classList.toggle('dark', media.matches);
                    }
                };
                apply();
                media.addEventListener('change', apply);
            })();
        </script>

        {{-- Inline page-paint background. Matches `--background` so the
             first paint doesn't flash before CSS loads. --}}
        <style>
            html {
                background-color: #ffffff;
                color-scheme: light;
            }

            html.dark {
                background-color: #111111;
                color-scheme: dark;
            }
        </style>

        <title data-inertia>{{ config('app.name', 'TryPost.it') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Fonts (Inter, Outfit, JetBrains Mono, Roboto Slab) come from the
             build itself, imported by resources/css/app.css: no font host is
             contacted, so a visitor's browser talks to this domain only. --}}
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @include('partials.gtm-noscript')
        @inertia
    </body>
</html>
