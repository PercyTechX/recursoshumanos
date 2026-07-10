<?php

use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Ubigeo;
use Livewire\Volt\Component;

new class extends Component {
    public int $clienteId;

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';
    public string $direccion = '';
    public string $latitud = '';
    public string $longitud = '';
    public int $radio_metros = 100;
    public string $departamento = '';
    public string $provincia = '';
    public string $distrito = '';
    public string $centro_costo = '';

    public function mount(Cliente $cliente): void
    {
        $this->clienteId = $cliente->id;
    }

    public function updatedDepartamento(): void
    {
        $this->provincia = '';
        $this->distrito = '';
    }

    public function updatedProvincia(): void
    {
        $this->distrito = '';
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'radio_metros' => ['required', 'integer', 'min:10', 'max:5000'],
            'departamento' => ['nullable', 'string', 'max:60'],
            'provincia' => ['nullable', 'string', 'max:60'],
            'distrito' => ['nullable', 'string', 'max:60'],
            'centro_costo' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'direccion', 'latitud', 'longitud', 'departamento', 'provincia', 'distrito', 'centro_costo']);
        $this->radio_metros = 100;
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $s = Sucursal::where('cliente_id', $this->clienteId)->findOrFail($id);
        $this->editandoId = $s->id;
        $this->nombre = $s->nombre;
        $this->direccion = $s->direccion ?? '';
        $this->latitud = $s->latitud !== null ? (string) $s->latitud : '';
        $this->longitud = $s->longitud !== null ? (string) $s->longitud : '';
        $this->radio_metros = $s->radio_metros ?? 100;
        $this->departamento = $s->departamento ?? '';
        $this->provincia = $s->provincia ?? '';
        $this->distrito = $s->distrito ?? '';
        $this->centro_costo = $s->centro_costo ?? '';
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'clientes.editar' : 'clientes.crear'), 403);
        $datos = $this->validate();

        Sucursal::updateOrCreate(
            ['id' => $this->editandoId, 'cliente_id' => $this->clienteId],
            [
                'cliente_id' => $this->clienteId,
                'nombre' => $datos['nombre'],
                'direccion' => $datos['direccion'] ?: null,
                'latitud' => $datos['latitud'] !== '' ? $datos['latitud'] : null,
                'longitud' => $datos['longitud'] !== '' ? $datos['longitud'] : null,
                'radio_metros' => $datos['radio_metros'],
                'departamento' => $datos['departamento'] ?: null,
                'provincia' => $datos['provincia'] ?: null,
                'distrito' => $datos['distrito'] ?: null,
                'centro_costo' => $datos['centro_costo'] ?: null,
            ],
        );

        $this->mostrarForm = false;
        session()->flash('ok', 'Sucursal guardada.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('clientes.eliminar'), 403);
        Sucursal::where('cliente_id', $this->clienteId)->findOrFail($id)->delete();
        session()->flash('ok', 'Sucursal eliminada.');
    }

    public function with(): array
    {
        return [
            'cliente' => Cliente::findOrFail($this->clienteId),
            'sucursales' => Sucursal::where('cliente_id', $this->clienteId)->orderBy('nombre')->get(),
            'departamentos' => Ubigeo::departamentos(),
            'provincias' => Ubigeo::provincias($this->departamento ?: null),
            'distritos' => Ubigeo::distritos($this->departamento ?: null, $this->provincia ?: null),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div>
            <a href="{{ route('clientes.index') }}" wire:navigate class="text-sm text-primary hover:underline">← Clientes</a>
            <h3 class="text-lg font-semibold text-navy">{{ $cliente->nombre_comercial ?: $cliente->razon_social }}</h3>
        </div>
        @can('clientes.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                <x-icon name="plus" class="w-4 h-4" /> Nueva sucursal
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Sucursal</th>
                    <th class="px-4 py-3">Ubicación</th>
                    <th class="px-4 py-3">Geocerca</th>
                    <th class="px-4 py-3">Centro de costo</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sucursales as $s)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium text-ink">{{ $s->nombre }}</div>
                            @if ($s->direccion)<div class="text-faint text-xs">{{ $s->direccion }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-muted text-xs">
                            {{ collect([$s->distrito, $s->provincia, $s->departamento])->filter()->implode(', ') ?: '—' }}
                            @if ($s->tieneUbicacion())<div class="text-faint tabular-nums">{{ $s->latitud }}, {{ $s->longitud }}</div>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($s->tieneUbicacion())
                                <span class="inline-flex items-center rounded-full bg-primary-tint text-primary px-2.5 py-0.5 text-xs font-semibold">{{ $s->radio_metros }} m</span>
                            @else
                                <span class="text-danger text-xs">Sin coordenadas</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $s->centro_costo ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                @can('clientes.editar')
                                    <button wire:click="editar({{ $s->id }})" class="{{ $btn }} text-primary" title="Editar">
                                        <x-icon name="pencil" />
                                    </button>
                                @endcan
                                @can('clientes.eliminar')
                                    <button wire:click="eliminar({{ $s->id }})" wire:confirm="¿Eliminar esta sucursal?" class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin sucursales. Agrega la primera.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar sucursal' : 'Nueva sucursal' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('nombre') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Dirección</label>
                        <input type="text" wire:model="direccion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>

                    {{-- Geocerca --}}
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Departamento</label>
                            <select wire:model.live="departamento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($departamentos as $dep)
                                    <option value="{{ $dep }}">{{ $dep }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Provincia</label>
                            <select wire:model.live="provincia" @disabled($departamento === '') class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary disabled:bg-canvas disabled:text-faint">
                                <option value="">— Seleccionar —</option>
                                @foreach ($provincias as $prov)
                                    <option value="{{ $prov }}">{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Distrito</label>
                            <select wire:model="distrito" @disabled($provincia === '') class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary disabled:bg-canvas disabled:text-faint">
                                <option value="">— Seleccionar —</option>
                                @foreach ($distritos as $dist)
                                    <option value="{{ $dist }}">{{ $dist }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Centro de costo</label>
                            <input type="text" wire:model="centro_costo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
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
