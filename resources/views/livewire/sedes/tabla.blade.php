<?php

use App\Models\Sede;
use Livewire\Volt\Component;

new class extends Component {
    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';
    public string $tipo = 'oficina';
    public string $direccion = '';
    public string $latitud = '';
    public string $longitud = '';
    public int $radio_metros = 100;

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:oficina,almacen,otro'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'radio_metros' => ['required', 'integer', 'min:10', 'max:5000'],
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'direccion', 'latitud', 'longitud']);
        $this->tipo = 'oficina';
        $this->radio_metros = 100;
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $s = Sede::findOrFail($id);
        $this->editandoId = $s->id;
        $this->nombre = $s->nombre;
        $this->tipo = $s->tipo ?? 'oficina';
        $this->direccion = $s->direccion ?? '';
        $this->latitud = $s->latitud !== null ? (string) $s->latitud : '';
        $this->longitud = $s->longitud !== null ? (string) $s->longitud : '';
        $this->radio_metros = $s->radio_metros ?? 100;
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'clientes.editar' : 'clientes.crear'), 403);
        $datos = $this->validate();

        Sede::updateOrCreate(['id' => $this->editandoId], [
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'direccion' => $datos['direccion'] ?: null,
            'latitud' => $datos['latitud'] !== '' ? $datos['latitud'] : null,
            'longitud' => $datos['longitud'] !== '' ? $datos['longitud'] : null,
            'radio_metros' => $datos['radio_metros'],
        ]);

        $this->mostrarForm = false;
        session()->flash('ok', 'Sede guardada.');
    }

    public function with(): array
    {
        return ['sedes' => Sede::orderBy('nombre')->get()];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <a href="{{ route('clientes.index') }}" wire:navigate class="text-sm text-primary hover:underline">← Clientes</a>
        @can('clientes.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                <x-icon name="plus" class="w-4 h-4" /> Nueva sede
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[560px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Sede</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Geocerca</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sedes as $s)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium text-ink">{{ $s->nombre }}</div>
                            @if ($s->direccion)<div class="text-faint text-xs">{{ $s->direccion }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-muted capitalize">{{ $s->tipo ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($s->tieneUbicacion())
                                <span class="inline-flex items-center rounded-full bg-primary-tint text-primary px-2.5 py-0.5 text-xs font-semibold">{{ $s->radio_metros }} m</span>
                                <div class="text-faint text-xs tabular-nums mt-0.5">{{ $s->latitud }}, {{ $s->longitud }}</div>
                            @else
                                <span class="text-danger text-xs">Sin coordenadas</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @can('clientes.editar')
                                    <button wire:click="editar({{ $s->id }})" class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas text-primary" title="Editar">
                                        <x-icon name="pencil" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Sin sedes registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar sede' : 'Nueva sede' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('nombre') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo</label>
                            <select wire:model="tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="oficina">Oficina</option>
                                <option value="almacen">Almacén</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Dirección</label>
                        <input type="text" wire:model="direccion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div class="rounded-lg border border-line p-3 space-y-3" x-data>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary">Geocerca</span>
                            <button type="button"
                                    x-on:click="navigator.geolocation.getCurrentPosition(
                                        p => { $wire.set('latitud', p.coords.latitude.toFixed(7)); $wire.set('longitud', p.coords.longitude.toFixed(7)); },
                                        e => alert('No se pudo obtener la ubicación: ' + e.message),
                                        { enableHighAccuracy: true })"
                                    class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                <x-icon name="home" class="w-3.5 h-3.5" /> Usar mi ubicación
                            </button>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-muted mb-1">Latitud</label>
                                <input type="text" wire:model="latitud" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('latitud') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-muted mb-1">Longitud</label>
                                <input type="text" wire:model="longitud" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('longitud') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-muted mb-1">Radio (m)</label>
                                <input type="number" wire:model="radio_metros" min="10" max="5000" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('radio_metros') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
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
