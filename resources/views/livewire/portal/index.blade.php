<?php

use App\Models\Ausencia;
use App\Models\Documento;
use App\Models\Marcacion;
use App\Models\MovimientoVacaciones;
use App\Models\SolicitudVacaciones;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $empleadoId = null;

    // Solicitar vacaciones
    public bool $mostrarForm = false;
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $motivo = '';

    public function mount(): void
    {
        $this->empleadoId = auth()->user()->empleado?->id;
    }

    /** Marca ingreso o salida (el tipo se decide según la última marcación). */
    public function marcar(float $lat, float $lng, ?float $precision = null): void
    {
        abort_if($this->empleadoId === null, 403);

        $ultima = Marcacion::where('empleado_id', $this->empleadoId)
            ->orderByDesc('fecha_hora')->orderByDesc('id')->first();
        $tipo = ($ultima && $ultima->tipo === Marcacion::INGRESO) ? Marcacion::SALIDA : Marcacion::INGRESO;

        Marcacion::create([
            'empleado_id' => $this->empleadoId,
            'tipo' => $tipo,
            'fecha_hora' => now(),
            'latitud' => $lat,
            'longitud' => $lng,
            'precision_m' => $precision,
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'ip' => request()->ip(),
        ]);

        session()->flash('ok', ($tipo === Marcacion::INGRESO ? 'Ingreso' : 'Salida').' registrada correctamente.');
    }

    public function solicitar(): void
    {
        abort_if($this->empleadoId === null, 403);

        $datos = $this->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        SolicitudVacaciones::create([
            'empleado_id' => $this->empleadoId,
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'dias' => SolicitudVacaciones::calcularDias($datos['fecha_inicio'], $datos['fecha_fin']),
            'motivo' => $datos['motivo'] ?: null,
            'estado' => SolicitudVacaciones::PENDIENTE,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['fecha_inicio', 'fecha_fin', 'motivo']);
        $this->mostrarForm = false;
        session()->flash('ok', 'Solicitud enviada. Queda pendiente de aprobación.');
    }

    /** El trabajador solo puede cancelar SUS solicitudes aún pendientes. */
    public function cancelar(int $id): void
    {
        $s = SolicitudVacaciones::where('empleado_id', $this->empleadoId)->findOrFail($id);
        if ($s->estado === SolicitudVacaciones::PENDIENTE) {
            $s->update(['estado' => SolicitudVacaciones::CANCELADA]);
        }
        session()->flash('ok', 'Solicitud cancelada.');
    }

    public function with(): array
    {
        if ($this->empleadoId === null) {
            return ['empleado' => null];
        }

        $empleado = \App\Models\Empleado::with(['area', 'cargo', 'sede'])->find($this->empleadoId);

        $ultimaMarcacion = Marcacion::where('empleado_id', $this->empleadoId)
            ->orderByDesc('fecha_hora')->orderByDesc('id')->first();

        return [
            'empleado' => $empleado,
            'jornadaAbierta' => $ultimaMarcacion && $ultimaMarcacion->tipo === 'ingreso',
            'ultimaMarcacion' => $ultimaMarcacion,
            'marcaciones' => Marcacion::where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_hora')->orderByDesc('id')->limit(20)->get(),
            'documentos' => Documento::with('tipoDocumento')->where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_vencimiento')->get(),
            'solicitudes' => SolicitudVacaciones::where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_inicio')->get(),
            'saldoVac' => (float) MovimientoVacaciones::where('empleado_id', $this->empleadoId)->sum('dias'),
            'ausencias' => Ausencia::where('empleado_id', $this->empleadoId)->orderByDesc('fecha_inicio')->get(),
            'diasCalc' => SolicitudVacaciones::calcularDias($this->fecha_inicio, $this->fecha_fin),
        ];
    }
}; ?>

<div x-data="{ tab: 'asistencia' }">
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    @if (! $empleado)
        <div class="bg-surface border border-line rounded-xl p-8 text-center">
            <p class="text-muted">Tu usuario aún no está vinculado a un empleado. Contacta a RRHH.</p>
        </div>
    @else
        {{-- Cabecera --}}
        <div class="bg-gradient-to-br from-navy via-primary-dark to-primary rounded-2xl shadow-lg p-6 text-white mb-5">
            <p class="text-white/80 text-sm">Mi espacio</p>
            <h3 class="text-2xl font-semibold tracking-tight">{{ $empleado->nombres }} {{ $empleado->apellidos }}</h3>
            <p class="text-white/85 text-sm mt-0.5">
                {{ $empleado->cargo?->nombre ?? 'Sin cargo' }} · {{ $empleado->area?->nombre ?? 'Sin área' }}
            </p>
        </div>

        {{-- Pestañas --}}
        @php $tabBtn = 'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors'; @endphp
        <div class="border-b border-line mb-5 flex flex-wrap gap-1">
            <button @click="tab='asistencia'" :class="tab==='asistencia' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Asistencia</button>
            <button @click="tab='datos'" :class="tab==='datos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis datos</button>
            <button @click="tab='documentos'" :class="tab==='documentos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis documentos ({{ $documentos->count() }})</button>
            <button @click="tab='vacaciones'" :class="tab==='vacaciones' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis vacaciones</button>
            <button @click="tab='ausencias'" :class="tab==='ausencias' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis ausencias ({{ $ausencias->count() }})</button>
        </div>

        {{-- ASISTENCIA --}}
        <section x-show="tab==='asistencia'">
            <div class="bg-surface border border-line rounded-xl p-6 mb-5 text-center">
                @if ($jornadaAbierta)
                    <div class="inline-flex items-center gap-2 rounded-full bg-success-tint text-success px-3 py-1 text-xs font-semibold mb-3">
                        <span class="w-2 h-2 rounded-full bg-current"></span>Jornada abierta desde {{ $ultimaMarcacion->fecha_hora->format('d/m/Y H:i') }}
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 rounded-full bg-canvas text-muted border border-line px-3 py-1 text-xs font-semibold mb-3">
                        <span class="w-2 h-2 rounded-full bg-current"></span>Sin jornada abierta
                    </div>
                @endif

                <div x-data="{ cargando: false }">
                    <button
                        x-on:click="
                            if (!navigator.geolocation) { alert('Tu dispositivo no permite ubicación (GPS).'); return; }
                            cargando = true;
                            navigator.geolocation.getCurrentPosition(
                                p => { $wire.marcar(p.coords.latitude, p.coords.longitude, p.coords.accuracy).then(() => cargando = false); },
                                e => { cargando = false; alert('Necesitamos tu ubicación para marcar: ' + e.message); },
                                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                            )
                        "
                        :disabled="cargando"
                        class="inline-flex items-center gap-2 rounded-xl px-8 py-4 text-lg font-bold text-white shadow-lg disabled:opacity-60
                               {{ $jornadaAbierta ? 'bg-danger hover:brightness-95' : 'bg-success hover:brightness-95' }}">
                        <x-icon name="map-pin" class="w-6 h-6" />
                        <span x-show="!cargando">{{ $jornadaAbierta ? 'Marcar salida' : 'Marcar ingreso' }}</span>
                        <span x-show="cargando">Obteniendo ubicación…</span>
                    </button>
                    <p class="text-xs text-faint mt-3">Se registrará tu ubicación (GPS) y la hora exacta. Requiere permitir la ubicación en tu celular.</p>
                </div>
            </div>

            {{-- Marcaciones recientes --}}
            <div class="bg-surface border border-line rounded-xl overflow-x-auto">
                <div class="px-4 py-2 text-xs uppercase tracking-wide text-faint border-b border-line">Mis marcaciones recientes</div>
                <table class="w-full text-sm min-w-[520px]">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Fecha y hora</th>
                            <th class="px-4 py-3">Ubicación (GPS)</th>
                            <th class="px-4 py-3">Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($marcaciones as $m)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-4 py-3">
                                    @if ($m->tipo === 'ingreso')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">Ingreso</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-tint text-warning px-2.5 py-0.5 text-xs font-semibold">Salida</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ $m->fecha_hora->format('d/m/Y H:i:s') }}</td>
                                <td class="px-4 py-3 text-faint text-xs tabular-nums">
                                    @if ($m->latitud !== null){{ $m->latitud }}, {{ $m->longitud }} @if ($m->precision_m)· ±{{ (int) $m->precision_m }}m @endif
                                    @else — @endif
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if ($m->es_manual)<span class="text-warning">Manual (supervisor)</span>@else<span class="text-muted">App</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Aún no tienes marcaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- MIS DATOS --}}
        <section x-show="tab==='datos'" x-cloak class="bg-surface border border-line rounded-xl p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4 text-sm">
                @php
                    $campos = [
                        'Documento' => trim(($empleado->tipo_documento ?? '').' '.($empleado->numero_documento ?? '')),
                        'Fecha de nacimiento' => optional($empleado->fecha_nacimiento)->format('d/m/Y'),
                        'Teléfono' => $empleado->telefono,
                        'Correo' => $empleado->correo,
                        'Dirección' => $empleado->direccion,
                        'Sede' => $empleado->sede?->nombre,
                        'Fecha de ingreso' => optional($empleado->fecha_ingreso)->format('d/m/Y'),
                        'Tipo de contrato' => $empleado->tipo_contrato,
                        'Sistema pensionario' => $empleado->sistema_pensionario,
                        'Régimen de salud' => $empleado->regimen_salud,
                        'Banco' => $empleado->banco,
                        'N° de cuenta' => $empleado->numero_cuenta,
                        'CCI' => $empleado->cci,
                    ];
                @endphp
                @foreach ($campos as $label => $valor)
                    <div>
                        <dt class="text-faint text-xs uppercase tracking-wide">{{ $label }}</dt>
                        <dd class="text-ink mt-0.5">{{ $valor ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-xs text-faint mt-4">¿Algún dato incorrecto? Comunícalo a RRHH para actualizarlo.</p>
        </section>

        {{-- MIS DOCUMENTOS --}}
        <section x-show="tab==='documentos'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">Vencimiento</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Archivo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documentos as $d)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $d->tipoDocumento?->nombre }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ optional($d->fecha_vencimiento)->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    [$c, $t] = match ($d->estado) {
                                        'vigente' => ['bg-success-tint text-success', 'Vigente'],
                                        'por_vencer' => ['bg-warning-tint text-warning', 'Por vencer'],
                                        'vencido' => ['bg-danger-tint text-danger', 'Vencido'],
                                        default => ['bg-canvas text-faint', 'Sin vigencia'],
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}"><span class="w-2 h-2 rounded-full bg-current"></span>{{ $t }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($d->archivo_path)
                                    <a href="{{ Storage::url($d->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                                @else <span class="text-faint">—</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">No tienes documentos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- MIS VACACIONES --}}
        <section x-show="tab==='vacaciones'" x-cloak>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="rounded-xl border border-line bg-surface px-5 py-3">
                    <div class="text-xs uppercase tracking-wide text-faint">Saldo disponible</div>
                    <div class="text-2xl font-bold tabular-nums {{ $saldoVac < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($saldoVac, 1) }} <span class="text-sm font-medium text-muted">días</span></div>
                </div>
                <button wire:click="$set('mostrarForm', true)" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                    <x-icon name="plus" class="w-4 h-4" /> Solicitar vacaciones
                </button>
            </div>

            <div class="bg-surface border border-line rounded-xl overflow-x-auto">
                <table class="w-full text-sm min-w-[520px]">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                            <th class="px-4 py-3">Periodo</th>
                            <th class="px-4 py-3 text-center">Días</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($solicitudes as $s)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-4 py-3 text-muted tabular-nums">{{ $s->fecha_inicio->format('d/m/Y') }} → {{ $s->fecha_fin->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-center tabular-nums">{{ $s->dias }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        [$c, $t] = match ($s->estado) {
                                            'aprobada' => ['bg-success-tint text-success', 'Aprobada'],
                                            'rechazada' => ['bg-danger-tint text-danger', 'Rechazada'],
                                            'cancelada' => ['bg-canvas text-faint', 'Cancelada'],
                                            default => ['bg-warning-tint text-warning', 'Pendiente'],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}">{{ $t }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($s->estado === 'pendiente')
                                        <button wire:click="cancelar({{ $s->id }})" wire:confirm="¿Cancelar tu solicitud?" class="text-muted hover:underline text-sm font-medium">Cancelar</button>
                                    @else <span class="text-faint">—</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-faint">No tienes solicitudes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- MIS AUSENCIAS --}}
        <section x-show="tab==='ausencias'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Periodo</th>
                        <th class="px-4 py-3 text-center">Días</th>
                        <th class="px-4 py-3">Goce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ausencias as $a)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $a->tipo_label }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $a->fecha_inicio->format('d/m/Y') }} → {{ $a->fecha_fin->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ $a->dias }}</td>
                            <td class="px-4 py-3">{!! $a->con_goce ? '<span class="text-success text-xs font-semibold">Con goce</span>' : '<span class="text-muted text-xs font-semibold">Sin goce</span>' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">No tienes ausencias registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Modal solicitar vacaciones --}}
        @if ($mostrarForm)
            <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
                <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <h3 class="text-lg font-semibold text-navy">Solicitar vacaciones</h3>
                        <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                    </div>
                    <form wire:submit="solicitar" class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Desde *</label>
                                <input type="date" wire:model.live="fecha_inicio" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('fecha_inicio') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Hasta *</label>
                                <input type="date" wire:model.live="fecha_fin" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('fecha_fin') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        @if ($diasCalc > 0)
                            <div class="rounded-lg bg-primary-tint text-primary-dark px-3 py-2 text-sm"><strong>{{ $diasCalc }}</strong> día(s) · saldo actual: {{ number_format($saldoVac, 1) }}</div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Motivo (opcional)</label>
                            <input type="text" wire:model="motivo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                            <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Enviar solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
