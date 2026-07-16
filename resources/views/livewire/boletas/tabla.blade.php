<?php

use App\Models\BoletaPago;
use App\Models\Empleado;
use App\Services\SharePoint\RendicionArchivos;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $filtroPeriodo = ''; // YYYY-MM
    public ?int $filtroEmpleado = null;

    // Form subir boleta
    public ?int $empleado_id = null;
    public string $periodo = '';
    public string $tipo = 'Mensual';
    public $archivo = null;

    public function mount(): void
    {
        $this->periodo = now()->format('Y-m');
    }

    public function updatingFiltroPeriodo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEmpleado(): void
    {
        $this->resetPage();
    }

    public function subir(): void
    {
        abort_unless(auth()->user()->can('boletas.subir'), 403);
        $this->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'periodo' => ['required', 'date_format:Y-m'],
            'tipo' => ['required', 'in:'.implode(',', BoletaPago::TIPOS)],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ], [], ['empleado_id' => 'empleado', 'periodo' => 'periodo', 'archivo' => 'archivo']);

        $emp = Empleado::findOrFail($this->empleado_id);

        $boleta = new BoletaPago([
            'empleado_id' => $emp->id,
            'periodo' => $this->periodo.'-01',
            'tipo' => $this->tipo,
            'subido_por' => auth()->id(),
        ]);
        // Guardar temporal local; luego intento a SharePoint (guardar-temporal-y-reintentar).
        $boleta->archivo_nombre = 'Boleta_'.str_replace(' ', '_', $this->tipo).'_'.$this->periodo.'.pdf';
        $boleta->archivo_path = $this->archivo->store('boletas', 'public');
        $boleta->archivo_status = 'pendiente';
        $boleta->save();

        app(RendicionArchivos::class)->subir(
            $boleta, 'archivo', $this->carpetaEmpleado($emp).'/Boletas', $boleta->archivo_nombre, 'documentos',
        );

        $this->reset(['empleado_id', 'archivo']);
        session()->flash('ok', 'Boleta subida. El trabajador ya puede verla en su portal.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(auth()->user()->can('boletas.eliminar'), 403);
        $b = BoletaPago::findOrFail($id);

        if ($b->archivo_item_id) {
            try {
                app(SharePointDocs::class)->eliminar($b->archivo_item_id, 'documentos');
            } catch (\Throwable) {
                // mejor esfuerzo
            }
        }
        if ($b->archivo_path) {
            Storage::disk('public')->delete($b->archivo_path);
        }
        $b->delete();
        session()->flash('ok', 'Boleta eliminada.');
    }

    /** Misma convención de carpeta que el módulo Documentos: {DNI - Apellidos Nombres}. */
    private function carpetaEmpleado(Empleado $emp): string
    {
        return trim(($emp->numero_documento ?? 's-d').' - '.($emp->apellidos ?? '').' '.($emp->nombres ?? ''));
    }

    public function with(): array
    {
        return [
            'boletas' => BoletaPago::query()->with(['empleado', 'subidoPor'])
                ->when($this->filtroPeriodo, fn ($q) => $q->where('periodo', $this->filtroPeriodo.'-01'))
                ->when($this->filtroEmpleado, fn ($q) => $q->where('empleado_id', $this->filtroEmpleado))
                ->orderByDesc('periodo')->orderBy('id')
                ->paginate(12),
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
        ];
    }
}; ?>

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <p class="text-muted mb-5">Sube la boleta en PDF por trabajador y periodo. El trabajador la verá en "Mi espacio" y <strong>confirmará su recepción</strong>.</p>

    {{-- Subir boleta --}}
    @can('boletas.subir')
        <div class="bg-surface border border-line rounded-xl mb-5">
            <div class="px-4 py-3 border-b border-line flex items-center gap-2 text-navy font-semibold"><x-icon name="plus" class="w-4 h-4 text-primary" /> Subir boleta</div>
            <form wire:submit="subir" class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-xs text-muted mb-1 font-medium">Trabajador *</label>
                        <select wire:model="empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                            @endforeach
                        </select>
                        @error('empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-muted mb-1 font-medium">Periodo *</label>
                        <input type="month" wire:model="periodo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('periodo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-muted mb-1 font-medium">Tipo *</label>
                        <select wire:model="tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @foreach (\App\Models\BoletaPago::TIPOS as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-muted mb-1 font-medium">PDF de la boleta *</label>
                        <input type="file" wire:model="archivo" accept="application/pdf" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                        <div wire:loading wire:target="archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                        @error('archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Subir</button>
                    </div>
                </div>
                <p class="text-xs text-faint mt-3">Se archiva en SharePoint: Doc_Sistemas / {trabajador} / Boletas.</p>
            </form>
        </div>
    @endcan

    {{-- Lista --}}
    <div class="bg-surface border border-line rounded-xl">
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-line">
            <div>
                <label class="block text-xs text-muted mb-1">Periodo</label>
                <input type="month" wire:model.live="filtroPeriodo" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="block text-xs text-muted mb-1">Trabajador</label>
                <select wire:model.live="filtroEmpleado" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    <option value="">Todos</option>
                    @foreach ($empleados as $e)
                        <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Periodo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3">Recepción</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($boletas as $b)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $b->empleado?->apellidos }}, {{ $b->empleado?->nombres }}</td>
                            <td class="px-4 py-3 text-muted">{{ $b->periodo_label }}</td>
                            <td class="px-4 py-3 text-muted">{{ $b->tipo }}</td>
                            <td class="px-4 py-3">
                                @if ($b->archivo_web_url)
                                    <a href="{{ $b->archivo_web_url }}" target="_blank" class="text-primary hover:underline">Ver en SharePoint</a>
                                @elseif ($b->archivo_path)
                                    <a href="{{ Storage::url($b->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver <span class="text-warning text-xs">(pendiente SP)</span></a>
                                @else — @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($b->recibida_at)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-tint text-success px-2.5 py-0.5 text-xs font-semibold">Recibida · {{ $b->recibida_at->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-tint text-warning px-2.5 py-0.5 text-xs font-semibold">Sin confirmar</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('boletas.eliminar')
                                    <button wire:click="eliminar({{ $b->id }})" wire:confirm="¿Eliminar esta boleta?" class="text-danger text-xs font-semibold hover:underline">Eliminar</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-faint">No hay boletas con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $boletas->links() }}</div>
    </div>
</div>
