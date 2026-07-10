<?php

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public string $buscar = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public array $roles = [];
    public ?int $empleado_id = null;
    public bool $activo = true;

    // Reset de contraseña
    public bool $mostrarReset = false;
    public ?int $resetId = null;
    public string $nuevaPassword = '';

    public function esSuper(): bool
    {
        return auth()->user()?->hasRole('SuperAdmin') ?? false;
    }

    /** No se puede tocar a un SuperAdmin si tú no lo eres. */
    private function puedeGestionar(User $u): bool
    {
        return $this->esSuper() || ! $u->hasRole('SuperAdmin');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'password' => [$this->editandoId ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in(Role::pluck('name')->all())],
            'empleado_id' => ['nullable', 'exists:empleados,id'],
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'name', 'email', 'password', 'roles', 'empleado_id']);
        $this->activo = true;
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $u = User::with('empleado')->findOrFail($id);
        abort_unless($this->puedeGestionar($u), 403);

        $this->editandoId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->password = '';
        $this->roles = $u->getRoleNames()->all();
        $this->empleado_id = $u->empleado?->id;
        $this->activo = $u->activo;
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        // Solo un SuperAdmin puede otorgar el rol SuperAdmin.
        $roles = collect($this->roles);
        if (! $this->esSuper()) {
            $roles = $roles->reject(fn ($r) => $r === 'SuperAdmin');
        }

        $u = User::findOrNew($this->editandoId);
        abort_unless($this->puedeGestionar($u), 403);

        $u->name = $datos['name'];
        $u->email = $datos['email'];
        $u->activo = $this->activo;
        if ($this->password !== '') {
            $u->password = Hash::make($this->password);
        }
        $u->save();

        $u->syncRoles($roles->all());

        // Vincular al empleado (1-1): libera al empleado anterior y asigna el nuevo.
        Empleado::where('user_id', $u->id)->update(['user_id' => null]);
        if ($this->empleado_id) {
            Empleado::whereKey($this->empleado_id)->update(['user_id' => $u->id]);
        }

        $this->mostrarForm = false;
        session()->flash('ok', 'Usuario guardado.');
    }

    public function abrirReset(int $id): void
    {
        $u = User::findOrFail($id);
        abort_unless($this->puedeGestionar($u), 403);
        $this->resetId = $id;
        $this->nuevaPassword = '';
        $this->resetErrorBag();
        $this->mostrarReset = true;
    }

    public function resetearPassword(): void
    {
        $this->validate(['nuevaPassword' => ['required', 'string', 'min:8']], [], ['nuevaPassword' => 'contraseña']);
        $u = User::findOrFail($this->resetId);
        abort_unless($this->puedeGestionar($u), 403);
        $u->update(['password' => Hash::make($this->nuevaPassword)]);
        $this->mostrarReset = false;
        session()->flash('ok', 'Contraseña restablecida.');
    }

    public function toggleActivo(int $id): void
    {
        $u = User::findOrFail($id);
        abort_unless($this->puedeGestionar($u), 403);
        if ($u->id === auth()->id()) {
            session()->flash('error', 'No puedes desactivar tu propia cuenta.');

            return;
        }
        $u->update(['activo' => ! $u->activo]);
        session()->flash('ok', 'Estado del usuario actualizado.');
    }

    public function eliminar(int $id): void
    {
        $u = User::findOrFail($id);
        abort_unless($this->puedeGestionar($u), 403);
        if ($u->id === auth()->id()) {
            session()->flash('error', 'No puedes eliminar tu propia cuenta.');

            return;
        }
        if ($u->hasRole('SuperAdmin') && User::role('SuperAdmin')->count() <= 1) {
            session()->flash('error', 'No puedes eliminar al último Super Admin.');

            return;
        }
        Empleado::where('user_id', $u->id)->update(['user_id' => null]);
        $u->delete();
        session()->flash('ok', 'Usuario eliminado.');
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $rolesDisponibles = Role::orderBy('name')->pluck('name')
            ->reject(fn ($r) => $r === 'SuperAdmin' && ! $this->esSuper())
            ->values();

        return [
            'usuarios' => User::query()
                ->with(['roles', 'empleado'])
                ->when($this->buscar, fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', '%'.$this->buscar.'%')
                    ->orWhere('email', 'like', '%'.$this->buscar.'%')))
                ->orderBy('name')->paginate(10),
            'rolesDisponibles' => $rolesDisponibles,
            'empleadosVinculables' => Empleado::where('situacion', 'activo')
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $this->editandoId))
                ->orderBy('apellidos')->get(),
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

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex-1 min-w-[180px] flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2">
            <x-icon name="search" class="w-4 h-4 text-faint shrink-0" />
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por nombre o correo…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>
        <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
            <x-icon name="plus" class="w-4 h-4" /> Nuevo usuario
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[760px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Roles</th>
                    <th class="px-4 py-3">Empleado vinculado</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium text-ink">{{ $u->name }}</div>
                            <div class="text-faint text-xs">{{ $u->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @forelse ($u->roles as $r)
                                <span class="inline-flex items-center rounded-full bg-primary-tint text-primary px-2 py-0.5 text-xs font-semibold mr-1">{{ $r->name }}</span>
                            @empty
                                <span class="text-faint text-xs">Sin rol</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $u->empleado ? $u->empleado->apellidos.', '.$u->empleado->nombres : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($u->activo)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold"><span class="w-2 h-2 rounded-full bg-current"></span>Activo</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-canvas text-muted border border-line px-2.5 py-0.5 text-xs font-semibold"><span class="w-2 h-2 rounded-full bg-current"></span>Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                <button wire:click="abrirReset({{ $u->id }})" class="{{ $btn }} text-muted hover:text-primary" title="Restablecer contraseña">
                                    <x-icon name="key" />
                                </button>
                                <button wire:click="toggleActivo({{ $u->id }})" class="{{ $btn }} {{ $u->activo ? 'text-warning' : 'text-success' }}" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                    <x-icon name="{{ $u->activo ? 'ban' : 'check' }}" />
                                </button>
                                <button wire:click="editar({{ $u->id }})" class="{{ $btn }} text-primary" title="Editar">
                                    <x-icon name="pencil" />
                                </button>
                                <button wire:click="eliminar({{ $u->id }})" wire:confirm="¿Eliminar el usuario {{ $u->name }}?" class="{{ $btn }} text-danger" title="Eliminar">
                                    <x-icon name="trash" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $usuarios->links() }}</div>

    {{-- Modal usuario --}}
    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nombre *</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Correo *</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('email') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">{{ $editandoId ? 'Contraseña (dejar en blanco para no cambiar)' : 'Contraseña *' }}</label>
                            <input type="text" wire:model="password" placeholder="Mínimo 8 caracteres" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Roles</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($rolesDisponibles as $rol)
                                <label class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm cursor-pointer hover:bg-canvas">
                                    <input type="checkbox" wire:model="roles" value="{{ $rol }}" class="rounded border-line text-primary focus:ring-primary">
                                    <span class="text-ink">{{ $rol }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Empleado vinculado (para autoservicio)</label>
                        <select wire:model="empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Ninguno —</option>
                            @foreach ($empleadosVinculables as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }} ({{ $e->numero_documento }})</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" wire:model="activo" class="rounded border-line text-primary focus:ring-primary">
                        Cuenta activa (puede iniciar sesión)
                    </label>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarForm', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal reset de contraseña --}}
    @if ($mostrarReset)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-sm mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Restablecer contraseña</h3>
                    <button wire:click="$set('mostrarReset', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="resetearPassword" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Nueva contraseña *</label>
                        <input type="text" wire:model="nuevaPassword" placeholder="Mínimo 8 caracteres" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('nuevaPassword') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarReset', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Restablecer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
