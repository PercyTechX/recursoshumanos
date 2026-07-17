<?php

use App\Models\Ausencia;
use App\Models\Empleado;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $filtroTipo = '';

    #[Url]
    public string $filtroEstado = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    // Rechazo (con motivo)
    public bool $mostrarRechazo = false;
    public ?int $rechazoId = null;
    public string $rechazoMotivo = '';

    public ?int $empleado_id = null;
    public string $tipo = 'descanso_medico';
    public bool $con_goce = true;
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $documento_ref = '';
    public string $motivo = '';
    public $archivo = null;
    public ?string $archivoActual = null;

    public function updatedTipo(string $value): void
    {
        // Ajusta "con goce" al valor típico del tipo elegido (editable).
        $this->con_goce = Ausencia::TIPOS[$value][1] ?? true;
    }

    public function nuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $a = Ausencia::findOrFail($id);
        $this->editandoId = $a->id;
        $this->empleado_id = $a->empleado_id;
        $this->tipo = $a->tipo;
        $this->con_goce = $a->con_goce;
        $this->fecha_inicio = optional($a->fecha_inicio)->format('Y-m-d') ?? '';
        $this->fecha_fin = optional($a->fecha_fin)->format('Y-m-d') ?? '';
        $this->documento_ref = $a->documento_ref ?? '';
        $this->motivo = $a->motivo ?? '';
        $this->archivoActual = $a->archivo_nombre;
        $this->archivo = null;
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'ausencias.editar' : 'ausencias.crear'), 403);
        $datos = $this->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(Ausencia::TIPOS))],
            'con_goce' => ['boolean'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'documento_ref' => ['nullable', 'string', 'max:120'],
            'motivo' => ['nullable', 'string', 'max:200'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [], ['empleado_id' => 'empleado']);

        $a = Ausencia::findOrNew($this->editandoId);
        $a->fill([
            'empleado_id' => $datos['empleado_id'],
            'tipo' => $datos['tipo'],
            'con_goce' => $this->con_goce,
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'dias' => Ausencia::calcularDias($datos['fecha_inicio'], $datos['fecha_fin']),
            'documento_ref' => $datos['documento_ref'] ?: null,
            'motivo' => $datos['motivo'] ?: null,
            'created_by' => $a->created_by ?? auth()->id(),
        ]);

        if ($this->archivo) {
            if ($a->archivo_path) {
                Storage::disk('public')->delete($a->archivo_path);
            }
            $a->archivo_path = $this->archivo->store('ausencias', 'public');
            $a->archivo_nombre = $this->archivo->getClientOriginalName();
        }

        $a->save();
        $this->mostrarForm = false;
        $this->resetForm();
        session()->flash('ok', 'Ausencia registrada.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('ausencias.eliminar'), 403);
        $a = Ausencia::findOrFail($id);
        if ($a->archivo_path) {
            Storage::disk('public')->delete($a->archivo_path);
        }
        $a->delete();
        session()->flash('ok', 'Ausencia eliminada.');
    }

    public function resetForm(): void
    {
        $this->reset(['editandoId', 'empleado_id', 'fecha_inicio', 'fecha_fin', 'documento_ref', 'motivo', 'archivo', 'archivoActual']);
        $this->tipo = 'descanso_medico';
        $this->con_goce = true;
        $this->resetErrorBag();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    // ---- Aprobación (Supervisor visa → RRHH aprueba) ----

    /** ¿El usuario puede visar esta solicitud? (supervisor del empleado, o RRHH). */
    public function puedeVisar(Ausencia $a): bool
    {
        $u = auth()->user();
        if (! $u->can('ausencias.visar')) {
            return false;
        }
        if ($u->hasAnyRole(['SuperAdmin', 'RRHH', 'Gerencia'])) {
            return true; // RRHH/Gerencia pueden visar en cualquier caso
        }
        // Un supervisor solo visa a SUS subordinados
        $miEmpleado = $u->empleado?->id;

        return $miEmpleado && (int) $a->empleado?->supervisor_id === (int) $miEmpleado;
    }

    public function visar(int $id): void
    {
        $a = Ausencia::with('empleado')->findOrFail($id);
        abort_unless($this->puedeVisar($a) && $a->puede('visar'), 403);
        $a->transicionar('visar');
        $a->visado_por = auth()->id();
        $a->fecha_visto = now();
        $a->save();
        session()->flash('ok', 'Solicitud visada. Pasa a RRHH.');
    }

    public function aprobar(int $id): void
    {
        abort_unless(auth()->user()->can('ausencias.aprobar'), 403);
        $a = Ausencia::findOrFail($id);
        abort_unless($a->puede('aprobar'), 403);
        $a->transicionar('aprobar');
        $a->decidida_por = auth()->id();
        $a->fecha_decision = now();
        $a->save();
        session()->flash('ok', 'Solicitud aprobada.');
    }

    public function abrirRechazo(int $id): void
    {
        $this->rechazoId = $id;
        $this->rechazoMotivo = '';
        $this->resetErrorBag();
        $this->mostrarRechazo = true;
    }

    public function rechazar(): void
    {
        $a = Ausencia::with('empleado')->findOrFail($this->rechazoId);
        $ok = ($a->estado === Ausencia::PENDIENTE_SUPERVISOR && $this->puedeVisar($a))
            || ($a->estado === Ausencia::PENDIENTE_RRHH && auth()->user()->can('ausencias.aprobar'));
        abort_unless($ok && $a->puede('rechazar'), 403);

        $this->validate(['rechazoMotivo' => ['required', 'string', 'max:300']], [], ['rechazoMotivo' => 'motivo']);
        $a->transicionar('rechazar');
        $a->comentario_decision = $this->rechazoMotivo;
        $a->decidida_por = auth()->id();
        $a->fecha_decision = now();
        $a->save();

        $this->mostrarRechazo = false;
        session()->flash('ok', 'Solicitud rechazada.');
    }

    public function with(): array
    {
        return [
            'ausencias' => Ausencia::query()
                ->with(['empleado', 'solicitadoPor'])
                ->when($this->buscar, fn ($q) => $q->whereHas('empleado', fn ($w) => $w
                    ->where('nombres', 'like', '%'.$this->buscar.'%')
                    ->orWhere('apellidos', 'like', '%'.$this->buscar.'%')
                    ->orWhere('numero_documento', 'like', '%'.$this->buscar.'%')))
                ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->when($this->filtroEstado === 'pendientes', fn ($q) => $q->pendientes())
                ->when($this->filtroEstado && $this->filtroEstado !== 'pendientes', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate(10),
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'tipos' => Ausencia::TIPOS,
            'diasCalc' => Ausencia::calcularDias($this->fecha_inicio, $this->fecha_fin),
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
        <select wire:model.live="filtroTipo" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $k => $t)
                <option value="{{ $k }}">{{ $t[0] }}</option>
            @endforeach
        </select>
        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los estados</option>
            <option value="pendientes">Pendientes</option>
            <option value="pendiente_supervisor">Pendiente (supervisor)</option>
            <option value="pendiente_rrhh">Pendiente (RRHH)</option>
            <option value="aprobada">Aprobadas</option>
            <option value="rechazada">Rechazadas</option>
            <option value="cancelada">Canceladas</option>
        </select>
        @can('ausencias.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                <x-icon name="plus" class="w-4 h-4" /> Nueva ausencia
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[760px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3 text-center">Días</th>
                    <th class="px-4 py-3">Goce</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Sustento</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ausencias as $a)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $a->empleado?->apellidos }}, {{ $a->empleado?->nombres }}</td>
                        <td class="px-4 py-3 text-muted">
                            {{ $a->tipo_label }}
                            @if ($a->documento_ref)<div class="text-faint text-xs">{{ $a->documento_ref }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $a->fecha_inicio->format('d/m/Y') }} → {{ $a->fecha_fin->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-center tabular-nums font-semibold text-ink">{{ $a->dias }}</td>
                        <td class="px-4 py-3">
                            @if ($a->con_goce)
                                <span class="inline-flex items-center rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">Con goce</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-canvas text-muted border border-line px-2.5 py-0.5 text-xs font-semibold">Sin goce</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badgeL = [
                                    'pendiente_supervisor' => 'bg-warning-tint text-warning', 'pendiente_rrhh' => 'bg-warning-tint text-warning',
                                    'aprobada' => 'bg-success-tint text-success', 'rechazada' => 'bg-danger-tint text-danger',
                                    'cancelada' => 'bg-canvas text-faint',
                                ];
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeL[$a->estado] ?? 'bg-canvas text-faint' }}">{{ $a->estado_label }}</span>
                            @if ($a->estado === 'rechazada' && $a->comentario_decision)<div class="text-danger text-[11px] mt-1">{{ $a->comentario_decision }}</div>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($a->archivo_item_id || $a->archivo_path)
                                <a href="{{ route('ausencias.sustento', $a) }}" target="_blank" class="inline-flex items-center gap-1 text-primary hover:underline" title="Ver sustento">
                                    <x-icon name="eye" class="w-4 h-4" /> Ver
                                </a>
                            @else <span class="text-faint">—</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 justify-end flex-wrap">
                                @php
                                    $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors';
                                    $chip = 'text-xs font-semibold px-2.5 py-1 rounded-lg border border-line hover:bg-canvas';
                                @endphp
                                @if ($a->estado === 'pendiente_supervisor' && $this->puedeVisar($a))
                                    <button wire:click="visar({{ $a->id }})" class="{{ $chip }} text-success">Visar</button>
                                    <button wire:click="abrirRechazo({{ $a->id }})" class="{{ $chip }} text-danger">Rechazar</button>
                                @elseif ($a->estado === 'pendiente_rrhh')
                                    @can('ausencias.aprobar')
                                        <button wire:click="aprobar({{ $a->id }})" class="{{ $chip }} text-success">Aprobar</button>
                                        <button wire:click="abrirRechazo({{ $a->id }})" class="{{ $chip }} text-danger">Rechazar</button>
                                    @endcan
                                @endif
                                @can('ausencias.editar')
                                    <button wire:click="editar({{ $a->id }})" class="{{ $btn }} text-primary" title="Editar">
                                        <x-icon name="pencil" />
                                    </button>
                                @endcan
                                @can('ausencias.eliminar')
                                    <button wire:click="eliminar({{ $a->id }})" wire:confirm="¿Eliminar esta ausencia?" class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-faint">Sin ausencias registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $ausencias->links() }}</div>

    {{-- Modal --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar ausencia' : 'Nueva ausencia' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Empleado *</label>
                        <select wire:model="empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                            @endforeach
                        </select>
                        @error('empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo *</label>
                            <select wire:model.live="tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @foreach ($tipos as $k => $t)
                                    <option value="{{ $k }}">{{ $t[0] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-muted">
                                <input type="checkbox" wire:model="con_goce" class="rounded border-line text-primary focus:ring-primary">
                                Con goce de haber
                            </label>
                        </div>
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
                        <div class="rounded-lg bg-primary-tint text-primary-dark px-3 py-2 text-sm"><strong>{{ $diasCalc }}</strong> día(s) calendario</div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">N° CITT / resolución / referencia</label>
                        <input type="text" wire:model="documento_ref" placeholder="Ej. CITT N° 123456" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo / observación</label>
                        <input type="text" wire:model="motivo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Documento (PDF/imagen)</label>
                        <input type="file" wire:model="archivo" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-tint file:px-3 file:py-1.5 file:text-primary file:font-semibold">
                        <div wire:loading wire:target="archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                        @if ($archivoActual)<p class="text-xs text-faint mt-1">Actual: {{ $archivoActual }} (subir uno nuevo lo reemplaza)</p>@endif
                        @error('archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: rechazar solicitud --}}
    @if ($mostrarRechazo)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-sm mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Rechazar solicitud</h3>
                    <button wire:click="$set('mostrarRechazo', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="rechazar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Motivo del rechazo *</label>
                        <input type="text" wire:model="rechazoMotivo" placeholder="Ej. falta el CITT" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('rechazoMotivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
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
