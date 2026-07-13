<?php

use App\Models\Cliente;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $buscar = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $razon_social = '';
    public string $nombre_comercial = '';
    public string $ruc = '';

    protected function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:160'],
            'nombre_comercial' => ['nullable', 'string', 'max:160'],
            'ruc' => ['nullable', 'string', 'max:11', Rule::unique('clientes', 'ruc')->ignore($this->editandoId)],
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'razon_social', 'nombre_comercial', 'ruc']);
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $c = Cliente::findOrFail($id);
        $this->editandoId = $c->id;
        $this->razon_social = $c->razon_social;
        $this->nombre_comercial = $c->nombre_comercial ?? '';
        $this->ruc = $c->ruc ?? '';
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'clientes.editar' : 'clientes.crear'), 403);
        $datos = $this->validate();

        Cliente::updateOrCreate(['id' => $this->editandoId], [
            'razon_social' => $datos['razon_social'],
            'nombre_comercial' => $datos['nombre_comercial'] ?: null,
            'ruc' => $datos['ruc'] ?: null,
        ]);

        $this->mostrarForm = false;
        session()->flash('ok', 'Cliente guardado.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('clientes.eliminar'), 403);
        Cliente::findOrFail($id)->delete();
        session()->flash('ok', 'Cliente eliminado.');
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'clientes' => Cliente::query()
                ->withCount('sucursales')
                ->when($this->buscar, fn ($q) => $q->where(fn ($w) => $w
                    ->where('razon_social', 'like', '%'.$this->buscar.'%')
                    ->orWhere('nombre_comercial', 'like', '%'.$this->buscar.'%')
                    ->orWhere('ruc', 'like', '%'.$this->buscar.'%')))
                ->orderBy('razon_social')->paginate(10),
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
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar cliente o RUC…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>
        <a href="{{ route('sedes.index') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-line text-muted hover:bg-canvas text-sm font-semibold px-4 py-2">
            <x-icon name="home" class="w-4 h-4" /> Nuestras sedes
        </a>
        @can('clientes.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                <x-icon name="plus" class="w-4 h-4" /> Nuevo cliente
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[640px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">RUC</th>
                    <th class="px-4 py-3 text-center">Sucursales</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clientes as $c)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium text-ink">{{ $c->nombre_comercial ?: $c->razon_social }}</div>
                            @if ($c->nombre_comercial)<div class="text-faint text-xs">{{ $c->razon_social }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $c->ruc ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('clientes.sucursales', $c) }}" wire:navigate class="text-primary hover:underline font-semibold">{{ $c->sucursales_count }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                <a href="{{ route('clientes.sucursales', $c) }}" wire:navigate class="{{ $btn }} text-muted hover:text-primary" title="Sucursales">
                                    <x-icon name="clipboard" />
                                </a>
                                @can('clientes.editar')
                                    <button wire:click="editar({{ $c->id }})" class="{{ $btn }} text-primary" title="Editar">
                                        <x-icon name="pencil" />
                                    </button>
                                @endcan
                                @can('clientes.eliminar')
                                    <button wire:click="eliminar({{ $c->id }})" wire:confirm="¿Eliminar el cliente y sus sucursales?" class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Sin clientes registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>

    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar cliente' : 'Nuevo cliente' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Razón social *</label>
                        <input type="text" wire:model="razon_social" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('razon_social') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Nombre comercial</label>
                        <input type="text" wire:model="nombre_comercial" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">RUC</label>
                        <input type="text" wire:model="ruc" maxlength="11" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('ruc') <span class="text-danger text-xs">{{ $message }}</span> @enderror
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
