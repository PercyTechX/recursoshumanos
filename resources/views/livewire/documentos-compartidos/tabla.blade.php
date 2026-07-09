<?php

use App\Models\DocumentoCompartido;
use App\Models\DocumentoCompartidoCobertura;
use App\Models\Empleado;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    // Coberturas
    public array $tiposSel = [];
    public array $aseguradora = [];
    public array $numero = [];

    // Datos del documento
    public string $fecha_emision = '';
    public string $fecha_vencimiento = '';
    public string $observacion = '';
    public $archivo = null;
    public ?string $archivoActual = null;

    // Grupo de personas
    public array $empleadosSel = [];
    public string $buscarEmpleado = '';

    protected function rules(): array
    {
        return [
            'tiposSel' => ['required', 'array', 'min:1'],
            'tiposSel.*' => ['exists:tipos_documento,id'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'observacion' => ['nullable', 'string', 'max:200'],
            'archivo' => [$this->editandoId ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'empleadosSel' => ['required', 'array', 'min:1'],
            'empleadosSel.*' => ['exists:empleados,id'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tiposSel.required' => 'Marca al menos una cobertura (ej. SCTR Salud).',
            'empleadosSel.required' => 'Selecciona al menos una persona amparada.',
            'archivo.required' => 'Sube el archivo del documento.',
        ];
    }

    public function nuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $this->cargar($id, renovar: false);
    }

    /** Renovar = nuevo documento con las MISMAS coberturas y el MISMO grupo, pero vigencia y archivo nuevos. */
    public function renovar(int $id): void
    {
        $this->cargar($id, renovar: true);
    }

    private function cargar(int $id, bool $renovar): void
    {
        $this->resetForm();
        $doc = DocumentoCompartido::with('coberturas')->findOrFail($id);

        $this->tiposSel = $doc->coberturas->pluck('tipo_documento_id')->map(fn ($v) => (string) $v)->all();
        foreach ($doc->coberturas as $c) {
            $this->aseguradora[$c->tipo_documento_id] = $c->aseguradora ?? '';
            $this->numero[$c->tipo_documento_id] = $c->numero_poliza ?? '';
        }
        $this->observacion = $doc->observacion ?? '';
        $this->empleadosSel = $doc->empleados()->pluck('empleados.id')->map(fn ($v) => (string) $v)->all();

        if ($renovar) {
            // Copia coberturas y grupo; deja vigencia y archivo en blanco.
            $this->editandoId = null;
        } else {
            $this->editandoId = $doc->id;
            $this->fecha_emision = optional($doc->fecha_emision)->format('Y-m-d') ?? '';
            $this->fecha_vencimiento = optional($doc->fecha_vencimiento)->format('Y-m-d') ?? '';
            $this->archivoActual = $doc->archivo_path;
        }

        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        $doc = DocumentoCompartido::findOrNew($this->editandoId);
        $doc->fecha_emision = $this->fecha_emision ?: null;
        $doc->fecha_vencimiento = $this->fecha_vencimiento ?: null;
        $doc->observacion = $this->observacion ?: null;

        if ($this->archivo) {
            if ($doc->archivo_path) {
                Storage::disk('public')->delete($doc->archivo_path);
            }
            $doc->archivo_path = $this->archivo->store('documentos-compartidos', 'public');
            $doc->archivo_nombre = $this->archivo->getClientOriginalName();
        }

        $doc->save();

        // Coberturas: reconstruye según lo seleccionado
        $doc->coberturas()->delete();
        foreach ($this->tiposSel as $tipoId) {
            DocumentoCompartidoCobertura::create([
                'documento_compartido_id' => $doc->id,
                'tipo_documento_id' => $tipoId,
                'aseguradora' => ($this->aseguradora[$tipoId] ?? '') ?: null,
                'numero_poliza' => ($this->numero[$tipoId] ?? '') ?: null,
            ]);
        }

        // Grupo de personas
        $doc->empleados()->sync($this->empleadosSel);

        $this->mostrarForm = false;
        $this->resetForm();
        session()->flash('ok', 'Documento compartido guardado.');
    }

    public function eliminar(int $id): void
    {
        $doc = DocumentoCompartido::findOrFail($id);
        if ($doc->archivo_path) {
            Storage::disk('public')->delete($doc->archivo_path);
        }
        $doc->delete();
        session()->flash('ok', 'Documento compartido eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editandoId', 'tiposSel', 'aseguradora', 'numero',
            'fecha_emision', 'fecha_vencimiento', 'observacion', 'archivo', 'archivoActual',
            'empleadosSel', 'buscarEmpleado',
        ]);
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'documentos' => DocumentoCompartido::with('coberturas.tipoDocumento')
                ->withCount('empleados')
                ->orderByDesc('fecha_vencimiento')->orderByDesc('id')->get(),
            'tiposCompartibles' => TipoDocumento::compartibles()->orderBy('nombre')->get(),
            'empleadosLista' => Empleado::query()
                ->where('situacion', 'activo')
                ->when($this->buscarEmpleado, fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombres', 'like', '%'.$this->buscarEmpleado.'%')
                    ->orWhere('apellidos', 'like', '%'.$this->buscarEmpleado.'%')
                    ->orWhere('numero_documento', 'like', '%'.$this->buscarEmpleado.'%')))
                ->orderBy('apellidos')->limit(200)->get(),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <p class="text-sm text-muted">
            Un solo archivo (SCTR, póliza, homologación) que ampara a <strong>varias personas</strong> a la vez.
        </p>
        <button wire:click="nuevo" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            + Nuevo documento compartido
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Coberturas</th>
                    <th class="px-4 py-3">Vigencia</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-center">Personas</th>
                    <th class="px-4 py-3">Archivo</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $d)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $d->coberturas_texto ?: '—' }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">
                            {{ optional($d->fecha_emision)->format('d/m/Y') ?? '—' }} → {{ optional($d->fecha_vencimiento)->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                [$c, $t] = match ($d->estado) {
                                    'vigente' => ['bg-success-tint text-success', 'Vigente'],
                                    'por_vencer' => ['bg-warning-tint text-warning', 'Por vencer'],
                                    'vencido' => ['bg-danger-tint text-danger', 'Vencido'],
                                    default => ['bg-canvas text-faint', 'Sin vigencia'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>{{ $t }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums font-semibold text-ink">{{ $d->empleados_count }}</td>
                        <td class="px-4 py-3">
                            @if ($d->archivo_path)
                                <a href="{{ Storage::url($d->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                            @else <span class="text-faint">—</span> @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="renovar({{ $d->id }})" class="text-success hover:underline text-sm font-medium" title="Crear la póliza del siguiente periodo con el mismo grupo">Renovar</button>
                            <button wire:click="editar({{ $d->id }})" class="ml-3 text-primary hover:underline text-sm font-medium">Editar</button>
                            <button wire:click="eliminar({{ $d->id }})" wire:confirm="¿Eliminar este documento compartido?" class="ml-3 text-danger hover:underline text-sm font-medium">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-faint">Sin documentos compartidos. Crea el primero (ej. SCTR colectivo).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-3xl mt-8 mb-8 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar documento compartido' : 'Nuevo documento compartido' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>

                <form wire:submit="guardar" class="px-6 py-5 space-y-5">
                    {{-- Coberturas --}}
                    <div>
                        <label class="block text-sm font-medium text-muted mb-2">Coberturas que ampara *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($tiposCompartibles as $tipo)
                                <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm cursor-pointer hover:bg-canvas">
                                    <input type="checkbox" wire:model.live="tiposSel" value="{{ $tipo->id }}" class="rounded border-line text-primary focus:ring-primary">
                                    <span class="text-ink">{{ $tipo->nombre }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('tiposSel') <span class="text-danger text-xs">{{ $message }}</span> @enderror

                        {{-- Datos de póliza por cobertura seleccionada --}}
                        @foreach ($tiposCompartibles as $tipo)
                            @if (in_array((string) $tipo->id, $tiposSel) || in_array($tipo->id, $tiposSel))
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-lg bg-canvas/60 border border-line p-3">
                                    <div class="sm:col-span-2 text-xs font-semibold text-primary">{{ $tipo->nombre }}</div>
                                    <div>
                                        <label class="block text-xs text-muted mb-1">Aseguradora</label>
                                        <input type="text" wire:model="aseguradora.{{ $tipo->id }}" placeholder="Ej. Sanitas / Crecer" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-muted mb-1">N° de contrato / póliza</label>
                                        <input type="text" wire:model="numero.{{ $tipo->id }}" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Vigencia + archivo --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Emisión</label>
                            <input type="date" wire:model="fecha_emision" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Vencimiento</label>
                            <input type="date" wire:model="fecha_vencimiento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('fecha_vencimiento') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Archivo (PDF/imagen) {{ $editandoId ? '' : '*' }}</label>
                            <input type="file" wire:model="archivo" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-tint file:px-3 file:py-1.5 file:text-primary file:font-semibold">
                            <div wire:loading wire:target="archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                            @if ($archivoActual)
                                <a href="{{ Storage::url($archivoActual) }}" target="_blank" class="text-xs text-primary hover:underline">Archivo actual</a>
                            @endif
                            @error('archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Observación</label>
                        <input type="text" wire:model="observacion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>

                    {{-- Selección de personas --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-muted">Personas amparadas *</label>
                            <span class="text-xs font-semibold text-primary">{{ count($empleadosSel) }} seleccionada(s)</span>
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="buscarEmpleado" placeholder="Buscar por nombre o DNI…"
                               class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary mb-2">
                        <div class="max-h-56 overflow-y-auto rounded-lg border border-line divide-y divide-line">
                            @forelse ($empleadosLista as $emp)
                                <label class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-canvas">
                                    <input type="checkbox" wire:model="empleadosSel" value="{{ $emp->id }}" class="rounded border-line text-primary focus:ring-primary">
                                    <span class="text-ink">{{ $emp->apellidos }}, {{ $emp->nombres }}</span>
                                    <span class="text-faint tabular-nums ml-auto">{{ $emp->tipo_documento }} {{ $emp->numero_documento }}</span>
                                </label>
                            @empty
                                <div class="px-3 py-6 text-center text-faint text-sm">Sin coincidencias.</div>
                            @endforelse
                        </div>
                        @error('empleadosSel') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
