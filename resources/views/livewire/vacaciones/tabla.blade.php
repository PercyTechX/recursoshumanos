<?php

use App\Models\Empleado;
use App\Models\MovimientoVacaciones;
use App\Models\SolicitudVacaciones;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $filtroEstado = '';

    // Form nueva solicitud
    public bool $mostrarForm = false;
    public ?int $empleado_id = null;
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $motivo = '';

    // Rechazo
    public bool $mostrarRechazo = false;
    public ?int $rechazandoId = null;
    public string $comentario_decision = '';

    // Retorno anticipado (interrupción)
    public bool $mostrarRetorno = false;
    public ?int $retornandoId = null;
    public string $fecha_fin_real = '';

    public function puedeDecidir(): bool
    {
        return auth()->user()?->hasAnyRole(['RRHH', 'Gerencia', 'Supervisor']) ?? false;
    }

    public function nuevo(): void
    {
        $this->reset(['empleado_id', 'fecha_inicio', 'fecha_fin', 'motivo']);
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ], [], ['empleado_id' => 'empleado']);

        SolicitudVacaciones::create([
            'empleado_id' => $datos['empleado_id'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'dias' => SolicitudVacaciones::calcularDias($datos['fecha_inicio'], $datos['fecha_fin']),
            'motivo' => $datos['motivo'] ?: null,
            'estado' => SolicitudVacaciones::PENDIENTE,
            'created_by' => auth()->id(),
        ]);

        $this->mostrarForm = false;
        session()->flash('ok', 'Solicitud de vacaciones registrada.');
    }

    public function aprobar(int $id): void
    {
        abort_unless($this->puedeDecidir(), 403);
        $s = SolicitudVacaciones::findOrFail($id);
        if ($s->estado !== SolicitudVacaciones::PENDIENTE) {
            return;
        }

        $s->update([
            'estado' => SolicitudVacaciones::APROBADA,
            'decidida_por' => auth()->id(),
            'fecha_decision' => now()->toDateString(),
        ]);

        // Descuenta del saldo (movimiento gozado, negativo)
        MovimientoVacaciones::create([
            'empleado_id' => $s->empleado_id,
            'fecha' => $s->fecha_inicio->toDateString(),
            'tipo' => MovimientoVacaciones::GOZADO,
            'dias' => -1 * $s->dias,
            'solicitud_id' => $s->id,
            'observacion' => 'Vacaciones aprobadas ('.$s->fecha_inicio->format('d/m/Y').' al '.$s->fecha_fin->format('d/m/Y').')',
            'created_by' => auth()->id(),
        ]);

        session()->flash('ok', 'Solicitud aprobada y descontada del saldo.');
    }

    public function abrirRechazo(int $id): void
    {
        abort_unless($this->puedeDecidir(), 403);
        $this->rechazandoId = $id;
        $this->comentario_decision = '';
        $this->resetErrorBag();
        $this->mostrarRechazo = true;
    }

    public function rechazar(): void
    {
        abort_unless($this->puedeDecidir(), 403);
        $s = SolicitudVacaciones::findOrFail($this->rechazandoId);
        if ($s->estado === SolicitudVacaciones::PENDIENTE) {
            $s->update([
                'estado' => SolicitudVacaciones::RECHAZADA,
                'decidida_por' => auth()->id(),
                'fecha_decision' => now()->toDateString(),
                'comentario_decision' => $this->comentario_decision ?: null,
            ]);
        }
        $this->mostrarRechazo = false;
        session()->flash('ok', 'Solicitud rechazada.');
    }

    public function abrirRetorno(int $id): void
    {
        abort_unless($this->puedeDecidir(), 403);
        $this->retornandoId = $id;
        $this->fecha_fin_real = '';
        $this->resetErrorBag();
        $this->mostrarRetorno = true;
    }

    /** Registra que el trabajador volvió antes: reintegra al saldo los días no gozados. */
    public function registrarRetorno(): void
    {
        abort_unless($this->puedeDecidir(), 403);
        $s = SolicitudVacaciones::findOrFail($this->retornandoId);

        $this->validate([
            'fecha_fin_real' => [
                'required', 'date',
                'after_or_equal:'.$s->fecha_inicio->toDateString(),
                'before:'.$s->fecha_fin->toDateString(),
            ],
        ], [
            'fecha_fin_real.after_or_equal' => 'El último día real no puede ser antes del inicio de las vacaciones.',
            'fecha_fin_real.before' => 'Si volvió el último día o después, no hubo interrupción.',
        ], ['fecha_fin_real' => 'último día real']);

        if ($s->estado !== SolicitudVacaciones::APROBADA || $s->interrumpida) {
            return;
        }

        $diasReales = SolicitudVacaciones::calcularDias($s->fecha_inicio->toDateString(), $this->fecha_fin_real);
        $reintegro = $s->dias - $diasReales;

        $s->update([
            'fecha_fin_real' => $this->fecha_fin_real,
            'dias_reintegrados' => $reintegro,
        ]);

        if ($reintegro > 0) {
            MovimientoVacaciones::create([
                'empleado_id' => $s->empleado_id,
                'fecha' => $this->fecha_fin_real,
                'tipo' => MovimientoVacaciones::REINTEGRO,
                'dias' => $reintegro,
                'solicitud_id' => $s->id,
                'observacion' => 'Retorno anticipado: gozó '.$diasReales.' de '.$s->dias.' días',
                'created_by' => auth()->id(),
            ]);
        }

        $this->mostrarRetorno = false;
        session()->flash('ok', "Retorno registrado. Se reintegraron {$reintegro} día(s) al saldo.");
    }

    public function cancelar(int $id): void
    {
        $s = SolicitudVacaciones::findOrFail($id);
        if ($s->estado === SolicitudVacaciones::PENDIENTE) {
            $s->update(['estado' => SolicitudVacaciones::CANCELADA]);
        }
        session()->flash('ok', 'Solicitud cancelada.');
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $solicitudes = SolicitudVacaciones::query()
            ->with(['empleado', 'decididaPor'])
            ->when($this->buscar, fn ($q) => $q->whereHas('empleado', fn ($w) => $w
                ->where('nombres', 'like', '%'.$this->buscar.'%')
                ->orWhere('apellidos', 'like', '%'.$this->buscar.'%')
                ->orWhere('numero_documento', 'like', '%'.$this->buscar.'%')))
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderByDesc('created_at')
            ->paginate(10);

        return [
            'solicitudes' => $solicitudes,
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'diasCalc' => SolicitudVacaciones::calcularDias($this->fecha_inicio, $this->fecha_fin),
            'saldoEmpleado' => $this->empleado_id
                ? (float) MovimientoVacaciones::where('empleado_id', $this->empleado_id)->sum('dias')
                : null,
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2">
            <x-icon name="search" class="w-4 h-4 text-faint shrink-0" />
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por empleado o documento…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>
        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendientes</option>
            <option value="aprobada">Aprobadas</option>
            <option value="rechazada">Rechazadas</option>
            <option value="cancelada">Canceladas</option>
        </select>
        <button wire:click="nuevo" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">+ Nueva solicitud</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3 text-center">Días</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($solicitudes as $s)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">
                            {{ $s->empleado?->apellidos }}, {{ $s->empleado?->nombres }}
                            @if ($s->motivo)<div class="text-faint text-xs">{{ $s->motivo }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">
                            {{ $s->fecha_inicio->format('d/m/Y') }} → {{ $s->fecha_fin->format('d/m/Y') }}
                            @if ($s->interrumpida)
                                <div class="text-warning text-xs">Volvió el {{ $s->fecha_fin_real->format('d/m/Y') }} · +{{ number_format((float) $s->dias_reintegrados, 0) }} reintegrados</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums font-semibold text-ink">{{ $s->dias }}</td>
                        <td class="px-4 py-3">
                            @php
                                [$c, $t] = match ($s->estado) {
                                    'aprobada' => ['bg-success-tint text-success', 'Aprobada'],
                                    'rechazada' => ['bg-danger-tint text-danger', 'Rechazada'],
                                    'cancelada' => ['bg-canvas text-faint', 'Cancelada'],
                                    default => ['bg-warning-tint text-warning', 'Pendiente'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>{{ $t }}
                            </span>
                            @if ($s->estado === 'rechazada' && $s->comentario_decision)
                                <div class="text-faint text-xs mt-0.5">{{ $s->comentario_decision }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($s->estado === 'pendiente')
                                @if ($this->puedeDecidir())
                                    <button wire:click="aprobar({{ $s->id }})" wire:confirm="¿Aprobar estas vacaciones? Se descontarán del saldo." class="text-success hover:underline text-sm font-medium">Aprobar</button>
                                    <button wire:click="abrirRechazo({{ $s->id }})" class="ml-3 text-danger hover:underline text-sm font-medium">Rechazar</button>
                                @endif
                                <button wire:click="cancelar({{ $s->id }})" wire:confirm="¿Cancelar esta solicitud?" class="ml-3 text-muted hover:underline text-sm font-medium">Cancelar</button>
                            @elseif ($s->estado === 'aprobada')
                                @if ($this->puedeDecidir() && ! $s->interrumpida)
                                    <button wire:click="abrirRetorno({{ $s->id }})" class="text-warning hover:underline text-sm font-medium" title="La empresa lo hizo volver antes">Retorno anticipado</button>
                                @elseif ($s->interrumpida)
                                    <span class="text-warning text-xs font-medium">Interrumpida</span>
                                @else
                                    <span class="text-faint text-xs">{{ $s->decididaPor?->name ? 'por '.$s->decididaPor->name : '—' }}</span>
                                @endif
                            @else
                                <span class="text-faint text-xs">{{ $s->decididaPor?->name ? 'por '.$s->decididaPor->name : '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin solicitudes de vacaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $solicitudes->links() }}</div>

    {{-- Modal nueva solicitud --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Nueva solicitud de vacaciones</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Empleado *</label>
                        <select wire:model.live="empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                            @endforeach
                        </select>
                        @error('empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        @if ($saldoEmpleado !== null)
                            <p class="text-xs mt-1 {{ $saldoEmpleado < 0 ? 'text-danger' : 'text-muted' }}">
                                Saldo actual: <strong>{{ number_format($saldoEmpleado, 1) }}</strong> días
                            </p>
                        @endif
                    </div>
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
                        <div class="rounded-lg bg-primary-tint text-primary-dark px-3 py-2 text-sm">
                            <strong>{{ $diasCalc }}</strong> día(s) calendario
                            @if ($saldoEmpleado !== null && $diasCalc > $saldoEmpleado)
                                <span class="text-danger">· excede el saldo disponible</span>
                            @endif
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo / observación</label>
                        <input type="text" wire:model="motivo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal retorno anticipado --}}
    @if ($mostrarRetorno)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Retorno anticipado</h3>
                    <button wire:click="$set('mostrarRetorno', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="registrarRetorno" class="px-6 py-5 space-y-4">
                    <p class="text-sm text-muted">La empresa lo hizo volver antes de terminar sus vacaciones. Indica el <strong>último día que estuvo de vacaciones</strong>; los días no gozados vuelven a su saldo.</p>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Último día real de vacaciones *</label>
                        <input type="date" wire:model="fecha_fin_real" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('fecha_fin_real') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarRetorno', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-warning hover:brightness-95 text-white text-sm font-semibold px-4 py-2">Registrar retorno</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal rechazo --}}
    @if ($mostrarRechazo)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Rechazar solicitud</h3>
                    <button wire:click="$set('mostrarRechazo', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="rechazar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo del rechazo (opcional)</label>
                        <textarea wire:model="comentario_decision" rows="3" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarRechazo', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-danger hover:brightness-95 text-white text-sm font-semibold px-4 py-2">Rechazar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
