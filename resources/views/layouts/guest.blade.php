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
                <a href="/" wire:navigate class="flex items-center gap-2 text-white">
                    <x-application-logo class="w-14 h-14 fill-current text-white/90" />
                </a>
                <span class="mt-3 text-white text-lg font-semibold tracking-tight">{{ config('app.name', 'Sistema RRHH') }}</span>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-surface shadow-lg overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
