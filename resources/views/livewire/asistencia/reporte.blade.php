<?php

use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\TicketAvance;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $tipo = 'general'; // general | detallado
    #[Url]
    public string $desde = '';
    #[Url]
    public string $hasta = '';
    #[Url]
    public ?int $empleado_id = null;

    public function mount(): void
    {
        $this->desde = $this->desde ?: now()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: now()->toDateString();
    }

    /** Empareja marcaciones ingreso→salida en jornadas (soporta cruce de medianoche). */
    private function jornadas($marcaciones): array
    {
        $jornadas = [];
        $abierta = null;
        foreach ($marcaciones as $m) {
            if ($m->tipo === 'ingreso') {
                $abierta = $m;
            } elseif ($m->tipo === 'salida' && $abierta) {
                $jornadas[] = [
                    'ingreso' => $abierta->fecha_hora,
                    'salida' => $m->fecha_hora,
                    'minutos' => $abierta->fecha_hora->diffInMinutes($m->fecha_hora),
                ];
                $abierta = null;
            }
        }
        if ($abierta) {
            $jornadas[] = ['ingreso' => $abierta->fecha_hora, 'salida' => null, 'minutos' => 0];
        }

        return $jornadas;
    }

    private function rango(): array
    {
        return [$this->desde.' 00:00:00', $this->hasta.' 23:59:59'];
    }

    /** Datos calculados del reporte (resumen por empleado o detalle de uno). */
    private function calcular(): array
    {
        [$ini, $fin] = $this->rango();

        $marcs = Marcacion::query()
            ->whereBetween('fecha_hora', [$ini, $fin])
            ->when($this->empleado_id, fn ($q) => $q->where('empleado_id', $this->empleado_id))
            ->orderBy('fecha_hora')->orderBy('id')->get()
            ->groupBy('empleado_id');

        // Tickets operados por empleado en el rango (distintos)
        $ticketsPorEmp = TicketAvance::query()
            ->whereBetween('ticket_avances.fecha_hora', [$ini, $fin])
            ->join('ticket_tecnico', 'ticket_tecnico.id', '=', 'ticket_avances.ticket_tecnico_id')
            ->selectRaw('ticket_tecnico.empleado_id as eid, count(distinct ticket_tecnico.ticket_id) as n')
            ->groupBy('ticket_tecnico.empleado_id')->pluck('n', 'eid');

        $empleados = Empleado::whereIn('id', $marcs->keys())->get()->keyBy('id');

        $filas = [];
        $detalle = [];
        foreach ($marcs as $eid => $lista) {
            $j = $this->jornadas($lista);
            $min = array_sum(array_column($j, 'minutos'));
            $filas[] = [
                'empleado' => $empleados[$eid] ?? null,
                'jornadas' => count($j),
                'minutos' => $min,
                'tickets' => (int) ($ticketsPorEmp[$eid] ?? 0),
            ];
            if ($this->empleado_id && (int) $eid === (int) $this->empleado_id) {
                $detalle = $j;
            }
        }
        usort($filas, fn ($a, $b) => strcmp($a['empleado']?->apellidos ?? '', $b['empleado']?->apellidos ?? ''));

        return ['filas' => $filas, 'detalle' => $detalle];
    }

    /** Línea de tiempo del empleado (ingreso, tickets, salida) agrupada por turno. */
    private function timeline(int $empleadoId): array
    {
        [$ini, $fin] = $this->rango();

        $eventos = collect();

        // Marcaciones (ingreso/salida)
        foreach (Marcacion::where('empleado_id', $empleadoId)->whereBetween('fecha_hora', [$ini, $fin])->get() as $m) {
            $eventos->push([
                'hora' => $m->fecha_hora, 'orden' => $m->fecha_hora->timestamp,
                'clase' => 'marcacion', 'tipo' => $m->tipo,
                'titulo' => $m->tipo === 'ingreso' ? 'Ingreso' : 'Salida',
                'ubicacion' => null, 'dentro' => null,
                'lat' => $m->latitud, 'lng' => $m->longitud,
            ]);
        }

        // Avances de tickets
        $avances = TicketAvance::whereBetween('fecha_hora', [$ini, $fin])
            ->whereHas('ticketTecnico', fn ($q) => $q->where('empleado_id', $empleadoId))
            ->with(['ticketTecnico.ticket.sucursal', 'ticketTecnico.ticket.sede'])->get();
        foreach ($avances as $a) {
            $ticket = $a->ticketTecnico?->ticket;
            $estado = match ($a->estado) {
                'iniciado' => 'Iniciado', 'en_ejecucion' => 'En ejecución',
                'terminado' => 'Terminado', 'abortado' => 'Abortado', default => $a->estado,
            };
            $eventos->push([
                'hora' => $a->fecha_hora, 'orden' => $a->fecha_hora->timestamp + 0.5,
                'clase' => 'ticket', 'tipo' => $a->estado,
                'titulo' => 'Ticket '.($ticket?->ticket_atencion ?? '').' · '.$estado,
                'ubicacion' => $ticket?->ubicacion_nombre,
                'dentro' => $a->dentro_geocerca,
                'lat' => $a->latitud, 'lng' => $a->longitud,
            ]);
        }

        $eventos = $eventos->sortBy('orden')->values();

        // Agrupar por turno (ingreso→salida)
        $turnos = [];
        $actual = null;
        foreach ($eventos as $ev) {
            if ($ev['clase'] === 'marcacion' && $ev['tipo'] === 'ingreso') {
                if ($actual) {
                    $turnos[] = $actual;
                }
                $actual = ['inicio' => $ev['hora'], 'fin' => null, 'eventos' => [$ev]];
            } elseif ($ev['clase'] === 'marcacion' && $ev['tipo'] === 'salida') {
                if (! $actual) {
                    $actual = ['inicio' => null, 'fin' => null, 'eventos' => []];
                }
                $actual['fin'] = $ev['hora'];
                $actual['eventos'][] = $ev;
                $turnos[] = $actual;
                $actual = null;
            } else {
                if (! $actual) {
                    $actual = ['inicio' => null, 'fin' => null, 'eventos' => []];
                }
                $actual['eventos'][] = $ev;
            }
        }
        if ($actual) {
            $turnos[] = $actual;
        }

        foreach ($turnos as &$t) {
            $t['minutos'] = ($t['inicio'] && $t['fin']) ? $t['inicio']->diffInMinutes($t['fin']) : 0;
        }

        return $turnos;
    }

    public function exportar()
    {
        $datos = $this->calcular();
        $filename = 'reporte-asistencia-'.$this->desde.'_'.$this->hasta.'.csv';

        return response()->streamDownload(function () use ($datos) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
            fputcsv($out, ['Empleado', 'Documento', 'Jornadas', 'Horas', 'Tickets operados']);
            foreach ($datos['filas'] as $f) {
                fputcsv($out, [
                    trim(($f['empleado']?->apellidos ?? '').', '.($f['empleado']?->nombres ?? '')),
                    $f['empleado']?->numero_documento,
                    $f['jornadas'],
                    number_format($f['minutos'] / 60, 2),
                    $f['tickets'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function with(): array
    {
        $r = $this->calcular();

        return [
            'filas' => $r['filas'],
            'detalle' => $r['detalle'],
            'turnos' => ($this->tipo === 'detallado' && $this->empleado_id) ? $this->timeline($this->empleado_id) : [],
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
        ];
    }
}; ?>

@php
    $horas = fn ($min) => intdiv($min, 60).'h '.($min % 60).'m';
@endphp

<div>
    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3 mb-5 bg-surface border border-line rounded-xl p-4">
        <div>
            <label class="block text-xs text-muted mb-1">Tipo de reporte</label>
            <div class="inline-flex rounded-lg border border-line overflow-hidden">
                <button wire:click="$set('tipo', 'general')" class="px-3 py-2 text-sm font-medium {{ $tipo === 'general' ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-canvas' }}">General</button>
                <button wire:click="$set('tipo', 'detallado')" class="px-3 py-2 text-sm font-medium {{ $tipo === 'detallado' ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-canvas' }}">Detallado</button>
            </div>
        </div>
        <div>
            <label class="block text-xs text-muted mb-1">Desde</label>
            <input type="date" wire:model.live="desde" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs text-muted mb-1">Hasta</label>
            <input type="date" wire:model.live="hasta" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs text-muted mb-1">Empleado</label>
            <select wire:model.live="empleado_id" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                <option value="">Todos</option>
                @foreach ($empleados as $e)
                    <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1"></div>
        <button wire:click="exportar" class="inline-flex items-center gap-1.5 rounded-lg bg-excel hover:brightness-95 text-white text-sm font-semibold px-4 py-2">
            <x-icon name="download" class="w-4 h-4" /> Exportar a Excel
        </button>
    </div>

    @if ($tipo === 'general')
    {{-- Resumen por empleado --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface mb-5">
        <div class="px-4 py-2 text-xs uppercase tracking-wide text-faint border-b border-line">Resumen del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</div>
        <table class="w-full text-sm min-w-[640px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3 text-center">Jornadas</th>
                    <th class="px-4 py-3 text-right">Horas trabajadas</th>
                    <th class="px-4 py-3 text-center">Tickets operados</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $f)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $f['empleado']?->apellidos }}, {{ $f['empleado']?->nombres }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ $f['jornadas'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink">{{ $horas($f['minutos']) }}</td>
                        <td class="px-4 py-3 text-center tabular-nums text-muted">{{ $f['tickets'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Sin marcaciones en el rango elegido.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Detalle de jornadas (si se filtró un empleado) --}}
    @if ($empleado_id && count($detalle))
        <div class="overflow-x-auto rounded-xl border border-line bg-surface">
            <div class="px-4 py-2 text-xs uppercase tracking-wide text-faint border-b border-line">Detalle de jornadas</div>
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Ingreso</th>
                        <th class="px-4 py-3">Salida</th>
                        <th class="px-4 py-3 text-right">Horas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle as $j)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $j['ingreso']->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $j['salida']?->format('d/m/Y H:i') ?? '— (en curso)' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ $j['salida'] ? $horas($j['minutos']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @endif

    {{-- DETALLADO: línea de tiempo (trazabilidad) --}}
    @if ($tipo === 'detallado')
        @if (! $empleado_id)
            <div class="bg-surface border border-line rounded-xl p-8 text-center text-muted">Elige un <strong>empleado</strong> para ver su trazabilidad del día.</div>
        @elseif (! count($turnos))
            <div class="bg-surface border border-line rounded-xl p-8 text-center text-faint">Sin actividad en el rango elegido.</div>
        @else
            <div class="space-y-4">
                @foreach ($turnos as $i => $t)
                    <div class="bg-surface border border-line rounded-xl overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-2 bg-canvas border-b border-line">
                            <span class="text-sm font-semibold text-navy">Turno {{ $i + 1 }}
                                @if ($t['inicio']) · {{ $t['inicio']->format('d/m/Y H:i') }} @endif
                                @if ($t['fin']) → {{ $t['fin']->format('H:i') }} @elseif ($t['inicio']) → (en curso) @endif
                            </span>
                            @if ($t['minutos'] > 0)<span class="text-xs text-muted tabular-nums">{{ $horas($t['minutos']) }}</span>@endif
                        </div>
                        <ol class="relative">
                            @foreach ($t['eventos'] as $ev)
                                <li class="flex gap-3 px-4 py-3 border-b border-line last:border-0">
                                    <div class="w-14 shrink-0 text-xs text-faint tabular-nums pt-0.5">{{ $ev['hora']->format('H:i') }}</div>
                                    <div class="shrink-0 pt-1">
                                        @php
                                            $dot = match (true) {
                                                $ev['clase'] === 'marcacion' && $ev['tipo'] === 'ingreso' => 'bg-success',
                                                $ev['clase'] === 'marcacion' && $ev['tipo'] === 'salida' => 'bg-warning',
                                                $ev['tipo'] === 'terminado' => 'bg-success',
                                                $ev['tipo'] === 'abortado' => 'bg-danger',
                                                default => 'bg-primary',
                                            };
                                        @endphp
                                        <span class="block w-2.5 h-2.5 rounded-full {{ $dot }}"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm text-ink">{{ $ev['titulo'] }}</div>
                                        <div class="text-xs text-faint flex flex-wrap gap-x-3">
                                            @if ($ev['ubicacion'])<span><x-icon name="map-pin" class="w-3 h-3 inline" /> {{ $ev['ubicacion'] }}</span>@endif
                                            @if (! is_null($ev['dentro']))
                                                <span class="{{ $ev['dentro'] ? 'text-success' : 'text-danger' }}">{{ $ev['dentro'] ? 'dentro de zona' : 'fuera de zona' }}</span>
                                            @endif
                                            @if ($ev['lat'])<span class="tabular-nums">{{ $ev['lat'] }}, {{ $ev['lng'] }}</span>@endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    <p class="text-xs text-faint mt-3">Las horas se calculan emparejando cada ingreso con su salida (soporta jornadas que cruzan la medianoche). Las <strong>tardanzas</strong> requieren definir horarios/turnos (pendiente).</p>
</div>
