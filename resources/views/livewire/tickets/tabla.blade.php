<?php

use App\Models\Cliente;
use App\Models\Sede;
use App\Models\Sucursal;
use App\Models\Ticket;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $filtroEstado = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $ticket_atencion = '';
    public ?int $cliente_id = null;
    public string $ubicacion_tipo = 'sucursal'; // sucursal | sede
    public ?int $sucursal_id = null;
    public ?int $sede_id = null;
    public string $descripcion = '';

    public function updatedClienteId(): void
    {
        $this->sucursal_id = null;
    }

    public function updatedUbicacionTipo(): void
    {
        $this->sucursal_id = null;
        $this->sede_id = null;
    }

    protected function rules(): array
    {
        return [
            'ticket_atencion' => ['required', 'string', 'max:60', Rule::unique('tickets', 'ticket_atencion')->ignore($this->editandoId)],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'ubicacion_tipo' => ['required', 'in:sucursal,sede'],
            // exclude_unless: si no aplica el tipo, el campo se ignora por completo
            // (no se valida su exists ni se exige) — evita errores "fantasma" ocultos.
            'sucursal_id' => ['exclude_unless:ubicacion_tipo,sucursal', 'required', 'exists:sucursales,id'],
            'sede_id' => ['exclude_unless:ubicacion_tipo,sede', 'required', 'exists:sedes,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'sucursal_id.required' => 'Elige la sucursal.',
            'sede_id.required' => 'Elige la sede.',
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'ticket_atencion', 'cliente_id', 'sucursal_id', 'sede_id', 'descripcion']);
        $this->ubicacion_tipo = 'sucursal';
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $t = Ticket::findOrFail($id);
        $this->editandoId = $t->id;
        $this->ticket_atencion = $t->ticket_atencion;
        $this->cliente_id = $t->cliente_id;
        $this->ubicacion_tipo = $t->sucursal_id ? 'sucursal' : 'sede';
        $this->sucursal_id = $t->sucursal_id;
        $this->sede_id = $t->sede_id;
        $this->descripcion = $t->descripcion ?? '';
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can($this->editandoId ? 'tickets.editar' : 'tickets.crear'), 403);
        $datos = $this->validate();

        $payload = [
            'ticket_atencion' => $datos['ticket_atencion'],
            'cliente_id' => $datos['cliente_id'],
            'sucursal_id' => $this->ubicacion_tipo === 'sucursal' ? $this->sucursal_id : null,
            'sede_id' => $this->ubicacion_tipo === 'sede' ? $this->sede_id : null,
            'descripcion' => $datos['descripcion'] ?: null,
        ];
        if (! $this->editandoId) {
            $payload['creado_por'] = auth()->id();
            $payload['estado'] = Ticket::ABIERTO;
        }

        Ticket::updateOrCreate(['id' => $this->editandoId], $payload);

        $this->mostrarForm = false;
        session()->flash('ok', 'Ticket guardado.');
    }

    public function cerrar(int $id): void
    {
        abort_unless(auth()->user()->can('tickets.cerrar'), 403);
        Ticket::whereKey($id)->update([
            'estado' => Ticket::CERRADO,
            'cerrado_por' => auth()->id(),
            'fecha_cierre' => now(),
        ]);
        session()->flash('ok', 'Ticket cerrado.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('tickets.eliminar'), 403);
        Ticket::findOrFail($id)->delete();
        session()->flash('ok', 'Ticket eliminado.');
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'tickets' => Ticket::query()
                ->with(['cliente', 'sede', 'sucursal', 'creadoPor'])
                ->when($this->buscar, fn ($q) => $q->where(fn ($w) => $w
                    ->where('ticket_atencion', 'like', '%'.$this->buscar.'%')
                    ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'like', '%'.$this->buscar.'%')->orWhere('nombre_comercial', 'like', '%'.$this->buscar.'%'))))
                ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
                ->orderByDesc('id')->paginate(10),
            'clientes' => Cliente::where('activo', true)->orderBy('razon_social')->get(),
            'sedes' => Sede::orderBy('nombre')->get(),
            'sucursalesCliente' => $this->cliente_id
                ? Sucursal::where('cliente_id', $this->cliente_id)->where('activo', true)->orderBy('nombre')->get()
                : collect(),
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
            <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por ticket o cliente…"
                   class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
        </div>
        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos</option>
            <option value="abierto">Abiertos</option>
            <option value="cerrado">Cerrados</option>
        </select>
        @can('tickets.crear')
            <button wire:click="nuevo" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">
                <x-icon name="plus" class="w-4 h-4" /> Nuevo ticket
            </button>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[760px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Ticket</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Ubicación</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $t)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-medium text-ink">{{ $t->ticket_atencion }}</div>
                            <div class="text-faint text-xs">IDTICKET #{{ $t->id }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $t->cliente?->nombre_comercial ?: $t->cliente?->razon_social }}</td>
                        <td class="px-4 py-3 text-muted">
                            <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5 text-faint" /> {{ $t->ubicacion_nombre }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($t->estado === 'abierto')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold"><span class="w-2 h-2 rounded-full bg-current"></span>Abierto</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-canvas text-muted border border-line px-2.5 py-0.5 text-xs font-semibold"><span class="w-2 h-2 rounded-full bg-current"></span>Cerrado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1 justify-end w-full">
                                @php $btn = 'inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-canvas transition-colors'; @endphp
                                @if ($t->estado === 'abierto')
                                    @can('tickets.cerrar')
                                        <button wire:click="cerrar({{ $t->id }})" wire:confirm="¿Cerrar este ticket? Los técnicos dejarán de verlo." class="{{ $btn }} text-warning" title="Cerrar ticket">
                                            <x-icon name="lock" />
                                        </button>
                                    @endcan
                                    @can('tickets.editar')
                                        <button wire:click="editar({{ $t->id }})" class="{{ $btn }} text-primary" title="Editar">
                                            <x-icon name="pencil" />
                                        </button>
                                    @endcan
                                @endif
                                @can('tickets.eliminar')
                                    <button wire:click="eliminar({{ $t->id }})" wire:confirm="¿Eliminar este ticket?" class="{{ $btn }} text-danger" title="Eliminar">
                                        <x-icon name="trash" />
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>

    @if ($mostrarForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $editandoId ? 'Editar ticket' : 'Nuevo ticket' }}</h3>
                    <button wire:click="$set('mostrarForm', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardar" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Ticket de atención * <span class="text-faint">(único)</span></label>
                        <input type="text" wire:model="ticket_atencion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('ticket_atencion') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Cliente *</label>
                        <select wire:model.live="cliente_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre_comercial ?: $c->razon_social }}</option>
                            @endforeach
                        </select>
                        @error('cliente_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">¿Dónde se atiende? *</label>
                        <select wire:model.live="ubicacion_tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="sucursal">Sucursal del cliente</option>
                            <option value="sede">Nuestra sede</option>
                        </select>
                    </div>

                    @if ($ubicacion_tipo === 'sucursal')
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Sucursal *</label>
                            <select wire:model="sucursal_id" @disabled(! $cliente_id) class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary disabled:bg-canvas disabled:text-faint">
                                <option value="">{{ $cliente_id ? '— Seleccionar —' : 'Elige un cliente primero' }}</option>
                                @foreach ($sucursalesCliente as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                            @error('sucursal_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nuestra sede *</label>
                            <select wire:model="sede_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                            @error('sede_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Descripción</label>
                        <textarea wire:model="descripcion" rows="2" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary"></textarea>
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
