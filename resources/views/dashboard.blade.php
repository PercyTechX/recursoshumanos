<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-navy leading-tight">Tablero</h2>
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

            @php
                $u = auth()->user();
                $card = 'flex items-center gap-3 bg-surface border border-line rounded-xl p-4 border-l-4 hover:shadow-sm transition-shadow';
                $circ = 'inline-flex items-center justify-center w-11 h-11 rounded-full shrink-0';
            @endphp

            {{-- KPIs --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                @can('empleados.ver')
                    <a href="{{ route('empleados.index') }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="users" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Empleados activos</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\Empleado::where('situacion', 'activo')->count() }}</div></div>
                    </a>
                @endcan

                @can('documentos.ver')
                    @php
                        $r = \App\Models\Documento::resumenSemaforo();
                        $rc = \App\Models\DocumentoCompartido::resumenSemaforo();
                        $porV = $r['por_vencer'] + $rc['por_vencer'];
                        $venc = $r['vencido'] + $rc['vencido'];
                    @endphp
                    <a href="{{ route('documentos.index', ['filtroEstado' => 'por_vencer']) }}" wire:navigate class="{{ $card }} border-l-warning">
                        <span class="{{ $circ }} bg-warning-tint text-warning"><x-icon name="clock" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Documentos por vencer</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ $porV }}</div></div>
                    </a>
                    <a href="{{ route('documentos.index', ['filtroEstado' => 'vencido']) }}" wire:navigate class="{{ $card }} border-l-danger">
                        <span class="{{ $circ }} bg-danger-tint text-danger"><x-icon name="alert" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Documentos vencidos</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ $venc }}</div></div>
                    </a>
                @endcan

                @can('vacaciones.ver')
                    <a href="{{ route('vacaciones.index') }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="sun" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Vacaciones pendientes</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\SolicitudVacaciones::where('estado', 'pendiente')->count() }}</div></div>
                    </a>
                @endcan

                @can('ausencias.ver')
                    <a href="{{ route('ausencias.index') }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="health" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Ausencias en curso hoy</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\Ausencia::whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today())->count() }}</div></div>
                    </a>
                @endcan

                @can('tickets.ver')
                    <a href="{{ route('tickets.index', ['filtroEstado' => 'abierto']) }}" wire:navigate class="{{ $card }} border-l-success">
                        <span class="{{ $circ }} bg-success-tint text-success"><x-icon name="ticket" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Tickets abiertos</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\Ticket::abiertos()->count() }}</div></div>
                    </a>
                @endcan

                @can('asistencia.ver')
                    <a href="{{ route('asistencia.index', ['vista' => 'tickets']) }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="map-pin" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Técnicos operando ahora</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\TicketTecnico::whereIn('estado_trabajo', \App\Models\TicketTecnico::ACTIVOS)->count() }}</div></div>
                    </a>
                    <a href="{{ route('asistencia.index') }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="clock" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Marcaciones hoy</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\Marcacion::whereDate('fecha_hora', today())->count() }}</div></div>
                    </a>
                @endcan

                @can('descuentos.ver')
                    <a href="{{ route('descuentos.index') }}" wire:navigate class="{{ $card }} border-l-warning">
                        <span class="{{ $circ }} bg-warning-tint text-warning"><x-icon name="cash" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Descuentos pendientes</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">S/ {{ number_format((float) \App\Models\Descuento::where('estado', 'pendiente')->sum('monto'), 2) }}</div></div>
                    </a>
                @endcan

                @can('activos.ver')
                    <a href="{{ route('activos.index') }}" wire:navigate class="{{ $card }} border-l-primary">
                        <span class="{{ $circ }} bg-primary-tint text-primary"><x-icon name="wrench" class="w-6 h-6" /></span>
                        <div><div class="text-sm text-muted">Activos asignados</div>
                            <div class="text-2xl font-bold text-ink tabular-nums leading-tight">{{ \App\Models\Activo::where('estado', 'asignado')->count() }}</div></div>
                    </a>
                @endcan

            </div>

            @if ($u->empleado)
                <a href="{{ route('portal.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-primary font-medium hover:underline">
                    <x-icon name="user" class="w-4 h-4" /> Ir a Mi espacio (mis datos, asistencia, tickets)
                </a>
            @endif

        </div>
    </div>
</x-app-layout>
