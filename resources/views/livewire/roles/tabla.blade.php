<?php

use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';
    public array $permisosSel = [];

    // Roles del sistema: no se pueden eliminar (sí editar sus permisos).
    public const BASE = ['SuperAdmin', 'RRHH', 'Supervisor', 'Gerencia', 'Empleado', 'Contador'];

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'permisosSel']);
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editarRol(int $id): void
    {
        $rol = Role::findOrFail($id);
        $this->editandoId = $rol->id;
        $this->nombre = $rol->name;
        $this->permisosSel = $rol->permissions->pluck('name')->all();
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($this->editandoId)],
            'permisosSel' => ['array'],
            'permisosSel.*' => [Rule::in(Permission::pluck('name')->all())],
        ], [], ['nombre' => 'nombre']);

        $rol = $this->editandoId
            ? tap(Role::findOrFail($this->editandoId), fn ($r) => $r->update(['name' => $datos['nombre']]))
            : Role::create(['name' => $datos['nombre'], 'guard_name' => 'web']);

        // El SuperAdmin puede todo por diseño; no tiene sentido limitarlo.
        if ($rol->name !== 'SuperAdmin') {
            $rol->syncPermissions($datos['permisosSel']);
        }

        $this->mostrarForm = false;
        session()->flash('ok', 'Rol guardado.');
    }

    public function eliminar(int $id): void
    {
        $rol = Role::findOrFail($id);
        if (in_array($rol->name, self::BASE, true)) {
            session()->flash('error', 'Los roles del sistema no se pueden eliminar.');

            return;
        }
        if ($rol->users()->count() > 0) {
            session()->flash('error', 'No se puede eliminar: hay usuarios con este rol.');

            return;
        }
        $rol->delete();
        session()->flash('ok', 'Rol eliminado.');
    }

    /** Marca/desmarca todas las acciones de un módulo. */
    public function toggleModulo(string $modulo): void
    {
        $acciones = collect(array_keys(config("permisos.$modulo.acciones")))->map(fn ($a) => "$modulo.$a");
        $todas = $acciones->every(fn ($p) => in_array($p, $this->permisosSel, true));
        $this->permisosSel = $todas
            ? array_values(array_diff($this->permisosSel, $acciones->all()))
            : array_values(array_unique(array_merge($this->permisosSel, $acciones->all())));
    }

    public function with(): array
    {
        return [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
            'modulos' => config('permisos'),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-danger-tint text-danger px-4 py-2 text-sm font-medium">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <p class="text-sm text-muted">Define qué <strong>módulos y acciones</strong> puede usar cada rol. El SuperAdmin siempre puede todo.</p>
        <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            <x-icon name="plus" class="w-4 h-4" /> Nuevo rol
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[560px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3 text-center">Permisos</th>
                    <th class="px-4 py-3 text-center">Usuarios</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $rol)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 font-medium text-ink">
                            {{ $rol->name }}
                            @if ($rol->name === 'SuperAdmin')<span class="ml-1 text-xs text-primary">(todo)</span>@endif
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums text-muted">{{ $rol->name === 'SuperAdmin' ? '∞' : $rol->permissions_count }}</td>
                        <td class="px-4 py-3 text-center tabular-nums text-muted">{{ $rol->users_count }}</td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                <button wire:click="editarRol({{ $rol->id }})" class="{{ $btn }} text-primary" title="Configurar accesos">
                                    <x-icon name="pencil" />
                                </button>
                                @unless (in_array($rol->name, ['SuperAdmin','RRHH','Supervisor','Gerencia','Empleado','Contador'], true))
                                    <button wire:click="eliminar({{ $rol->id }})" wire:confirm="¿Eliminar el rol {{ $rol->name }}?" class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal matriz --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-2xl mt-8 mb-8 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Configurar rol' : 'Nuevo rol' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Nombre del rol *</label>
                        <input type="text" wire:model="nombre" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary" {{ $nombre === 'SuperAdmin' ? 'readonly' : '' }}>
                        @error('nombre') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>

                    @if ($nombre === 'SuperAdmin')
                        <div class="rounded-lg bg-primary-tint text-primary-dark px-4 py-3 text-sm">El SuperAdmin tiene acceso total por diseño; no requiere configuración.</div>
                    @else
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-muted">Módulos y acciones permitidas</label>
                            @foreach ($modulos as $mod => $data)
                                <div class="rounded-lg border border-line p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-semibold text-ink">{{ $data['label'] }}</span>
                                        <button type="button" wire:click="toggleModulo('{{ $mod }}')" class="text-xs text-primary hover:underline">Marcar/limpiar todo</button>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($data['acciones'] as $accion => $etiqueta)
                                            <label class="flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs cursor-pointer hover:bg-canvas">
                                                <input type="checkbox" wire:model="permisosSel" value="{{ $mod.'.'.$accion }}" class="rounded border-line text-primary focus:ring-primary">
                                                <span class="text-ink">{{ $etiqueta }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
