<?php

use App\Models\Descuento;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $filtroEstado = 'pendiente';

    public function marcarAplicado(int $id): void
    {
        abort_unless(auth()->user()->can('descuentos.aplicar'), 403);
        Descuento::whereKey($id)->update(['estado' => Descuento::APLICADO]);
        session()->flash('ok', 'Descuento marcado como aplicado.');
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Descuento::query()
            ->with(['empleado', 'activo'])
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->latest('id');

        return [
            'descuentos' => $query->paginate(15),
            'totalPendiente' => Descuento::where('estado', 'pendiente')->sum('monto'),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    {{-- Resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <div class="flex items-center gap-3 bg-surface border border-line rounded-xl p-4 border-l-4 border-l-warning">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-warning-tint text-warning shrink-0">
                <x-icon name="cash" class="w-6 h-6" />
            </span>
            <div>
                <div class="text-sm text-muted">Total pendiente de descontar</div>
                <div class="text-2xl font-bold text-ink tabular-nums leading-tight">S/ {{ number_format((float) $totalPendiente, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Filtro --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <select wire:model.live="filtroEstado" class="rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Todos</option>
            <option value="pendiente">Pendientes</option>
            <option value="aplicado">Aplicados</option>
        </select>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Motivo</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($descuentos as $d)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 font-medium text-ink">{{ $d->empleado?->apellidos }}, {{ $d->empleado?->nombres }}</td>
                        <td class="px-4 py-3 text-muted">{{ $d->motivo }}</td>
                        <td class="px-4 py-3 text-right text-ink font-semibold tabular-nums">S/ {{ number_format((float) $d->monto, 2) }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ $d->created_at?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            @if ($d->estado === 'pendiente')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-tint text-warning px-2.5 py-0.5 text-xs font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-current"></span>Pendiente
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-current"></span>Aplicado
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($d->estado === 'pendiente')
                                @can('descuentos.aplicar')
                                    <button wire:click="marcarAplicado({{ $d->id }})" wire:confirm="¿Marcar este descuento como aplicado en planilla?"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-line text-primary hover:bg-canvas text-sm font-medium px-3 py-1.5" title="Marcar como aplicado en planilla">
                                        <x-icon name="check" class="w-4 h-4" /> Marcar aplicado
                                    </button>
                                @else
                                    <span class="text-faint">—</span>
                                @endcan
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-faint">No hay descuentos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $descuentos->links() }}</div>
</div>
