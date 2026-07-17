<?php

use App\Models\AsistenciaDia;
use App\Models\Empleado;
use App\Models\Marcacion;
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

    private function dia(int $empleadoId, string $fecha): AsistenciaDia
    {
        // whereDate compara solo la parte de fecha (evita duplicados por la hora del cast).
        return AsistenciaDia::where('empleado_id', $empleadoId)->whereDate('fecha', $fecha)->first()
            ?? new AsistenciaDia(['empleado_id' => $empleadoId, 'fecha' => $fecha]);
    }

    public function toggleRefrigerio(int $empleadoId, string $fecha, string $comida): void
    {
        abort_unless(auth()->user()->can('asistencia.registrar') || auth()->user()->can('asistencia.editar'), 403);
        abort_unless(in_array($comida, ['desayuno', 'almuerzo', 'cena'], true), 400);

        $d = $this->dia($empleadoId, $fecha);
        $d->{$comida} = ! $d->{$comida};
        $d->marcado_por = auth()->id();
        $d->save();
    }

    public function toggleVb(int $empleadoId, string $fecha): void
    {
        abort_unless(auth()->user()->can('asistencia.vb'), 403);

        $d = $this->dia($empleadoId, $fecha);
        $d->vb_supervisor = ! $d->vb_supervisor;
        $d->vb_por = $d->vb_supervisor ? auth()->id() : null;
        $d->vb_at = $d->vb_supervisor ? now() : null;
        $d->save();
    }

    /** Empareja marcaciones ingreso→salida (soporta cruce de medianoche). */
    private function jornadas($marcaciones): array
    {
        $jornadas = [];
        $abierta = null;
        foreach ($marcaciones as $m) {
            if ($m->tipo === 'ingreso') {
                $abierta = $m;
            } elseif ($m->tipo === 'salida' && $abierta) {
                $jornadas[] = ['ingreso' => $abierta->fecha_hora, 'salida' => $m->fecha_hora, 'minutos' => $abierta->fecha_hora->diffInMinutes($m->fecha_hora)];
                $abierta = null;
            }
        }
        if ($abierta) {
            $jornadas[] = ['ingreso' => $abierta->fecha_hora, 'salida' => null, 'minutos' => 0];
        }

        return $jornadas;
    }

    public function with(): array
    {
        $ini = $this->desde.' 00:00:00';
        $fin = $this->hasta.' 23:59:59';

        $marcs = Marcacion::whereBetween('fecha_hora', [$ini, $fin])
            ->when($this->empleado_id, fn ($q) => $q->where('empleado_id', $this->empleado_id))
            ->orderBy('fecha_hora')->orderBy('id')->get()
            ->groupBy('empleado_id');

        $empleados = Empleado::whereIn('id', $marcs->keys())->get()->keyBy('id');

        // Una fila por empleado × día (la jornada se atribuye a la fecha de su ingreso).
        $filas = [];
        foreach ($marcs as $eid => $lista) {
            $porDia = [];
            foreach ($this->jornadas($lista) as $j) {
                $dia = $j['ingreso']->toDateString();
                $porDia[$dia] ??= ['brutos' => 0, 'ingreso' => $j['ingreso'], 'salida' => $j['salida']];
                $porDia[$dia]['brutos'] += $j['minutos'];
                if ($j['ingreso']->lt($porDia[$dia]['ingreso'])) {
                    $porDia[$dia]['ingreso'] = $j['ingreso'];
                }
                if ($j['salida'] && (! $porDia[$dia]['salida'] || $j['salida']->gt($porDia[$dia]['salida']))) {
                    $porDia[$dia]['salida'] = $j['salida'];
                }
            }
            foreach ($porDia as $dia => $d) {
                $filas[] = ['empleado' => $empleados[$eid] ?? null, 'empleado_id' => (int) $eid, 'fecha' => $dia] + $d;
            }
        }

        usort($filas, fn ($a, $b) => [$b['fecha'], $a['empleado']?->apellidos ?? ''] <=> [$a['fecha'], $b['empleado']?->apellidos ?? '']);

        // Overlays (refrigerios/VB) de esos días
        $dias = AsistenciaDia::with('vbPor')
            ->when($this->empleado_id, fn ($q) => $q->where('empleado_id', $this->empleado_id))
            ->whereBetween('fecha', [$this->desde, $this->hasta])->get()
            ->keyBy(fn ($d) => $d->empleado_id.'|'.$d->fecha->toDateString());

        return [
            'filas' => $filas,
            'dias' => $dias,
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'puedeVb' => auth()->user()->can('asistencia.vb'),
            'puedeMarcar' => auth()->user()->can('asistencia.registrar') || auth()->user()->can('asistencia.editar'),
        ];
    }
}; ?>

@php
    $horas = fn ($min) => intdiv($min, 60).'h '.($min % 60).'m';
@endphp

<div>
    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
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
        <p class="text-xs text-faint flex-1 min-w-[200px]">Cada <strong>refrigerio</strong> (D/A/C) descuenta 1 h. El <strong>VB</strong> lo da el supervisor.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[860px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Día</th>
                    <th class="px-4 py-3">Ingreso</th>
                    <th class="px-4 py-3">Salida</th>
                    <th class="px-4 py-3 text-right">Brutas</th>
                    <th class="px-4 py-3 text-center">Refrigerio</th>
                    <th class="px-4 py-3 text-right">Netas</th>
                    <th class="px-4 py-3">VB</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $f)
                    @php
                        $d = $dias[$f['empleado_id'].'|'.$f['fecha']] ?? null;
                        $refMin = $d ? $d->refrigerio_minutos : 0;
                        $netas = \App\Models\AsistenciaDia::minutosNetos($f['brutos'], $refMin);
                        $chk = fn ($on) => $on
                            ? 'bg-primary text-white border-primary'
                            : 'bg-surface text-faint border-line hover:bg-canvas';
                    @endphp
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $f['empleado']?->apellidos }}, {{ $f['empleado']?->nombres }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ \Illuminate\Support\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $f['ingreso']->format('H:i') }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $f['salida']?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $horas($f['brutos']) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 justify-center">
                                @foreach (['desayuno' => 'D', 'almuerzo' => 'A', 'cena' => 'C'] as $comida => $letra)
                                    <button type="button"
                                            @if ($puedeMarcar) wire:click="toggleRefrigerio({{ $f['empleado_id'] }}, '{{ $f['fecha'] }}', '{{ $comida }}')" @else disabled @endif
                                            title="{{ ucfirst($comida) }}"
                                            class="w-7 h-7 rounded-lg border text-xs font-bold {{ $chk($d && $d->{$comida}) }} {{ $puedeMarcar ? '' : 'opacity-60 cursor-not-allowed' }}">{{ $letra }}</button>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $refMin > 0 ? 'text-primary' : 'text-ink' }}">{{ $horas($netas) }}</td>
                        <td class="px-4 py-3">
                            @if ($d && $d->vb_supervisor)
                                <div class="flex items-center gap-2">
                                    <button type="button" @if ($puedeVb) wire:click="toggleVb({{ $f['empleado_id'] }}, '{{ $f['fecha'] }}')" @else disabled @endif
                                            class="inline-flex items-center gap-1 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold {{ $puedeVb ? 'hover:brightness-95' : 'cursor-default' }}">✓ VB</button>
                                    <span class="text-xs text-faint">{{ $d->vbPor?->name }} · {{ $d->vb_at?->format('d/m H:i') }}</span>
                                </div>
                            @elseif ($puedeVb)
                                <button type="button" wire:click="toggleVb({{ $f['empleado_id'] }}, '{{ $f['fecha'] }}')"
                                        class="rounded-full border border-line text-muted px-2.5 py-0.5 text-xs font-semibold hover:bg-canvas">Dar VB</button>
                            @else
                                <span class="text-faint text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-faint">Sin marcaciones en el rango elegido.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
