<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $brandName = config('clinicore.brand.name', 'CliniCore');
            $brandUrl = config('clinicore.brand.url', 'https://clinicore.es');
            $seoDescription = 'Software SaaS de planificación automática de guardias para servicios hospitalarios en España: Medicina, Farmacia Hospitalaria, Enfermería y unidades clínicas. Equidad anual, salientes, permutas y exportación a PDF/Excel.';
        @endphp

        <!-- SEO & OpenGraph -->
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="keywords" content="cuadrante de guardias, planificación de guardias hospitalarias, software guardias médicas, farmacia hospitalaria, enfermería, salientes, equidad guardias, CliniCore">
        <meta name="author" content="{{ $brandName }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $brandName }}">
        <meta property="og:title" content="{{ $brandName }} — Planificación inteligente de guardias hospitalarias">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $brandUrl }}">
        <meta property="og:locale" content="es_ES">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $brandName }} — Planificación inteligente de guardias hospitalarias">
        <meta name="twitter:description" content="{{ $seoDescription }}">

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#09090b">
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <!-- Iconos y fuentes -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @fonts

        <!-- Scripts de Estilos y Compilación ÚNICA (TypeScript + Vue) -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])

        <!-- Cabecera semántica de Inertia -->
        <x-inertia::head>
            <title>{{ config('app.name', 'CliniCore') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
