<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-navy leading-tight">
            Tablero
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Bienvenida --}}
            <div class="bg-gradient-to-br from-navy via-primary-dark to-primary rounded-2xl shadow-lg p-6 text-white">
                <p class="text-sm text-white/80">Bienvenido(a)</p>
                <h3 class="text-2xl font-semibold tracking-tight">{{ auth()->user()->name }}</h3>
                <p class="mt-1 text-white/85 text-sm">
                    Rol:
                    <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-semibold">
                        {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}
                    </span>
                </p>
            </div>

            {{-- Resumen del semáforo (datos reales) --}}
            @php
                // Cuenta solo el documento ACTUAL de cada requisito (no el historial)
                $r = \App\Models\Documento::resumenSemaforo();
                $vig = $r['vigente'];
                $porV = $r['por_vencer'];
                $venc = $r['vencido'];
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('documentos.index', ['filtroEstado' => 'vigente']) }}"
                   class="bg-surface border border-line rounded-xl p-4 border-l-4 border-l-success hover:shadow-sm">
                    <div class="text-sm text-muted">🟢 Documentos vigentes</div>
                    <div class="text-2xl font-bold text-ink tabular-nums">{{ $vig }}</div>
                </a>
                <a href="{{ route('documentos.index', ['filtroEstado' => 'por_vencer']) }}"
                   class="bg-surface border border-line rounded-xl p-4 border-l-4 border-l-warning hover:shadow-sm">
                    <div class="text-sm text-muted">🟡 Por vencer</div>
                    <div class="text-2xl font-bold text-ink tabular-nums">{{ $porV }}</div>
                </a>
                <a href="{{ route('documentos.index', ['filtroEstado' => 'vencido']) }}"
                   class="bg-surface border border-line rounded-xl p-4 border-l-4 border-l-danger hover:shadow-sm">
                    <div class="text-sm text-muted">🔴 Vencidos</div>
                    <div class="text-2xl font-bold text-ink tabular-nums">{{ $venc }}</div>
                </a>
            </div>

            <div class="bg-surface border border-line rounded-xl p-6 text-muted text-sm">
                Accesos rápidos:
                <a href="{{ route('empleados.index') }}" class="text-primary font-medium hover:underline">Empleados</a> ·
                <a href="{{ route('documentos.index') }}" class="text-primary font-medium hover:underline">Documentos</a>
            </div>

        </div>
    </div>
</x-app-layout>
