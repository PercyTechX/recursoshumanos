<?php

use App\Mail\AvisoVencimientoDocumento;
use App\Models\AvisoDocumento;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
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
    public string $filtroEstado = '';

    #[Url]
    public string $filtroTipo = '';

    #[Url]
    public bool $soloActuales = true;

    #[Url]
    public string $orden = 'vencimiento'; // empleado | estado | vencimiento

    #[Url]
    public string $ordenDir = 'asc';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    // Formulario
    public ?int $empleado_id = null;
    public ?int $tipo_documento_id = null;
    public string $fecha_emision = '';
    public string $fecha_vencimiento = '';
    public string $observacion = '';
    public $archivo = null;          // archivo nuevo (subida temporal)
    public ?string $archivoActual = null;

    protected function rules(): array
    {
        return [
            'empleado_id' => ['required', 'exists:empleados,id'],
            'tipo_documento_id' => ['required', 'exists:tipos_documento,id'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function nuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $d = Documento::findOrFail($id);
        $this->editandoId = $d->id;
        $this->empleado_id = $d->empleado_id;
        $this->tipo_documento_id = $d->tipo_documento_id;
        $this->fecha_emision = optional($d->fecha_emision)->format('Y-m-d') ?? '';
        $this->fecha_vencimiento = optional($d->fecha_vencimiento)->format('Y-m-d') ?? '';
        $this->observacion = $d->observacion ?? '';
        $this->archivoActual = $d->archivo_nombre;
        $this->archivo = null;
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $data = $this->validate();

        $payload = [
            'empleado_id' => $this->empleado_id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'fecha_emision' => $this->fecha_emision ?: null,
            'fecha_vencimiento' => $this->fecha_vencimiento ?: null,
            'observacion' => $this->observacion ?: null,
        ];

        if ($this->archivo) {
            $payload['archivo_path'] = $this->archivo->store('documentos', 'public');
            $payload['archivo_nombre'] = $this->archivo->getClientOriginalName();
        }

        Documento::updateOrCreate(['id' => $this->editandoId], $payload);

        $this->mostrarForm = false;
        $this->resetForm();
        session()->flash('ok', 'Documento guardado correctamente.');
    }

    public function eliminar(int $id): void
    {
        Documento::findOrFail($id)->delete();
        session()->flash('ok', 'Documento eliminado.');
    }

    /** Envía un correo al supervisor del trabajador avisando del vencimiento. */
    public function avisar(int $id): void
    {
        $doc = Documento::with(['empleado.supervisor.user', 'tipoDocumento'])->findOrFail($id);

        if (! in_array($doc->estado, ['por_vencer', 'vencido'], true)) {
            session()->flash('error', 'Solo se avisa de documentos por vencer o vencidos.');

            return;
        }

        $supervisor = $doc->empleado?->supervisor;
        if (! $supervisor) {
            session()->flash('error', 'El trabajador no tiene un supervisor asignado.');

            return;
        }

        $email = $supervisor->correo ?: $supervisor->user?->email;
        if (! $email) {
            session()->flash('error', 'El supervisor no tiene un correo registrado.');

            return;
        }

        Mail::to($email)->send(new AvisoVencimientoDocumento($doc));

        AvisoDocumento::create([
            'documento_id' => $doc->id,
            'empleado_id' => $doc->empleado_id,
            'supervisor_id' => $supervisor->id,
            'email_destino' => $email,
            'estado_documento' => $doc->estado,
            'dias' => $doc->dias_para_vencer,
            'enviado_por' => auth()->id(),
        ]);

        session()->flash('ok', "Aviso enviado al supervisor ({$email}).");
    }

    public function resetForm(): void
    {
        $this->reset([
            'editandoId', 'empleado_id', 'tipo_documento_id', 'fecha_emision',
            'fecha_vencimiento', 'observacion', 'archivo', 'archivoActual',
        ]);
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

    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatingSoloActuales(): void
    {
        $this->resetPage();
    }

    public function ordenarPor(string $campo): void
    {
        if ($this->orden === $campo) {
            $this->ordenDir = $this->ordenDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->orden = $campo;
            $this->ordenDir = 'asc';
        }
        $this->resetPage();
    }

    /** Muestra todas las versiones (historial) de un requisito (empleado + tipo). */
    public function verHistorial(int $empleadoId, int $tipoId): void
    {
        $this->soloActuales = false;
        $this->filtroTipo = (string) $tipoId;
        $this->filtroEstado = '';
        $this->buscar = Empleado::find($empleadoId)?->numero_documento ?? '';
        $this->resetPage();
    }

    public function with(): array
    {
        // IDs de los documentos "actuales" (el vigente de cada empleado+tipo)
        $actualIds = Documento::actuales()->pluck('id')->flip();

        // Base (filtros que sí se pueden hacer en BD)
        $coleccion = Documento::query()
            ->with(['empleado', 'tipoDocumento'])
            ->when($this->buscar, fn ($q) => $q->whereHas('empleado', fn ($e) => $e
                ->where('nombres', 'like', '%'.$this->buscar.'%')
                ->orWhere('apellidos', 'like', '%'.$this->buscar.'%')
                ->orWhere('numero_documento', 'like', '%'.$this->buscar.'%')))
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo_documento_id', $this->filtroTipo))
            ->get();

        // Solo el documento actual de cada requisito (oculta el historial)
        if ($this->soloActuales) {
            $coleccion = $coleccion->filter(fn ($d) => $actualIds->has($d->id))->values();
        }

        // El estado (semáforo) se calcula en PHP → se filtra aquí
        if ($this->filtroEstado) {
            $coleccion = $coleccion->filter(fn ($d) => $d->estado === $this->filtroEstado)->values();
        }

        // Ordenamiento (se hace en PHP porque el estado no es columna de BD)
        $dir = $this->ordenDir === 'desc' ? -1 : 1;
        $pesoEstado = ['vencido' => 0, 'por_vencer' => 1, 'vigente' => 2, 'sin_vigencia' => 3];
        $coleccion = $coleccion->sort(function ($a, $b) use ($dir, $pesoEstado) {
            $cmp = match ($this->orden) {
                'empleado' => strcmp(
                    ($a->empleado?->apellidos ?? '').($a->empleado?->nombres ?? ''),
                    ($b->empleado?->apellidos ?? '').($b->empleado?->nombres ?? '')
                ),
                'estado' => ($pesoEstado[$a->estado] ?? 9) <=> ($pesoEstado[$b->estado] ?? 9),
                default => ($a->fecha_vencimiento?->timestamp ?? PHP_INT_MAX) <=> ($b->fecha_vencimiento?->timestamp ?? PHP_INT_MAX),
            };

            return $cmp * $dir;
        })->values();

        // Paginación manual
        $porPagina = 10;
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $items = $coleccion->slice(($pagina - 1) * $porPagina, $porPagina)->values();
        $documentos = new LengthAwarePaginator(
            $items, $coleccion->count(), $porPagina, $pagina,
            ['path' => request()->url(), 'pageName' => 'page'],
        );

        return [
            'documentos' => $documentos,
            'actualIds' => $actualIds,
            'resumen' => Documento::resumenSemaforo(), // solo cuenta los actuales
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'tipos' => TipoDocumento::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">
            {{ session('ok') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger-tint text-danger px-4 py-2 text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Resumen semáforo --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <button wire:click="$set('filtroEstado', 'vigente')"
                class="text-left bg-surface border border-line rounded-xl p-4 border-l-4 border-l-success hover:shadow-sm">
            <div class="text-sm text-muted">🟢 Vigentes</div>
            <div class="text-2xl font-bold text-ink tabular-nums">{{ $resumen['vigente'] }}</div>
        </button>
        <button wire:click="$set('filtroEstado', 'por_vencer')"
                class="text-left bg-surface border border-line rounded-xl p-4 border-l-4 border-l-warning hover:shadow-sm">
            <div class="text-sm text-muted">🟡 Por vencer</div>
            <div class="text-2xl font-bold text-ink tabular-nums">{{ $resumen['por_vencer'] }}</div>
        </button>
        <button wire:click="$set('filtroEstado', 'vencido')"
                class="text-left bg-surface border border-line rounded-xl p-4 border-l-4 border-l-danger hover:shadow-sm">
            <div class="text-sm text-muted">🔴 Vencidos</div>
            <div class="text-2xl font-bold text-ink tabular-nums">{{ $resumen['vencido'] }}</div>
        </button>
    </div>

    {{-- Barra de acciones --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2">
            <span class="text-faint">🔎</span>
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por empleado o documento…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>

        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los estados</option>
            <option value="vigente">🟢 Vigentes</option>
            <option value="por_vencer">🟡 Por vencer</option>
            <option value="vencido">🔴 Vencidos</option>
            <option value="sin_vigencia">Sin vigencia</option>
        </select>

        <select wire:model.live="filtroTipo" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
            @endforeach
        </select>

        <label class="flex items-center gap-2 text-sm text-muted px-1" title="Muestra solo el documento actual de cada requisito; desmárcalo para ver el historial completo">
            <input type="checkbox" wire:model.live="soloActuales" class="rounded border-line text-primary focus:ring-primary">
            Solo vigentes
        </label>

        <button wire:click="nuevo" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            + Nuevo
        </button>

        <a href="{{ route('documentos.exportar', ['buscar' => $buscar, 'estado' => $filtroEstado, 'tipo' => $filtroTipo]) }}"
           class="rounded-lg bg-excel hover:brightness-95 text-white text-sm font-semibold px-4 py-2">
            ⬇ Exportar a Excel
        </a>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[760px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">
                        <button wire:click="ordenarPor('empleado')" class="uppercase tracking-wide hover:text-primary">
                            Empleado @if ($orden === 'empleado') {{ $ordenDir === 'asc' ? '▲' : '▼' }} @endif
                        </button>
                    </th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Emisión</th>
                    <th class="px-4 py-3">
                        <button wire:click="ordenarPor('vencimiento')" class="uppercase tracking-wide hover:text-primary">
                            Vencimiento @if ($orden === 'vencimiento') {{ $ordenDir === 'asc' ? '▲' : '▼' }} @endif
                        </button>
                    </th>
                    <th class="px-4 py-3">
                        <button wire:click="ordenarPor('estado')" class="uppercase tracking-wide hover:text-primary">
                            Estado @if ($orden === 'estado') {{ $ordenDir === 'asc' ? '▲' : '▼' }} @endif
                        </button>
                    </th>
                    <th class="px-4 py-3">Archivo</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $d)
                    @php $esActual = $actualIds->has($d->id); @endphp
                    <tr class="border-b border-line last:border-0 hover:bg-canvas/60 {{ $esActual ? '' : 'bg-canvas/40' }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $d->empleado?->apellidos }}, {{ $d->empleado?->nombres }}</td>
                        <td class="px-4 py-3 text-muted">
                            {{ $d->tipoDocumento?->nombre }}
                            @unless ($esActual)
                                <span class="ml-1 inline-flex items-center rounded bg-canvas text-faint border border-line px-1.5 py-0.5 text-[10px] font-semibold uppercase">Histórico</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($d->fecha_emision)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($d->fecha_vencimiento)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                [$clase, $texto] = match ($d->estado) {
                                    'vigente' => ['bg-success-tint text-success', 'Vigente'],
                                    'por_vencer' => ['bg-warning-tint text-warning', 'Por vencer'],
                                    'vencido' => ['bg-danger-tint text-danger', 'Vencido'],
                                    default => ['bg-canvas text-faint', 'Sin vigencia'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $clase }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>{{ $texto }}
                                @if ($d->estado === 'por_vencer') · {{ $d->dias_para_vencer }}d @endif
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($d->archivo_path)
                                <a href="{{ Storage::url($d->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (in_array($d->estado, ['por_vencer', 'vencido'], true))
                                <button wire:click="avisar({{ $d->id }})"
                                        wire:confirm="¿Enviar un correo al supervisor avisando de este documento?"
                                        class="text-warning hover:underline text-sm font-medium" title="Avisar al supervisor por correo">✉ Avisar</button>
                            @endif
                            <button wire:click="verHistorial({{ $d->empleado_id }}, {{ $d->tipo_documento_id }})"
                                    class="ml-3 text-muted hover:text-primary hover:underline text-sm font-medium" title="Ver todas las versiones de este requisito">Historial</button>
                            <button wire:click="editar({{ $d->id }})" class="ml-3 text-primary hover:underline text-sm font-medium">Editar</button>
                            <button wire:click="eliminar({{ $d->id }})" wire:confirm="¿Eliminar este documento?"
                                    class="ml-3 text-danger hover:underline text-sm font-medium">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-faint">No se encontraron documentos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $documentos->links() }}
    </div>

    {{-- Modal de formulario --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-2xl mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar documento' : 'Nuevo documento' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>

                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo de documento *</label>
                            <select wire:model="tipo_documento_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($tipos as $t)
                                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                @endforeach
                            </select>
                            @error('tipo_documento_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha de emisión</label>
                            <input type="date" wire:model="fecha_emision" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha de vencimiento</label>
                            <input type="date" wire:model="fecha_vencimiento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('fecha_vencimiento') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Archivo (PDF o imagen, máx. 5 MB)</label>
                            <input type="file" wire:model="archivo" class="w-full text-sm text-muted">
                            <div wire:loading wire:target="archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                            @error('archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            @if ($archivoActual)
                                <p class="text-xs text-faint mt-1">Actual: {{ $archivoActual }} (subir uno nuevo lo reemplaza)</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Observación</label>
                            <textarea wire:model="observacion" rows="2" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('mostrarForm', false)"
                                class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit"
                                class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
