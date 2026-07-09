<?php

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Sede;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $filtroSituacion = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    // Campos del formulario
    public string $tipo_documento = 'DNI';
    public string $numero_documento = '';
    public string $nombres = '';
    public string $apellidos = '';
    public string $fecha_nacimiento = '';
    public string $sexo = '';
    public string $estado_civil = '';
    public string $nacionalidad = 'Peruana';
    public string $telefono = '';
    public string $correo = '';
    public string $direccion = '';
    // Contacto de emergencia
    public string $emergencia_nombre = '';
    public string $emergencia_parentesco = '';
    public string $emergencia_telefono = '';
    // Laboral
    public ?int $area_id = null;
    public ?int $cargo_id = null;
    public ?int $sede_id = null;
    public string $fecha_ingreso = '';
    public string $tipo_contrato = '';
    public string $tipo_trabajador = '';
    public string $regimen_laboral = '';
    // Planilla
    public string $sueldo = '';
    public string $sistema_pensionario = '';
    public string $cuspp = '';
    public string $regimen_salud = 'EsSalud';
    // Bancario
    public string $banco = '';
    public string $numero_cuenta = '';
    public string $cci = '';
    // Estado
    public string $situacion = 'activo';
    public string $fecha_cese = '';

    public function puedeVerSueldo(): bool
    {
        return auth()->user()?->hasAnyRole(['RRHH', 'Gerencia', 'Contador']) ?? false;
    }

    protected function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:20', Rule::unique('empleados', 'numero_documento')->ignore($this->editandoId)],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'in:M,F'],
            'estado_civil' => ['nullable', 'string', 'max:20'],
            'nacionalidad' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'emergencia_nombre' => ['nullable', 'string', 'max:120'],
            'emergencia_parentesco' => ['nullable', 'string', 'max:40'],
            'emergencia_telefono' => ['nullable', 'string', 'max:30'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'cargo_id' => ['nullable', 'exists:cargos,id'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'fecha_ingreso' => ['nullable', 'date'],
            'tipo_contrato' => ['nullable', 'string', 'max:60'],
            'tipo_trabajador' => ['nullable', 'string', 'max:60'],
            'regimen_laboral' => ['nullable', 'string', 'max:60'],
            'sueldo' => ['nullable', 'numeric', 'min:0'],
            'sistema_pensionario' => ['nullable', 'string', 'max:20'],
            'cuspp' => ['nullable', 'string', 'max:40'],
            'regimen_salud' => ['nullable', 'string', 'max:40'],
            'banco' => ['nullable', 'string', 'max:60'],
            'numero_cuenta' => ['nullable', 'string', 'max:40'],
            'cci' => ['nullable', 'string', 'max:25'],
            'situacion' => ['required', 'in:activo,cesado'],
            'fecha_cese' => ['nullable', 'date', 'required_if:situacion,cesado'],
        ];
    }

    public function nuevo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $e = Empleado::findOrFail($id);
        $this->editandoId = $e->id;
        $this->tipo_documento = $e->tipo_documento ?? 'DNI';
        $this->numero_documento = $e->numero_documento ?? '';
        $this->nombres = $e->nombres ?? '';
        $this->apellidos = $e->apellidos ?? '';
        $this->fecha_nacimiento = optional($e->fecha_nacimiento)->format('Y-m-d') ?? '';
        $this->sexo = $e->sexo ?? '';
        $this->estado_civil = $e->estado_civil ?? '';
        $this->nacionalidad = $e->nacionalidad ?? '';
        $this->telefono = $e->telefono ?? '';
        $this->correo = $e->correo ?? '';
        $this->direccion = $e->direccion ?? '';
        $this->emergencia_nombre = $e->emergencia_nombre ?? '';
        $this->emergencia_parentesco = $e->emergencia_parentesco ?? '';
        $this->emergencia_telefono = $e->emergencia_telefono ?? '';
        $this->area_id = $e->area_id;
        $this->cargo_id = $e->cargo_id;
        $this->sede_id = $e->sede_id;
        $this->fecha_ingreso = optional($e->fecha_ingreso)->format('Y-m-d') ?? '';
        $this->tipo_contrato = $e->tipo_contrato ?? '';
        $this->tipo_trabajador = $e->tipo_trabajador ?? '';
        $this->regimen_laboral = $e->regimen_laboral ?? '';
        $this->sueldo = $this->puedeVerSueldo() ? (string) ($e->sueldo ?? '') : '';
        $this->sistema_pensionario = $e->sistema_pensionario ?? '';
        $this->cuspp = $e->cuspp ?? '';
        $this->regimen_salud = $e->regimen_salud ?? '';
        $this->banco = $e->banco ?? '';
        $this->numero_cuenta = $e->numero_cuenta ?? '';
        $this->cci = $e->cci ?? '';
        $this->situacion = $e->situacion ?? 'activo';
        $this->fecha_cese = optional($e->fecha_cese)->format('Y-m-d') ?? '';
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $data = $this->validate();

        // Si está activo, no debe quedar fecha de cese
        if ($data['situacion'] !== 'cesado') {
            $data['fecha_cese'] = null;
        }

        // Normaliza cadenas vacías a null
        foreach ($data as $k => $v) {
            if ($v === '') {
                $data[$k] = null;
            }
        }

        // El sueldo solo lo actualiza quien tiene permiso (no lo pisan los Supervisores)
        if (! $this->puedeVerSueldo()) {
            unset($data['sueldo']);
        }

        Empleado::updateOrCreate(['id' => $this->editandoId], $data);
        $this->mostrarForm = false;
        $this->resetForm();
        session()->flash('ok', 'Empleado guardado correctamente.');
    }

    public function eliminar(int $id): void
    {
        Empleado::findOrFail($id)->delete();
        session()->flash('ok', 'Empleado eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editandoId', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento',
            'sexo', 'estado_civil', 'telefono', 'correo', 'direccion',
            'emergencia_nombre', 'emergencia_parentesco', 'emergencia_telefono',
            'area_id', 'cargo_id', 'sede_id', 'fecha_ingreso', 'tipo_contrato', 'tipo_trabajador',
            'regimen_laboral', 'sueldo', 'sistema_pensionario', 'cuspp', 'banco', 'numero_cuenta', 'cci',
            'fecha_cese',
        ]);
        $this->tipo_documento = 'DNI';
        $this->nacionalidad = 'Peruana';
        $this->regimen_salud = 'EsSalud';
        $this->situacion = 'activo';
        $this->resetErrorBag();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $empleados = Empleado::query()
            ->with(['area', 'cargo', 'sede'])
            ->when($this->buscar, fn ($q) => $q->where(fn ($w) => $w
                ->where('nombres', 'like', '%'.$this->buscar.'%')
                ->orWhere('apellidos', 'like', '%'.$this->buscar.'%')
                ->orWhere('numero_documento', 'like', '%'.$this->buscar.'%')))
            ->when($this->filtroSituacion, fn ($q) => $q->where('situacion', $this->filtroSituacion))
            ->orderBy('apellidos')
            ->paginate(10);

        return [
            'empleados' => $empleados,
            'areas' => Area::orderBy('nombre')->get(),
            'cargos' => Cargo::orderBy('nombre')->get(),
            'sedes' => Sede::orderBy('nombre')->get(),
        ];
    }
}; ?>

<div>
    {{-- Mensaje de éxito --}}
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">
            {{ session('ok') }}
        </div>
    @endif

    {{-- Barra de acciones --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2">
            <span class="text-faint">🔎</span>
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por nombre o documento…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>

        <select wire:model.live="filtroSituacion"
                class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos</option>
            <option value="activo">Activos</option>
            <option value="cesado">Cesados</option>
        </select>

        <button wire:click="nuevo"
                class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            + Nuevo
        </button>

        <a href="{{ route('empleados.exportar', ['buscar' => $buscar, 'situacion' => $filtroSituacion]) }}"
           class="rounded-lg bg-excel hover:brightness-95 text-white text-sm font-semibold px-4 py-2">
            ⬇ Exportar a Excel
        </a>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[640px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Área</th>
                    <th class="px-4 py-3">Cargo</th>
                    <th class="px-4 py-3">Sede</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($empleados as $e)
                    <tr class="border-b border-line last:border-0 hover:bg-canvas/60">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('empleados.show', $e) }}" wire:navigate class="text-primary hover:underline" title="Ver expediente">
                                {{ $e->apellidos }}, {{ $e->nombres }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $e->tipo_documento }} {{ $e->numero_documento }}</td>
                        <td class="px-4 py-3 text-muted">{{ $e->area?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $e->cargo?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $e->sede?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($e->situacion === 'activo')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-success"></span>Activo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-danger-tint text-danger px-2.5 py-0.5 text-xs font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-danger"></span>Cesado
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="editar({{ $e->id }})" class="text-primary hover:underline text-sm font-medium">Editar</button>
                            <button wire:click="eliminar({{ $e->id }})" wire:confirm="¿Eliminar a {{ $e->nombres }} {{ $e->apellidos }}?"
                                    class="ml-3 text-danger hover:underline text-sm font-medium">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-faint">No se encontraron empleados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $empleados->links() }}
    </div>

    {{-- Modal de formulario --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-3xl mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">
                        {{ $editandoId ? 'Editar empleado' : 'Nuevo empleado' }}
                    </h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>

                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo doc.</label>
                            <select wire:model="tipo_documento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="DNI">DNI</option>
                                <option value="CE">Carné de Extranjería</option>
                                <option value="PAS">Pasaporte</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">N° documento *</label>
                            <input type="text" wire:model="numero_documento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('numero_documento') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nombres *</label>
                            <input type="text" wire:model="nombres" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('nombres') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Apellidos *</label>
                            <input type="text" wire:model="apellidos" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('apellidos') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha de nacimiento</label>
                            <input type="date" wire:model="fecha_nacimiento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Sexo</label>
                            <select wire:model="sexo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">—</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Estado civil</label>
                            <input type="text" wire:model="estado_civil" placeholder="Soltero(a), Casado(a)…" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nacionalidad</label>
                            <input type="text" wire:model="nacionalidad" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>

                        {{-- Sección: Laboral --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Datos laborales</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Área</label>
                            <select wire:model="area_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($areas as $a)
                                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Cargo</label>
                            <select wire:model="cargo_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($cargos as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Sede</label>
                            <select wire:model="sede_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha de ingreso</label>
                            <input type="date" wire:model="fecha_ingreso" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo de contrato</label>
                            <input type="text" wire:model="tipo_contrato" placeholder="Indefinido, Plazo fijo…" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo de trabajador</label>
                            <input type="text" wire:model="tipo_trabajador" placeholder="Empleado, Practicante…" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Régimen laboral</label>
                            <input type="text" wire:model="regimen_laboral" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>

                        {{-- Sección: Contacto --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Contacto</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Teléfono</label>
                            <input type="text" wire:model="telefono" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Correo</label>
                            <input type="email" wire:model="correo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('correo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Dirección</label>
                            <input type="text" wire:model="direccion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>

                        {{-- Sección: Contacto de emergencia --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Contacto de emergencia</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nombre</label>
                            <input type="text" wire:model="emergencia_nombre" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Parentesco</label>
                            <input type="text" wire:model="emergencia_parentesco" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Teléfono</label>
                            <input type="text" wire:model="emergencia_telefono" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>

                        {{-- Sección: Planilla --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Planilla</span>
                        </div>
                        @if ($this->puedeVerSueldo())
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Sueldo (S/)</label>
                                <input type="number" step="0.01" min="0" wire:model="sueldo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('sueldo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Sistema pensionario</label>
                            <select wire:model="sistema_pensionario" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">—</option>
                                <option value="ONP">ONP</option>
                                <option value="AFP">AFP</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">CUSPP (AFP)</label>
                            <input type="text" wire:model="cuspp" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Régimen de salud</label>
                            <input type="text" wire:model="regimen_salud" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>

                        {{-- Sección: Bancario --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Datos bancarios</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Banco</label>
                            <input type="text" wire:model="banco" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">N° de cuenta</label>
                            <input type="text" wire:model="numero_cuenta" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">CCI (interbancaria)</label>
                            <input type="text" wire:model="cci" placeholder="20 dígitos" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('cci') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Sección: Estado --}}
                        <div class="sm:col-span-2 border-t border-line pt-3 mt-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Estado</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Situación</label>
                            <select wire:model.live="situacion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="activo">Activo</option>
                                <option value="cesado">Cesado</option>
                            </select>
                        </div>
                        @if ($situacion === 'cesado')
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Fecha de cese *</label>
                                <input type="date" wire:model="fecha_cese" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('fecha_cese') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('mostrarForm', false)"
                                class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
