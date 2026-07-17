<?php

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\CategoriaActivo;
use App\Models\Empleado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $filtroCategoria = '';

    #[Url]
    public string $filtroEstado = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    // Formulario
    public ?int $categoria_id = null;
    public string $nombre = '';
    public string $codigo = '';
    public string $costo = '';
    public string $estado = 'disponible';
    public string $descripcion = '';

    // Asignar
    public ?int $asignarId = null;
    public ?int $asignEmpleadoId = null;
    public string $firmaEntrega = '';
    public string $asignObservacion = '';

    // Devolver
    public ?int $devolverId = null;
    public string $devEstado = 'bueno';
    public string $devObservacion = '';
    public string $firmaDevolucion = '';

    // Historial (trazabilidad del activo)
    public ?int $historialId = null;

    protected function rules(): array
    {
        return [
            'categoria_id' => ['required', 'exists:categorias_activo,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:60', Rule::unique('activos', 'codigo')->ignore($this->editandoId)],
            'costo' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(array_keys(Activo::ESTADOS))],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function nuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $a = Activo::findOrFail($id);
        $this->editandoId = $a->id;
        // Coalesce a '' porque las columnas nullables (código/descripción) no pueden
        // asignarse a propiedades string tipadas.
        $this->categoria_id = $a->categoria_id;
        $this->nombre = $a->nombre ?? '';
        $this->codigo = $a->codigo ?? '';
        $this->estado = $a->estado ?? 'disponible';
        $this->descripcion = $a->descripcion ?? '';
        $this->costo = (string) $a->costo;
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'activos.editar' : 'activos.crear'), 403);
        $data = $this->validate();
        Activo::updateOrCreate(['id' => $this->editandoId], $data);
        $this->mostrarForm = false;
        $this->resetForm();
        session()->flash('ok', 'Activo guardado correctamente.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('activos.eliminar'), 403);
        Activo::findOrFail($id)->delete();
        session()->flash('ok', 'Activo eliminado.');
    }

    // ---- Asignar / Devolver ----

    private function guardarFirma(string $dataUrl, string $carpeta): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }
        $base64 = explode(',', $dataUrl, 2)[1] ?? '';
        $path = $carpeta.'/'.uniqid('firma_').'.png';
        Storage::disk('public')->put($path, base64_decode($base64));

        return $path;
    }

    public function abrirAsignar(int $activoId): void
    {
        $this->reset(['asignEmpleadoId', 'firmaEntrega', 'asignObservacion']);
        $this->resetErrorBag();
        $this->asignarId = $activoId;
    }

    public function asignar(): void
    {
        abort_unless(auth()->user()->can('activos.asignar'), 403);
        $this->validate([
            'asignEmpleadoId' => ['required', 'exists:empleados,id'],
            'firmaEntrega' => ['required', 'string'],
        ], [], ['asignEmpleadoId' => 'empleado', 'firmaEntrega' => 'firma']);

        Asignacion::create([
            'activo_id' => $this->asignarId,
            'empleado_id' => $this->asignEmpleadoId,
            'fecha_entrega' => now()->toDateString(),
            'firma_entrega_path' => $this->guardarFirma($this->firmaEntrega, 'firmas'),
            'entregado_por' => auth()->id(),
            'observacion' => $this->asignObservacion ?: null,
        ]);

        Activo::whereKey($this->asignarId)->update(['estado' => Activo::ASIGNADO]);

        $this->asignarId = null;
        session()->flash('ok', 'Activo asignado correctamente.');
    }

    public function abrirDevolver(int $activoId): void
    {
        $this->reset(['devEstado', 'devObservacion', 'firmaDevolucion']);
        $this->devEstado = 'bueno';
        $this->resetErrorBag();
        $this->devolverId = $activoId;
    }

    public function devolver(): void
    {
        abort_unless(auth()->user()->can('activos.asignar'), 403);
        $this->validate([
            'devEstado' => ['required', 'in:bueno,dañado,perdido'],
        ]);

        $asignacion = Asignacion::where('activo_id', $this->devolverId)
            ->whereNull('fecha_devolucion')
            ->latest('id')
            ->first();

        if ($asignacion) {
            $asignacion->update([
                'fecha_devolucion' => now()->toDateString(),
                'estado_devolucion' => $this->devEstado,
                'firma_devolucion_path' => $this->firmaDevolucion ? $this->guardarFirma($this->firmaDevolucion, 'firmas') : null,
                'recibido_por' => auth()->id(),
                'observacion' => trim(($asignacion->observacion ? $asignacion->observacion.' | ' : '').$this->devObservacion) ?: $asignacion->observacion,
            ]);
        }

        // Nuevo estado del activo según cómo volvió
        $nuevoEstado = match ($this->devEstado) {
            'dañado' => Activo::MANTENIMIENTO,
            'perdido' => Activo::PERDIDO,
            default => Activo::DISPONIBLE,
        };
        Activo::whereKey($this->devolverId)->update(['estado' => $nuevoEstado]);

        $this->devolverId = null;
        session()->flash('ok', 'Devolución registrada.');
    }

    public function verHistorial(int $activoId): void
    {
        $this->historialId = $activoId;
    }

    public function resetForm(): void
    {
        $this->reset(['editandoId', 'categoria_id', 'nombre', 'codigo', 'costo', 'descripcion']);
        $this->estado = 'disponible';
        $this->resetErrorBag();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $activos = Activo::query()
            ->with(['categoria', 'asignacionActiva.empleado'])
            ->when($this->buscar, fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', '%'.$this->buscar.'%')
                ->orWhere('codigo', 'like', '%'.$this->buscar.'%')))
            ->when($this->filtroCategoria, fn ($q) => $q->where('categoria_id', $this->filtroCategoria))
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderBy('nombre')
            ->paginate(10);

        // Historial de trazabilidad del activo seleccionado
        $historial = $this->historialId
            ? Asignacion::with('empleado')
                ->where('activo_id', $this->historialId)
                ->orderByDesc('fecha_entrega')->orderByDesc('id')->get()
            : collect();

        return [
            'activos' => $activos,
            'categorias' => CategoriaActivo::where('activo', true)->orderBy('nombre')->get(),
            'estados' => Activo::ESTADOS,
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'historial' => $historial,
            'historialActivo' => $this->historialId ? Activo::find($this->historialId) : null,
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    {{-- Barra de acciones --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2">
            <x-icon name="search" class="w-4 h-4 text-faint shrink-0" />
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por nombre o código…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>

        <select wire:model.live="filtroCategoria" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todas las categorías</option>
            @foreach ($categorias as $c)
                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los estados</option>
            @foreach ($estados as $val => $lbl)
                <option value="{{ $val }}">{{ $lbl }}</option>
            @endforeach
        </select>

        @can('activos.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2"><x-icon name="plus" class="w-4 h-4" /> Nuevo activo</button>
        @endcan
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[680px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Activo</th>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3 text-right">Costo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Asignado a</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activos as $a)
                    <tr class="border-b border-line last:border-0 hover:bg-canvas/60">
                        <td class="px-4 py-3 font-medium">
                            <button wire:click="verHistorial({{ $a->id }})" class="text-primary hover:underline text-left" title="Ver trazabilidad (quién lo tuvo)">
                                {{ $a->nombre }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $a->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $a->categoria?->nombre }}</td>
                        <td class="px-4 py-3 text-right text-muted tabular-nums">S/ {{ number_format((float) $a->costo, 2) }}</td>
                        <td class="px-4 py-3">
                            @php
                                [$clase] = match ($a->estado) {
                                    'disponible' => ['bg-success-tint text-success'],
                                    'asignado' => ['bg-primary-tint text-primary'],
                                    'mantenimiento' => ['bg-warning-tint text-warning'],
                                    'perdido' => ['bg-danger-tint text-danger'],
                                    default => ['bg-canvas text-faint'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $clase }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>{{ $a->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            @if ($a->estado === 'asignado' && $a->asignacionActiva?->empleado)
                                {{ $a->asignacionActiva->empleado->apellidos }}, {{ $a->asignacionActiva->empleado->nombres }}
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                @can('activos.asignar')
                                    @if ($a->estado === 'disponible')
                                        <button wire:click="abrirAsignar({{ $a->id }})" class="{{ $btn }} text-primary" title="Asignar a un empleado">
                                            <x-icon name="user-plus" />
                                        </button>
                                    @elseif ($a->estado === 'asignado')
                                        <button wire:click="abrirDevolver({{ $a->id }})" class="{{ $btn }} text-warning" title="Registrar devolución">
                                            <x-icon name="return" />
                                        </button>
                                    @endif
                                @endcan
                                <button wire:click="verHistorial({{ $a->id }})" class="{{ $btn }} text-muted hover:text-primary" title="Trazabilidad">
                                    <x-icon name="history" />
                                </button>
                                @can('activos.editar')
                                    <button wire:click="editar({{ $a->id }})" class="{{ $btn }} text-primary" title="Editar">
                                        <x-icon name="pencil" />
                                    </button>
                                @endcan
                                @can('activos.eliminar')
                                    <button wire:click="eliminar({{ $a->id }})" wire:confirm="¿Eliminar {{ $a->nombre }}?"
                                            class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-faint">No hay activos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $activos->links() }}</div>

    {{-- Modal --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-xl mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar activo' : 'Nuevo activo' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('nombre') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Categoría *</label>
                            <select wire:model="categoria_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                            @error('categoria_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Código / Serie</label>
                            <input type="text" wire:model="codigo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('codigo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Costo (S/) *</label>
                            <input type="number" step="0.01" min="0" wire:model="costo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('costo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Estado *</label>
                            <select wire:model="estado" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @foreach ($estados as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Descripción</label>
                            <textarea wire:model="descripcion" rows="2" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Asignar --}}
    @if ($asignarId)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Asignar activo</h3>
                    <button wire:click="$set('asignarId', null)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="asignar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Empleado *</label>
                        <select wire:model="asignEmpleadoId" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                            @endforeach
                        </select>
                        @error('asignEmpleadoId') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Observación</label>
                        <input type="text" wire:model="asignObservacion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Firma de recepción *</label>
                        <x-firma model="firmaEntrega" />
                        @error('firmaEntrega') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('asignarId', null)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Devolver --}}
    @if ($devolverId)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Registrar devolución</h3>
                    <button wire:click="$set('devolverId', null)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="devolver" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">¿En qué estado vuelve? *</label>
                        <select wire:model="devEstado" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="bueno">Bueno → vuelve a disponible</option>
                            <option value="dañado">Dañado → pasa a mantenimiento</option>
                            <option value="perdido">Perdido / no devuelto → pasa a perdido</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Observación</label>
                        <input type="text" wire:model="devObservacion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Firma (opcional)</label>
                        <x-firma model="firmaDevolucion" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('devolverId', null)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Registrar devolución</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Trazabilidad del activo --}}
    @if ($historialId && $historialActivo)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-xl mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-navy">Trazabilidad</h3>
                        <p class="text-xs text-muted">{{ $historialActivo->nombre }} @if ($historialActivo->codigo) · {{ $historialActivo->codigo }} @endif</p>
                    </div>
                    <button wire:click="$set('historialId', null)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <div class="px-6 py-5">
                    @forelse ($historial as $h)
                        <div class="flex gap-3 pb-4 last:pb-0">
                            <div class="flex flex-col items-center">
                                <span class="w-2.5 h-2.5 rounded-full {{ $h->esta_activa ? 'bg-primary' : 'bg-line' }} mt-1.5"></span>
                                @if (! $loop->last)<span class="flex-1 w-px bg-line"></span>@endif
                            </div>
                            <div class="flex-1 pb-1">
                                <div class="font-medium text-ink">{{ $h->empleado?->apellidos }}, {{ $h->empleado?->nombres }}</div>
                                <div class="text-sm text-muted">
                                    Entregado: {{ optional($h->fecha_entrega)->format('d/m/Y') }}
                                </div>
                                @if ($h->esta_activa)
                                    <span class="inline-flex items-center gap-1.5 mt-1 rounded-full bg-primary-tint text-primary px-2.5 py-0.5 text-xs font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-current"></span>En su poder
                                    </span>
                                @else
                                    <div class="text-sm text-muted">
                                        Devuelto: {{ optional($h->fecha_devolucion)->format('d/m/Y') }}
                                        @if ($h->estado_devolucion) · <span class="capitalize">{{ $h->estado_devolucion }}</span> @endif
                                    </div>
                                @endif
                                @if ($h->observacion)
                                    <div class="text-xs text-faint mt-0.5">{{ $h->observacion }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-faint py-6">Este activo aún no ha sido asignado a nadie.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
