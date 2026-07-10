<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Sistema RRHH') }}</title>

        <style>[x-cloak]{display:none!important}</style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-ink">
        <div x-data="{ open: false }" class="min-h-screen bg-canvas lg:flex">

            {{-- ===== Sidebar ===== --}}
            <aside x-cloak
                   :class="open ? 'translate-x-0' : '-translate-x-full'"
                   class="fixed inset-y-0 left-0 z-40 w-64 bg-navy text-white flex flex-col transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto lg:shrink-0">

                {{-- Marca --}}
                <div class="h-16 flex items-center gap-2 px-5 border-b border-white/10">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary text-white font-bold">R</span>
                    <span class="font-semibold tracking-tight">{{ config('app.name', 'Sistema RRHH') }}</span>
                </div>

                {{-- Navegación --}}
                @php
                    $itemBase = 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
                    $itemOn = 'bg-white/15 text-white';
                    $itemOff = 'text-white/70 hover:bg-white/10 hover:text-white';
                @endphp
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="{{ $itemBase }} {{ request()->routeIs('dashboard') ? $itemOn : $itemOff }}">
                        <x-icon name="home" class="w-5 h-5 shrink-0" /> Tablero
                    </a>
                    @if (auth()->user()->empleado)
                        <a href="{{ route('portal.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('portal.*') ? $itemOn : $itemOff }}">
                            <x-icon name="user" class="w-5 h-5 shrink-0" /> Mi espacio
                        </a>
                    @endif

                    @can('empleados.ver')
                        <a href="{{ route('empleados.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('empleados.*') ? $itemOn : $itemOff }}">
                            <x-icon name="users" class="w-5 h-5 shrink-0" /> Empleados
                        </a>
                    @endcan
                    @can('documentos.ver')
                        <a href="{{ route('documentos.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('documentos.index') || request()->routeIs('documentos.exportar') ? $itemOn : $itemOff }}">
                            <x-icon name="document" class="w-5 h-5 shrink-0" /> Documentos
                        </a>
                    @endcan
                    @can('documentos_compartidos.ver')
                        <a href="{{ route('documentos-compartidos.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('documentos-compartidos.*') ? $itemOn : $itemOff }}">
                            <x-icon name="clipboard" class="w-5 h-5 shrink-0" /> Doc. compartidos
                        </a>
                    @endcan
                    @can('activos.ver')
                        <a href="{{ route('activos.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('activos.*') ? $itemOn : $itemOff }}">
                            <x-icon name="wrench" class="w-5 h-5 shrink-0" /> Activos
                        </a>
                    @endcan
                    @can('vacaciones.ver')
                        <a href="{{ route('vacaciones.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('vacaciones.*') ? $itemOn : $itemOff }}">
                            <x-icon name="sun" class="w-5 h-5 shrink-0" /> Vacaciones
                        </a>
                    @endcan
                    @can('ausencias.ver')
                        <a href="{{ route('ausencias.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('ausencias.*') ? $itemOn : $itemOff }}">
                            <x-icon name="health" class="w-5 h-5 shrink-0" /> Ausencias
                        </a>
                    @endcan
                    @can('descuentos.ver')
                        <a href="{{ route('descuentos.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('descuentos.*') ? $itemOn : $itemOff }}">
                            <x-icon name="cash" class="w-5 h-5 shrink-0" /> Descuentos
                        </a>
                    @endcan

                    @can('usuarios.ver')
                        <a href="{{ route('usuarios.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('usuarios.*') ? $itemOn : $itemOff }}">
                            <x-icon name="user-cog" class="w-5 h-5 shrink-0" /> Usuarios
                        </a>
                    @endcan
                    @role('SuperAdmin')
                        <a href="{{ route('roles.index') }}" wire:navigate
                           class="{{ $itemBase }} {{ request()->routeIs('roles.*') ? $itemOn : $itemOff }}">
                            <x-icon name="key" class="w-5 h-5 shrink-0" /> Roles y accesos
                        </a>
                    @endrole
                </nav>

                {{-- Usuario --}}
                <div class="border-t border-white/10 p-4">
                    <div class="text-sm font-medium truncate">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-white/60 truncate">
                        {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol' }}
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-sm">
                        <a href="{{ route('profile') }}" wire:navigate class="text-white/80 hover:text-white">Perfil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-white/80 hover:text-white">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Fondo oscuro al abrir en móvil --}}
            <div x-show="open" x-cloak @click="open = false"
                 class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

            {{-- ===== Contenido ===== --}}
            <div class="flex-1 min-w-0">
                <header class="bg-surface border-b border-line">
                    <div class="h-16 flex items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button @click="open = true" class="lg:hidden text-muted hover:text-ink text-2xl leading-none">☰</button>
                        @isset($header)
                            <div class="min-w-0">{{ $header }}</div>
                        @endisset
                    </div>
                </header>

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
