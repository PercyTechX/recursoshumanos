<?php

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Descuento;
use App\Models\Empleado;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component {
    public int $empleadoId;
    public string $empleadoNombre = '';
    public string $motivo = 'cese';
    public array $items = [];
    public string $firma = '';

    public function mount(Empleado $empleado): void
    {
        $this->empleadoId = $empleado->id;
        $this->empleadoNombre = $empleado->apellidos.', '.$empleado->nombres;

        $this->items = $empleado->asignacionesActivas()->with('activo')->get()
            ->map(fn (Asignacion $a) => [
                'asignacion_id' => $a->id,
                'activo_id' => $a->activo_id,
                'nombre' => $a->activo?->nombre,
                'codigo' => $a->activo?->codigo,
                'costo' => (float) ($a->activo?->costo ?? 0),
                'devuelto' => true,
                'monto' => 0,
            ])->values()->toArray();
    }

    // Al marcar "no devuelto", el monto propone el costo del activo
    public function updated(string $name): void
    {
        if (preg_match('/^items\.(\d+)\.devuelto$/', $name, $m)) {
            $i = (int) $m[1];
            $this->items[$i]['monto'] = $this->items[$i]['devuelto'] ? 0 : $this->items[$i]['costo'];
        }
    }

    public function getTotalProperty(): float
    {
        return collect($this->items)
            ->reject(fn ($it) => $it['devuelto'])
            ->sum(fn ($it) => (float) $it['monto']);
    }

    private function guardarFirma(string $dataUrl, string $carpeta): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }
        $base64 = explode(',', $dataUrl, 2)[1] ?? '';
        $path = $carpeta.'/'.uniqid('firma_').'.png';
        Storage::disk('public')->put($path, base64_decode($base64));

        return $path;
    }

    public function generar()
    {
        $this->validate([
            'motivo' => ['required', 'in:cese,perdida,otro'],
            'firma' => ['required', 'string'],
        ], [], ['firma' => 'firma']);

        $hoja = HojaRuta::create([
            'empleado_id' => $this->empleadoId,
            'motivo' => $this->motivo,
            'fecha' => now()->toDateString(),
            'firma_path' => $this->guardarFirma($this->firma, 'firmas'),
            'total_descuento' => $this->total,
            'generado_por' => auth()->id(),
        ]);

        foreach ($this->items as $it) {
            $devuelto = (bool) $it['devuelto'];
            $estado = $devuelto ? 'bueno' : 'perdido';

            Asignacion::whereKey($it['asignacion_id'])->update([
                'fecha_devolucion' => now()->toDateString(),
                'estado_devolucion' => $estado,
                'recibido_por' => auth()->id(),
                'hoja_ruta_id' => $hoja->id,
            ]);

            Activo::whereKey($it['activo_id'])->update([
                'estado' => $devuelto ? Activo::DISPONIBLE : Activo::PERDIDO,
            ]);

            $monto = $devuelto ? 0 : (float) $it['monto'];

            HojaRutaItem::create([
                'hoja_ruta_id' => $hoja->id,
                'activo_id' => $it['activo_id'],
                'asignacion_id' => $it['asignacion_id'],
                'devuelto' => $devuelto,
                'estado_devolucion' => $estado,
                'monto_descuento' => $monto,
            ]);

            if (! $devuelto && $monto > 0) {
                Descuento::create([
                    'empleado_id' => $this->empleadoId,
                    'hoja_ruta_id' => $hoja->id,
                    'activo_id' => $it['activo_id'],
                    'monto' => $monto,
                    'motivo' => 'Activo no devuelto: '.$it['nombre'],
                    'estado' => Descuento::PENDIENTE,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        session()->flash('ok', 'Hoja de ruta generada. Los descuentos quedaron registrados para el Contador.');

        return $this->redirectRoute('empleados.show', ['empleado' => $this->empleadoId], navigate: true);
    }
}; ?>

<div>
    <div class="bg-surface border border-line rounded-xl p-6">
        <h3 class="text-lg font-semibold text-navy">Hoja de ruta — {{ $empleadoNombre }}</h3>
        <p class="text-sm text-muted mt-1">
            Revisa los activos que el trabajador tiene en su poder. Marca cuáles devuelve; los
            <strong>no devueltos</strong> generan un descuento (puedes ajustar o poner el monto en 0).
        </p>

        <div class="mt-4 max-w-xs">
            <label class="block text-sm font-medium text-muted mb-1">Motivo</label>
            <select wire:model="motivo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                <option value="cese">Cese del trabajador</option>
                <option value="perdida">Pérdida</option>
                <option value="otro">Otro</option>
            </select>
        </div>

        <div class="mt-5 overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm min-w-[620px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Activo</th>
                        <th class="px-4 py-3 text-right">Costo</th>
                        <th class="px-4 py-3 text-center">¿Devuelto?</th>
                        <th class="px-4 py-3 text-right">Monto a descontar (S/)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $i => $it)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $it['nombre'] }} @if ($it['codigo'])<span class="text-faint">· {{ $it['codigo'] }}</span>@endif</td>
                            <td class="px-4 py-3 text-right text-muted tabular-nums">S/ {{ number_format($it['costo'], 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" wire:model.live="items.{{ $i }}.devuelto" class="rounded border-line text-primary focus:ring-primary">
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if (! $it['devuelto'])
                                    <input type="number" step="0.01" min="0" wire:model="items.{{ $i }}.monto"
                                           class="w-28 rounded-lg border-line text-sm text-right focus:border-primary focus:ring-primary">
                                @else
                                    <span class="text-faint">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Este trabajador no tiene activos por devolver.</td></tr>
                    @endforelse
                </tbody>
                @if (count($items))
                    <tfoot>
                        <tr class="bg-canvas border-t border-line">
                            <td colspan="3" class="px-4 py-3 text-right font-semibold text-ink">Total a descontar</td>
                            <td class="px-4 py-3 text-right font-bold text-danger tabular-nums">S/ {{ number_format($this->total, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- Firma --}}
        <div class="mt-5 max-w-md">
            <label class="block text-sm font-medium text-muted mb-1">Firma del trabajador (autoriza el descuento) *</label>
            <x-firma model="firma" />
            @error('firma') <span class="text-danger text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('empleados.show', $empleadoId) }}" wire:navigate
               class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</a>
            <button wire:click="generar" @if (! count($items)) disabled @endif
                    class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2 disabled:opacity-50">
                Generar hoja de ruta
            </button>
        </div>
    </div>
</div>
