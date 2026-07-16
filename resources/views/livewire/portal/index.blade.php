<?php

use App\Models\Ausencia;
use App\Models\BoletaPago;
use App\Models\Documento;
use App\Models\Marcacion;
use App\Models\MovimientoVacaciones;
use App\Models\RendicionDeposito;
use App\Models\SolicitudVacaciones;
use App\Models\Ticket;
use App\Models\TicketAvance;
use App\Models\TicketTecnico;
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

    // ---- Operación de tickets ----

    private function jornadaAbiertaAhora(): bool
    {
        $u = Marcacion::where('empleado_id', $this->empleadoId)->orderByDesc('fecha_hora')->orderByDesc('id')->first();

        return $u && $u->tipo === Marcacion::INGRESO;
    }

    private function ticketActivoDelEmpleado(): ?TicketTecnico
    {
        return TicketTecnico::where('empleado_id', $this->empleadoId)
            ->whereIn('estado_trabajo', TicketTecnico::ACTIVOS)
            ->with(['ticket.cliente', 'ticket.sucursal', 'ticket.sede'])
            ->first();
    }

    private function registrarAvance(TicketTecnico $tt, string $estado, ?float $lat, ?float $lng, ?float $precision, ?bool $dentro): void
    {
        TicketAvance::create([
            'ticket_tecnico_id' => $tt->id,
            'estado' => $estado,
            'fecha_hora' => now(),
            'latitud' => $lat,
            'longitud' => $lng,
            'precision_m' => $precision,
            'dentro_geocerca' => $dentro,
        ]);
    }

    public function tomarTicket(int $ticketId, float $lat, float $lng, ?float $precision = null): void
    {
        abort_if($this->empleadoId === null, 403);

        if (! $this->jornadaAbiertaAhora()) {
            session()->flash('error', 'Debes marcar tu ingreso antes de tomar un ticket.');

            return;
        }
        if ($this->ticketActivoDelEmpleado()) {
            session()->flash('error', 'Ya tienes un ticket activo. Termínalo o abórtalo antes de tomar otro.');

            return;
        }
        $ticket = Ticket::where('estado', Ticket::ABIERTO)->find($ticketId);
        if (! $ticket) {
            session()->flash('error', 'Ese ticket ya no está disponible.');

            return;
        }

        $tt = TicketTecnico::updateOrCreate(
            ['ticket_id' => $ticket->id, 'empleado_id' => $this->empleadoId],
            ['estado_trabajo' => TicketTecnico::INICIADO, 'liberado_por' => null, 'motivo' => null],
        );
        $dentro = $ticket->ubicacion()?->contiene($lat, $lng) ?? false;
        $this->registrarAvance($tt, TicketTecnico::INICIADO, $lat, $lng, $precision, $dentro);

        session()->flash('ok', 'Ticket iniciado.');
    }

    public function avanzar(int $ticketTecnicoId, float $lat, float $lng, ?float $precision = null): void
    {
        $tt = TicketTecnico::where('empleado_id', $this->empleadoId)->with('ticket')->findOrFail($ticketTecnicoId);
        $dentro = $tt->ticket?->ubicacion()?->contiene($lat, $lng) ?? false;

        if ($tt->estado_trabajo === TicketTecnico::INICIADO) {
            if (! $dentro) {
                session()->flash('error', 'Debes estar DENTRO del local para marcar "En ejecución".');

                return;
            }
            $tt->update(['estado_trabajo' => TicketTecnico::EN_EJECUCION]);
            $this->registrarAvance($tt, TicketTecnico::EN_EJECUCION, $lat, $lng, $precision, $dentro);
            session()->flash('ok', 'Ticket en ejecución.');
        } elseif ($tt->estado_trabajo === TicketTecnico::EN_EJECUCION) {
            if (! $dentro) {
                session()->flash('error', 'Debes estar DENTRO del local para Terminar.');

                return;
            }
            $tt->update(['estado_trabajo' => TicketTecnico::TERMINADO]);
            $this->registrarAvance($tt, TicketTecnico::TERMINADO, $lat, $lng, $precision, $dentro);
            session()->flash('ok', 'Ticket terminado. Quedas libre para tomar otro.');
        }
    }

    public function abortar(?float $lat = null, ?float $lng = null): void
    {
        $tt = $this->ticketActivoDelEmpleado();
        if (! $tt) {
            return;
        }
        $dentro = ($lat !== null) ? ($tt->ticket?->ubicacion()?->contiene($lat, $lng) ?? false) : null;
        $tt->update(['estado_trabajo' => TicketTecnico::ABORTADO]);
        $this->registrarAvance($tt, TicketTecnico::ABORTADO, $lat, $lng, null, $dentro);
        session()->flash('ok', 'Misión abortada. Quedas libre para tomar otro ticket.');
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
    /** El trabajador confirma la recepción de SU boleta (valor probatorio). */
    public function confirmarRecepcionBoleta(int $id): void
    {
        abort_if($this->empleadoId === null, 403);
        $b = BoletaPago::where('empleado_id', $this->empleadoId)->findOrFail($id);
        if (! $b->recibida_at) {
            $b->update(['recibida_at' => now()]);
            session()->flash('ok', 'Recepción de la boleta confirmada. ¡Gracias!');
        }
    }

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
            'ticketActivo' => $ticketActivo = $this->ticketActivoDelEmpleado(),
            'ticketsDisponibles' => $ticketActivo
                ? collect()
                : Ticket::abiertos()->with(['cliente', 'sucursal', 'sede'])->orderByDesc('id')->limit(50)->get(),
            'documentos' => Documento::with('tipoDocumento')->where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_vencimiento')->get(),
            'solicitudes' => SolicitudVacaciones::where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_inicio')->get(),
            'saldoVac' => $empleado->saldo_vacaciones,
            'ausencias' => Ausencia::where('empleado_id', $this->empleadoId)->orderByDesc('fecha_inicio')->get(),
            'diasCalc' => SolicitudVacaciones::calcularDias($this->fecha_inicio, $this->fecha_fin),
            'rendiciones' => RendicionDeposito::where('empleado_id', $this->empleadoId)->with('ticket')->orderByDesc('id')->get(),
            'boletas' => BoletaPago::where('empleado_id', $this->empleadoId)->orderByDesc('periodo')->orderBy('tipo')->get(),
        ];
    }
}; ?>

<div x-data="{ tab: 'asistencia' }">
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger-tint text-danger px-4 py-2 text-sm font-medium">{{ session('error') }}</div>
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
            <button @click="tab='tickets'" :class="tab==='tickets' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Tickets @if ($ticketActivo)<span class="ml-1 inline-flex items-center rounded-full bg-primary-tint text-primary px-1.5 text-[10px] font-bold">1</span>@endif</button>
            <button @click="tab='datos'" :class="tab==='datos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis datos</button>
            <button @click="tab='documentos'" :class="tab==='documentos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis documentos ({{ $documentos->count() }})</button>
            <button @click="tab='boletas'" :class="tab==='boletas' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis boletas ({{ $boletas->count() }}) @if ($boletas->whereNull('recibida_at')->count())<span class="ml-1 inline-flex items-center rounded-full bg-warning-tint text-warning px-1.5 text-[10px] font-bold">{{ $boletas->whereNull('recibida_at')->count() }}</span>@endif</button>
            <button @click="tab='vacaciones'" :class="tab==='vacaciones' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis vacaciones</button>
            <button @click="tab='ausencias'" :class="tab==='ausencias' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis ausencias ({{ $ausencias->count() }})</button>
            @if ($rendiciones->count())
                <button @click="tab='rendiciones'" :class="tab==='rendiciones' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Mis rendiciones ({{ $rendiciones->count() }})</button>
            @endif
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

        {{-- TICKETS --}}
        <section x-show="tab==='tickets'" x-cloak x-data="{
            cargando: false,
            abortCont: null, abortTimer: null,
            conGps(cb) {
                if (!navigator.geolocation) { alert('Tu dispositivo no permite ubicación (GPS).'); return; }
                this.cargando = true;
                navigator.geolocation.getCurrentPosition(
                    p => { this.cargando = false; cb(p.coords.latitude, p.coords.longitude, p.coords.accuracy); },
                    e => { this.cargando = false; alert('Necesitamos tu ubicación: ' + e.message); },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            },
            iniciarAbort() {
                if (!confirm('¿Seguro que quieres ABORTAR esta misión?')) return;
                this.abortCont = 10;
                this.abortTimer = setInterval(() => { this.abortCont--; if (this.abortCont <= 0) { clearInterval(this.abortTimer); this.ejecutarAbort(); } }, 1000);
            },
            cancelarAbort() { clearInterval(this.abortTimer); this.abortCont = null; },
            ejecutarAbort() {
                this.abortCont = null;
                if (navigator.geolocation) { navigator.geolocation.getCurrentPosition(p => $wire.abortar(p.coords.latitude, p.coords.longitude), () => $wire.abortar(null, null), { timeout: 8000 }); }
                else { $wire.abortar(null, null); }
            }
        }">
            @if ($ticketActivo)
                @php $t = $ticketActivo->ticket; @endphp
                <div class="bg-surface border border-line rounded-xl p-6 mb-5">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <div class="text-lg font-semibold text-navy">{{ $t?->ticket_atencion }}</div>
                            <div class="text-sm text-muted">{{ $t?->cliente?->nombre_comercial ?: $t?->cliente?->razon_social }}</div>
                            <div class="text-xs text-faint mt-0.5"><span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5" /> {{ $t?->ubicacion_nombre }}</span></div>
                        </div>
                        @php
                            [$bc, $bt] = match ($ticketActivo->estado_trabajo) {
                                'en_ejecucion' => ['bg-primary-tint text-primary', 'En ejecución'],
                                default => ['bg-warning-tint text-warning', 'Iniciado'],
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $bc }}"><span class="w-2 h-2 rounded-full bg-current"></span>{{ $bt }}</span>
                    </div>

                    {{-- Progreso de estados --}}
                    <div class="flex items-center gap-2 text-xs text-faint mb-4">
                        <span class="font-semibold text-success">1. Iniciado</span> →
                        <span class="{{ $ticketActivo->estado_trabajo === 'en_ejecucion' ? 'font-semibold text-primary' : '' }}">2. En ejecución</span> →
                        <span>3. Terminado</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($ticketActivo->estado_trabajo === 'iniciado')
                            <button :disabled="cargando" x-on:click="conGps((la,lo,ac) => $wire.avanzar({{ $ticketActivo->id }}, la, lo, ac))"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2 disabled:opacity-60">
                                <x-icon name="map-pin" class="w-4 h-4" /> <span x-show="!cargando">Marcar "En ejecución" (en el local)</span><span x-show="cargando">Ubicando…</span>
                            </button>
                        @elseif ($ticketActivo->estado_trabajo === 'en_ejecucion')
                            <button :disabled="cargando" x-on:click="conGps((la,lo,ac) => $wire.avanzar({{ $ticketActivo->id }}, la, lo, ac))"
                                    class="inline-flex items-center gap-2 rounded-lg bg-success hover:brightness-95 text-white text-sm font-semibold px-4 py-2 disabled:opacity-60">
                                <x-icon name="check" class="w-4 h-4" /> <span x-show="!cargando">Terminar (en el local)</span><span x-show="cargando">Ubicando…</span>
                            </button>
                        @endif

                        {{-- Abortar misión --}}
                        <template x-if="abortCont === null">
                            <button x-on:click="iniciarAbort()" class="inline-flex items-center gap-2 rounded-lg border border-danger text-danger hover:bg-danger-tint text-sm font-semibold px-4 py-2">
                                <x-icon name="ban" class="w-4 h-4" /> Abortar misión
                            </button>
                        </template>
                        <template x-if="abortCont !== null">
                            <div class="inline-flex items-center gap-3 rounded-lg bg-danger-tint text-danger px-4 py-2 text-sm font-semibold">
                                Abortando en <span x-text="abortCont"></span>s
                                <button x-on:click="cancelarAbort()" class="rounded-lg bg-surface border border-line text-ink px-3 py-1 text-xs">Cancelar</button>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-faint mt-3">"En ejecución" y "Terminar" solo se pueden marcar <strong>dentro del local</strong> (geocerca).</p>
                </div>
            @else
                @unless ($jornadaAbierta)
                    <div class="mb-4 rounded-lg bg-warning-tint text-warning px-4 py-3 text-sm font-medium">Marca tu <strong>ingreso</strong> (pestaña Asistencia) para poder tomar tickets.</div>
                @endunless

                <div class="bg-surface border border-line rounded-xl overflow-x-auto">
                    <div class="px-4 py-2 text-xs uppercase tracking-wide text-faint border-b border-line">Tickets abiertos</div>
                    <table class="w-full text-sm min-w-[560px]">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                                <th class="px-4 py-3">Ticket</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Ubicación</th>
                                <th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ticketsDisponibles as $tk)
                                <tr class="border-b border-line last:border-0">
                                    <td class="px-4 py-3 font-medium text-ink">{{ $tk->ticket_atencion }}</td>
                                    <td class="px-4 py-3 text-muted">{{ $tk->cliente?->nombre_comercial ?: $tk->cliente?->razon_social }}</td>
                                    <td class="px-4 py-3 text-muted text-xs"><span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5 text-faint" /> {{ $tk->ubicacion_nombre }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($jornadaAbierta)
                                            <button :disabled="cargando" x-on:click="conGps((la,lo,ac) => $wire.tomarTicket({{ $tk->id }}, la, lo, ac))"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-3 py-1.5 disabled:opacity-60">
                                                <span x-show="!cargando">Tomar</span><span x-show="cargando">…</span>
                                            </button>
                                        @else
                                            <span class="text-faint text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-faint">No hay tickets abiertos por ahora.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
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
                                @if ($d->sharepoint_item_id || $d->archivo_path)
                                    <a href="{{ route('portal.documento', $d) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                                @else <span class="text-faint">—</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">No tienes documentos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- MIS BOLETAS DE PAGO --}}
        <section x-show="tab==='boletas'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
            <table class="w-full text-sm min-w-[560px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Periodo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Boleta</th>
                        <th class="px-4 py-3">Recepción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($boletas as $b)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink font-medium">{{ $b->periodo_label }}</td>
                            <td class="px-4 py-3 text-muted">{{ $b->tipo }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('portal.boleta', $b) }}" target="_blank" class="text-primary hover:underline">Ver PDF</a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($b->recibida_at)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">✓ Recibida el {{ $b->recibida_at->format('d/m/Y') }}</span>
                                @else
                                    <button wire:click="confirmarRecepcionBoleta({{ $b->id }})"
                                            wire:confirm="¿Confirmas que recibiste esta boleta de pago?"
                                            class="rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-3 py-1.5">
                                        Confirmar recepción
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Aún no tienes boletas de pago publicadas.</td></tr>
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

        {{-- MIS RENDICIONES --}}
        @if ($rendiciones->count())
            <section x-show="tab==='rendiciones'" x-cloak class="space-y-3">
                @php
                    $badgeR = [
                        'Rindiendo' => 'bg-primary-tint text-primary', 'Por Revisar' => 'bg-warning-tint text-warning',
                        'Finalizado' => 'bg-success-tint text-success', 'Observado' => 'bg-danger-tint text-danger',
                        'Anulado' => 'bg-canvas text-faint',
                    ];
                @endphp
                @foreach ($rendiciones as $r)
                    <div class="bg-surface border border-line rounded-xl p-4 flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-ink">Ticket {{ $r->ticket?->ticket_atencion }} · <span class="tabular-nums">S/ {{ number_format($r->monto, 2) }}</span></div>
                            <div class="text-xs text-faint">{{ $r->local_nombre }} · {{ $r->dia?->format('d/m/Y') }}</div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeR[$r->estado] ?? '' }}"><span class="w-2 h-2 rounded-full bg-current"></span>{{ $r->estado }}</span>
                        <a href="{{ route('rendir', $r->token) }}" target="_blank"
                           class="rounded-lg text-sm font-semibold px-4 py-2 {{ $r->editable_por_tecnico ? 'bg-primary hover:bg-primary-dark text-white' : 'border border-line text-muted hover:bg-canvas' }}">
                            {{ $r->editable_por_tecnico ? 'Rendir' : 'Ver' }}
                        </a>
                    </div>
                @endforeach
            </section>
        @endif

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
