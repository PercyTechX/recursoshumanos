<?php

use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\TicketAvance;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
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

    <p class="text-xs text-faint mt-3">Las horas se calculan emparejando cada ingreso con su salida (soporta jornadas que cruzan la medianoche). Las <strong>tardanzas</strong> requieren definir horarios/turnos (pendiente).</p>
</div>
