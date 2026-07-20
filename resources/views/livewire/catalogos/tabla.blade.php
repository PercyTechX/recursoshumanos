<?php

use App\Models\Area;
use App\Models\Cargo;
use App\Models\CategoriaActivo;
use App\Models\TipoDocumento;
use App\Models\TipoEpp;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $catalogo = 'areas';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public array $form = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('SuperAdmin'), 403);
    }

    /** Definición de cada catálogo: modelo, campos (con tipo) y dónde se usa (para el bloqueo). */
    public function defs(): array
    {
        return [
            'areas' => ['label' => 'Áreas', 'model' => Area::class, 'campos' => ['nombre' => 'texto'], 'uso' => ['empleados', 'area_id']],
            'cargos' => ['label' => 'Cargos', 'model' => Cargo::class, 'campos' => ['nombre' => 'texto'], 'uso' => ['empleados', 'cargo_id']],
            'categorias_activo' => ['label' => 'Categorías de activo', 'model' => CategoriaActivo::class, 'campos' => ['nombre' => 'texto'], 'uso' => ['activos', 'categoria_id']],
            'tipos_epp' => ['label' => 'Tipos de EPP', 'model' => TipoEpp::class, 'campos' => ['nombre' => 'texto', 'controla_talla' => 'bool'], 'uso' => ['entregas_epp', 'tipo_epp_id']],
            'tipos_documento' => ['label' => 'Tipos de documento', 'model' => TipoDocumento::class, 'campos' => ['nombre' => 'texto', 'dias_aviso_previo' => 'numero', 'requiere_vigencia' => 'bool', 'compartible' => 'bool'], 'uso' => ['documentos', 'tipo_documento_id']],
        ];
    }

    public const ETIQUETAS = [
        'nombre' => 'Nombre', 'controla_talla' => 'Controla talla',
        'dias_aviso_previo' => 'Días de aviso previo', 'requiere_vigencia' => 'Requiere vigencia',
        'compartible' => 'Compartible (1 archivo ampara a varios)',
    ];

    private function def(): array
    {
        return $this->defs()[$this->catalogo];
    }

    public function updatingCatalogo(): void
    {
        $this->mostrarForm = false;
        $this->editandoId = null;
    }

    public function nuevo(): void
    {
        $this->editandoId = null;
        $this->form = ['activo' => true];
        foreach ($this->def()['campos'] as $campo => $tipo) {
            $this->form[$campo] = $tipo === 'bool' ? false : ($tipo === 'numero' ? 30 : '');
        }
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $m = $this->def()['model']::findOrFail($id);
        $this->editandoId = $id;
        $this->form = ['activo' => (bool) $m->activo];
        foreach ($this->def()['campos'] as $campo => $tipo) {
            $this->form[$campo] = $tipo === 'bool' ? (bool) $m->{$campo} : ($m->{$campo} ?? '');
        }
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->hasRole('SuperAdmin'), 403);
        $model = $this->def()['model'];
        $tabla = (new $model)->getTable();

        $reglas = ['form.nombre' => ['required', 'string', 'max:120', Rule::unique($tabla, 'nombre')->ignore($this->editandoId)]];
        foreach ($this->def()['campos'] as $campo => $tipo) {
            if ($tipo === 'numero') {
                $reglas["form.{$campo}"] = ['nullable', 'integer', 'min:0', 'max:3650'];
            } elseif ($tipo === 'bool') {
                $reglas["form.{$campo}"] = ['boolean'];
            }
        }
        $reglas['form.activo'] = ['boolean'];
        $this->validate($reglas, [], ['form.nombre' => 'nombre']);

        $m = $this->editandoId ? $model::findOrFail($this->editandoId) : new $model;
        $m->fill($this->form)->save();

        $this->mostrarForm = false;
        session()->flash('ok', 'Catálogo guardado.');
    }

    public function toggleActivo(int $id): void
    {
        abort_unless(auth()->user()->hasRole('SuperAdmin'), 403);
        $m = $this->def()['model']::findOrFail($id);
        $m->update(['activo' => ! $m->activo]);
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->hasRole('SuperAdmin'), 403);
        [$tabla, $col] = $this->def()['uso'];

        if (DB::table($tabla)->where($col, $id)->exists()) {
            session()->flash('error', 'No se puede eliminar: está en uso por registros existentes. Puedes DESACTIVARlo (deja de aparecer en los formularios sin borrar el histórico).');

            return;
        }
        $this->def()['model']::findOrFail($id)->delete();
        session()->flash('ok', 'Elemento eliminado.');
    }

    public function with(): array
    {
        return [
            'items' => $this->def()['model']::orderBy('nombre')->get(),
            'def' => $this->def(),
            'defs' => $this->defs(),
        ];
    }
}; ?>

@php
    $labels = ['nombre' => 'Nombre', 'controla_talla' => 'Controla talla', 'dias_aviso_previo' => 'Días de aviso previo', 'requiere_vigencia' => 'Requiere vigencia', 'compartible' => 'Compartible (1 archivo ampara a varios)'];
    $labelCorto = ['dias_aviso_previo' => 'Días aviso', 'requiere_vigencia' => 'Requiere vigencia', 'compartible' => 'Compartible', 'controla_talla' => 'Controla talla'];
@endphp

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger-tint text-danger px-4 py-2 text-sm font-medium">{{ session('error') }}</div>
    @endif

    <p class="text-muted mb-4">Administra las listas del sistema. Lo que <strong>desactives</strong> deja de aparecer en los formularios pero conserva el histórico; solo se puede <strong>eliminar</strong> lo que no esté en uso.</p>

    {{-- Selector de catálogo --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($defs as $key => $d)
            <button wire:click="$set('catalogo', '{{ $key }}')" class="px-3 py-2 text-sm font-medium rounded-lg border {{ $catalogo === $key ? 'bg-primary text-white border-primary' : 'bg-surface text-muted border-line hover:bg-canvas' }}">{{ $d['label'] }}</button>
        @endforeach
        <div class="flex-1"></div>
        <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            <x-icon name="plus" class="w-4 h-4" /> Agregar
        </button>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[560px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Nombre</th>
                    @foreach ($def['campos'] as $campo => $tipo)
                        @continue($campo === 'nombre')
                        <th class="px-4 py-3">{{ $labelCorto[$campo] ?? ucfirst($campo) }}</th>
                    @endforeach
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $it)
                    <tr class="border-b border-line last:border-0 {{ $it->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 text-ink font-medium">{{ $it->nombre }}</td>
                        @foreach ($def['campos'] as $campo => $tipo)
                            @continue($campo === 'nombre')
                            <td class="px-4 py-3 text-muted">
                                @if ($tipo === 'bool')
                                    {!! $it->{$campo} ? '<span class="text-success text-xs font-semibold">Sí</span>' : '<span class="text-faint text-xs">No</span>' !!}
                                @else
                                    {{ $it->{$campo} }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3">
                            @if ($it->activo)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">Activo</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-canvas text-faint border border-line px-2.5 py-0.5 text-xs font-semibold">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 justify-end">
                                <button wire:click="editar({{ $it->id }})" class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-line text-primary hover:bg-canvas">Editar</button>
                                <button wire:click="toggleActivo({{ $it->id }})" class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-line {{ $it->activo ? 'text-muted' : 'text-success' }} hover:bg-canvas">{{ $it->activo ? 'Desactivar' : 'Activar' }}</button>
                                <button wire:click="eliminar({{ $it->id }})" wire:confirm="¿Eliminar «{{ $it->nombre }}»? Solo funciona si no está en uso." class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-danger/40 text-danger hover:bg-danger-tint">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-faint">Sin elementos. Usa «Agregar».</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    @if ($mostrarForm)
        @php
            $labels = ['nombre'=>'Nombre','controla_talla'=>'Controla talla','dias_aviso_previo'=>'Días de aviso previo','requiere_vigencia'=>'Requiere vigencia','compartible'=>'Compartible (1 archivo ampara a varios)'];
        @endphp
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar' : 'Agregar' }} · {{ $def['label'] }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    @foreach ($def['campos'] as $campo => $tipo)
                        <div>
                            @if ($tipo === 'bool')
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" wire:model="form.{{ $campo }}" class="rounded border-line text-primary focus:ring-primary">
                                    {{ $labels[$campo] ?? ucfirst($campo) }}
                                </label>
                            @else
                                <label class="block text-sm font-medium text-muted mb-1">{{ $labels[$campo] ?? ucfirst($campo) }} @if ($campo === 'nombre')*@endif</label>
                                <input type="{{ $tipo === 'numero' ? 'number' : 'text' }}" wire:model="form.{{ $campo }}" @if ($tipo === 'numero') min="0" @endif class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('form.'.$campo) <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            @endif
                        </div>
                    @endforeach
                    <label class="flex items-center gap-2 text-sm text-ink pt-1 border-t border-line">
                        <input type="checkbox" wire:model="form.activo" class="rounded border-line text-primary focus:ring-primary">
                        Activo (aparece en los formularios)
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
