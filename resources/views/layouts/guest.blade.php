<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Sistema RRHH') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-navy via-primary-dark to-primary">
            <div class="flex flex-col items-center">
                <div class="bg-white rounded-2xl px-5 py-4 shadow-lg">
                    <img src="{{ asset('images/brand/logo-gds.png') }}" alt="GDS Infraestructura" class="h-12 w-auto">
                </div>
                <span class="mt-3 text-white text-lg font-semibold tracking-tight">{{ config('app.name', 'Sistema RRHH') }}</span>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-surface shadow-lg overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>

            {{-- Crédito del desarrollador --}}
            <div class="mt-6 flex items-center gap-2">
                <span class="text-[11px] uppercase tracking-wide text-white/50">Desarrollado por</span>
                <img src="{{ asset('images/brand/logo-percytech.png') }}" alt="PercyTech Solutions" class="h-5 w-auto opacity-90">
            </div>
        </div>
    </body>
</html>
