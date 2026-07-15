<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rendición · {{ config('rendiciones.empresa.nombre') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans text-ink antialiased bg-canvas min-h-screen flex flex-col">
    <header class="bg-navy text-white">
        <div class="max-w-4xl mx-auto w-full px-4 py-4 flex items-center justify-between">
            <div>
                <div class="font-semibold tracking-tight">{{ config('rendiciones.empresa.nombre') }}</div>
                <div class="text-white/70 text-xs">Rendición de caja chica</div>
            </div>
            <span class="text-white/80 text-xs">RUC {{ config('rendiciones.empresa.ruc') }}</span>
        </div>
    </header>

    <main class="max-w-4xl mx-auto w-full px-4 py-6 flex-1">
        <livewire:rendiciones.rendir :deposito="$deposito" />
    </main>

    <footer class="max-w-4xl mx-auto w-full px-4 py-6 text-center text-xs text-faint">
        Elaborado por {{ config('rendiciones.elaborado_por.nombre') }} · Soporte WhatsApp {{ config('rendiciones.elaborado_por.soporte') }}
    </footer>

    @livewireScripts
</body>
</html>
