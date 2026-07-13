<?php

use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\Ticket;
use App\Models\TicketAvance;
use App\Models\TicketTecnico;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $vista = 'marcaciones';
    public string $filtroEmpleado = '';

    // Marcación manual
    public bool $mostrarMarc = false;
    public ?int $marcId = null;
    public ?int $m_empleado_id = null;
    public string $m_tipo = 'ingreso';
    public string $m_fecha_hora = '';
    public string $m_motivo = '';

    // Liberar técnico
    public bool $mostrarLiberar = false;
    public ?int $liberarTtId = null;
    public string $liberarMotivo = '';

    // Avance manual de ticket
    public bool $mostrarAvance = false;
    public ?int $av_empleado_id = null;
    public ?int $av_ticket_id = null;
    public string $av_estado = 'iniciado';
    public string $av_motivo = '';

    public function updatingFiltroEmpleado(): void
    {
        $this->resetPage();
    }

    // ---- Marcación manual ----
    public function nuevaMarc(): void
    {
        $this->reset(['marcId', 'm_empleado_id', 'm_motivo']);
        $this->m_tipo = 'ingreso';
        $this->m_fecha_hora = now()->format('Y-m-d\TH:i');
        $this->resetErrorBag();
        $this->mostrarMarc = true;
    }

    public function editarMarc(int $id): void
    {
        $m = Marcacion::findOrFail($id);
        $this->marcId = $m->id;
        $this->m_empleado_id = $m->empleado_id;
        $this->m_tipo = $m->tipo;
        $this->m_fecha_hora = $m->fecha_hora->format('Y-m-d\TH:i');
        $this->m_motivo = $m->motivo ?? '';
        $this->resetErrorBag();
        $this->mostrarMarc = true;
    }

    public function guardarMarc(): void
    {
        abort_unless(auth()->user()->can($this->marcId ? 'asistencia.editar' : 'asistencia.registrar'), 403);
        $d = $this->validate([
            'm_empleado_id' => ['required', 'exists:empleados,id'],
            'm_tipo' => ['required', 'in:ingreso,salida'],
            'm_fecha_hora' => ['required', 'date'],
            'm_motivo' => ['required', 'string', 'max:200'],
        ], [], ['m_empleado_id' => 'empleado', 'm_fecha_hora' => 'fecha y hora', 'm_motivo' => 'motivo']);

        $m = Marcacion::findOrNew($this->marcId);
        $m->fill([
            'empleado_id' => $d['m_empleado_id'],
            'tipo' => $d['m_tipo'],
            'fecha_hora' => $d['m_fecha_hora'],
            'motivo' => $d['m_motivo'],
            'es_manual' => true,
            'registrado_por' => auth()->id(),
        ]);
        $m->save();

        $this->mostrarMarc = false;
        session()->flash('ok', 'Marcación registrada.');
    }

    public function eliminarMarc(int $id): void
    {
        abort_unless(auth()->user()->can('asistencia.editar'), 403);
        Marcacion::findOrFail($id)->delete();
        session()->flash('ok', 'Marcación eliminada.');
    }

    // ---- Liberar técnico de un ticket ----
    public function abrirLiberar(int $ttId): void
    {
        $this->liberarTtId = $ttId;
        $this->liberarMotivo = '';
        $this->resetErrorBag();
        $this->mostrarLiberar = true;
    }

    public function liberar(): void
    {
        abort_unless(auth()->user()->can('asistencia.registrar'), 403);
        $this->validate(['liberarMotivo' => ['required', 'string', 'max:200']], [], ['liberarMotivo' => 'motivo']);

        $tt = TicketTecnico::whereIn('estado_trabajo', TicketTecnico::ACTIVOS)->findOrFail($this->liberarTtId);
        $tt->update(['estado_trabajo' => TicketTecnico::ABORTADO, 'liberado_por' => auth()->id(), 'motivo' => $this->liberarMotivo]);
        TicketAvance::create([
            'ticket_tecnico_id' => $tt->id, 'estado' => TicketTecnico::ABORTADO, 'fecha_hora' => now(),
            'es_manual' => true, 'registrado_por' => auth()->id(), 'motivo' => $this->liberarMotivo,
        ]);

        $this->mostrarLiberar = false;
        session()->flash('ok', 'Técnico liberado del ticket.');
    }

    // ---- Avance manual de ticket (técnico sin señal) ----
    public function nuevoAvance(): void
    {
        $this->reset(['av_empleado_id', 'av_ticket_id', 'av_motivo']);
        $this->av_estado = 'iniciado';
        $this->resetErrorBag();
        $this->mostrarAvance = true;
    }

    public function guardarAvance(): void
    {
        abort_unless(auth()->user()->can('asistencia.registrar'), 403);
        $d = $this->validate([
            'av_empleado_id' => ['required', 'exists:empleados,id'],
            'av_ticket_id' => ['required', 'exists:tickets,id'],
            'av_estado' => ['required', 'in:iniciado,en_ejecucion,terminado,abortado'],
            'av_motivo' => ['required', 'string', 'max:200'],
        ], [], ['av_empleado_id' => 'empleado', 'av_ticket_id' => 'ticket', 'av_motivo' => 'motivo']);

        $tt = TicketTecnico::updateOrCreate(
            ['ticket_id' => $d['av_ticket_id'], 'empleado_id' => $d['av_empleado_id']],
            ['estado_trabajo' => $d['av_estado']],
        );
        TicketAvance::create([
            'ticket_tecnico_id' => $tt->id, 'estado' => $d['av_estado'], 'fecha_hora' => now(),
            'es_manual' => true, 'registrado_por' => auth()->id(), 'motivo' => $d['av_motivo'],
        ]);

        $this->mostrarAvance = false;
        session()->flash('ok', 'Avance de ticket registrado manualmente.');
    }

    public function with(): array
    {
        return [
            'marcaciones' => Marcacion::query()->with(['empleado', 'registradoPor'])
                ->when($this->filtroEmpleado, fn ($q) => $q->whereHas('empleado', fn ($e) => $e
                    ->where('nombres', 'like', '%'.$this->filtroEmpleado.'%')
                    ->orWhere('apellidos', 'like', '%'.$this->filtroEmpleado.'%')
                    ->orWhere('numero_documento', 'like', '%'.$this->filtroEmpleado.'%')))
                ->orderByDesc('fecha_hora')->orderByDesc('id')->paginate(12),
            'engagements' => TicketTecnico::whereIn('estado_trabajo', TicketTecnico::ACTIVOS)
                ->with(['empleado', 'ticket.cliente'])->orderByDesc('id')->get(),
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'ticketsAbiertos' => Ticket::abiertos()->with('cliente')->orderByDesc('id')->get(),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    {{-- Sub-navegación --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="inline-flex rounded-lg border border-line overflow-hidden">
            <button wire:click="$set('vista', 'marcaciones')" class="px-4 py-2 text-sm font-medium {{ $vista === 'marcaciones' ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-canvas' }}">Marcaciones</button>
            <button wire:click="$set('vista', 'tickets')" class="px-4 py-2 text-sm font-medium {{ $vista === 'tickets' ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-canvas' }}">Operación de tickets</button>
        </div>
        <div class="flex-1"></div>
        @if ($vista === 'marcaciones')
            @can('asistencia.registrar')
                <button wire:click="nuevaMarc" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                    <x-icon name="plus" class="w-4 h-4" /> Marcación manual
                </button>
            @endcan
        @else
            @can('asistencia.registrar')
                <button wire:click="nuevoAvance" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                    <x-icon name="plus" class="w-4 h-4" /> Avance manual
                </button>
            @endcan
        @endif
    </div>

    @if ($vista === 'marcaciones')
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2 mb-4 max-w-sm">
            <x-icon name="search" class="w-4 h-4 text-faint shrink-0" />
            <input type="text" wire:model.live.debounce.400ms="filtroEmpleado" placeholder="Filtrar por empleado…" class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>

        <div class="overflow-x-auto rounded-xl border border-line bg-surface">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Empleado</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Fecha y hora</th>
                        <th class="px-4 py-3">Origen</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($marcaciones as $m)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $m->empleado?->apellidos }}, {{ $m->empleado?->nombres }}</td>
                            <td class="px-4 py-3">
                                @if ($m->tipo === 'ingreso')
                                    <span class="inline-flex items-center rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">Ingreso</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-warning-tint text-warning px-2.5 py-0.5 text-xs font-semibold">Salida</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $m->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($m->es_manual)
                                    <span class="text-warning font-medium">Manual</span>
                                    @if ($m->registradoPor)<span class="text-faint"> · {{ $m->registradoPor->name }}</span>@endif
                                    @if ($m->motivo)<div class="text-faint">{{ $m->motivo }}</div>@endif
                                @else <span class="text-muted">App (GPS)</span> @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="inline-flex items-center gap-1 justify-end w-full">
                                    @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                    @can('asistencia.editar')
                                        <button wire:click="editarMarc({{ $m->id }})" class="{{ $btn }} text-primary" title="Corregir hora / editar">
                                            <x-icon name="pencil" />
                                        </button>
                                        <button wire:click="eliminarMarc({{ $m->id }})" wire:confirm="¿Eliminar esta marcación?" class="{{ $btn }} text-danger" title="Eliminar">
                                            <x-icon name="trash" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin marcaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $marcaciones->links() }}</div>
    @else
        {{-- Operación de tickets: técnicos con ticket activo --}}
        <div class="overflow-x-auto rounded-xl border border-line bg-surface">
            <table class="w-full text-sm min-w-[680px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Técnico</th>
                        <th class="px-4 py-3">Ticket</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($engagements as $e)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $e->empleado?->apellidos }}, {{ $e->empleado?->nombres }}</td>
                            <td class="px-4 py-3 text-muted">{{ $e->ticket?->ticket_atencion }} <span class="text-faint text-xs">· {{ $e->ticket?->cliente?->nombre_comercial ?: $e->ticket?->cliente?->razon_social }}</span></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full {{ $e->estado_trabajo === 'en_ejecucion' ? 'bg-primary-tint text-primary' : 'bg-warning-tint text-warning' }} px-2.5 py-0.5 text-xs font-semibold">{{ $e->estado_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('asistencia.registrar')
                                    <button wire:click="abrirLiberar({{ $e->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-line text-danger hover:bg-danger-tint text-xs font-semibold px-3 py-1.5" title="Liberar al técnico">
                                        <x-icon name="ban" class="w-4 h-4" /> Liberar
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Ningún técnico con ticket activo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Modal: marcación manual --}}
    @if ($mostrarMarc)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $marcId ? 'Corregir marcación' : 'Marcación manual' }}</h3>
                    <button wire:click="$set('mostrarMarc', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardarMarc" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Empleado *</label>
                        <select wire:model="m_empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->apellidos }}, {{ $emp->nombres }}</option>
                            @endforeach
                        </select>
                        @error('m_empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo *</label>
                            <select wire:model="m_tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="ingreso">Ingreso</option>
                                <option value="salida">Salida</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha y hora *</label>
                            <input type="datetime-local" wire:model="m_fecha_hora" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('m_fecha_hora') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo * <span class="text-faint">(por qué se registra manual)</span></label>
                        <input type="text" wire:model="m_motivo" placeholder="Ej. sin señal / equipo averiado" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('m_motivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarMarc', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: liberar técnico --}}
    @if ($mostrarLiberar)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-sm mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Liberar técnico</h3>
                    <button wire:click="$set('mostrarLiberar', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="liberar" class="px-6 py-5 space-y-4">
                    <p class="text-sm text-muted">El técnico quedará libre para tomar otro ticket.</p>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo *</label>
                        <input type="text" wire:model="liberarMotivo" placeholder="Ej. desviado a otra emergencia" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('liberarMotivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarLiberar', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-danger hover:brightness-95 text-white text-sm font-semibold px-4 py-2">Liberar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: avance manual de ticket --}}
    @if ($mostrarAvance)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Avance manual de ticket</h3>
                    <button wire:click="$set('mostrarAvance', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardarAvance" class="px-6 py-5 space-y-4">
                    <p class="text-sm text-muted">Para cuando el técnico no pudo registrar por su cuenta (sin señal / robo).</p>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Técnico *</label>
                        <select wire:model="av_empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->apellidos }}, {{ $emp->nombres }}</option>
                            @endforeach
                        </select>
                        @error('av_empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Ticket *</label>
                        <select wire:model="av_ticket_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($ticketsAbiertos as $tk)
                                <option value="{{ $tk->id }}">{{ $tk->ticket_atencion }} · {{ $tk->cliente?->nombre_comercial ?: $tk->cliente?->razon_social }}</option>
                            @endforeach
                        </select>
                        @error('av_ticket_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Estado *</label>
                        <select wire:model="av_estado" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="iniciado">Iniciado</option>
                            <option value="en_ejecucion">En ejecución</option>
                            <option value="terminado">Terminado</option>
                            <option value="abortado">Abortado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo *</label>
                        <input type="text" wire:model="av_motivo" placeholder="Ej. el técnico reportó por radio" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('av_motivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarAvance', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
