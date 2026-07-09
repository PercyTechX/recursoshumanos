<?php

use App\Models\Asignacion;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\EntregaEpp;
use App\Models\TipoEpp;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component {
    public int $empleadoId;

    // Entrega de EPP
    public bool $mostrarEpp = false;
    public ?int $tipo_epp_id = null;
    public int $cantidad = 1;
    public string $talla = '';
    public string $eppObservacion = '';
    public string $firmaEpp = '';

    public function mount(Empleado $empleado): void
    {
        $this->empleadoId = $empleado->id;
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

    public function abrirEpp(): void
    {
        $this->reset(['tipo_epp_id', 'cantidad', 'talla', 'eppObservacion', 'firmaEpp']);
        $this->cantidad = 1;
        $this->resetErrorBag();
        $this->mostrarEpp = true;
    }

    public function entregarEpp(): void
    {
        $this->validate([
            'tipo_epp_id' => ['required', 'exists:tipos_epp,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'talla' => ['nullable', 'string', 'max:20'],
            'firmaEpp' => ['required', 'string'],
        ], [], ['tipo_epp_id' => 'tipo de EPP', 'firmaEpp' => 'firma']);

        EntregaEpp::create([
            'empleado_id' => $this->empleadoId,
            'tipo_epp_id' => $this->tipo_epp_id,
            'cantidad' => $this->cantidad,
            'talla' => $this->talla ?: null,
            'fecha' => now()->toDateString(),
            'firma_path' => $this->guardarFirma($this->firmaEpp, 'firmas'),
            'entregado_por' => auth()->id(),
            'observacion' => $this->eppObservacion ?: null,
        ]);

        $this->mostrarEpp = false;
        session()->flash('ok', 'Entrega de EPP registrada.');
    }

    public function with(): array
    {
        $empleado = Empleado::with(['area', 'cargo', 'sede', 'supervisor'])->findOrFail($this->empleadoId);

        return [
            'empleado' => $empleado,
            'documentos' => Documento::with('tipoDocumento')
                ->where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_vencimiento')->get(),
            'asignaciones' => Asignacion::with('activo')
                ->where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha_entrega')->orderByDesc('id')->get(),
            'entregasEpp' => EntregaEpp::with('tipoEpp')
                ->where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha')->orderByDesc('id')->get(),
            'tiposEpp' => TipoEpp::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div x-data="{ tab: 'datos' }">
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    {{-- Cabecera del empleado --}}
    <div class="bg-gradient-to-br from-navy via-primary-dark to-primary rounded-2xl shadow-lg p-6 text-white mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-2xl font-semibold tracking-tight">{{ $empleado->apellidos }}, {{ $empleado->nombres }}</h3>
                <p class="text-white/85 text-sm mt-0.5">
                    {{ $empleado->cargo?->nombre ?? 'Sin cargo' }} · {{ $empleado->area?->nombre ?? 'Sin área' }}
                    · {{ $empleado->tipo_documento }} {{ $empleado->numero_documento }}
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $empleado->situacion === 'activo' ? 'bg-white/15' : 'bg-danger/30' }}">
                {{ ucfirst($empleado->situacion) }}
            </span>
        </div>
    </div>

    {{-- Pestañas --}}
    @php $tabBtn = 'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors'; @endphp
    <div class="border-b border-line mb-5 flex flex-wrap gap-1">
        <button @click="tab='datos'" :class="tab==='datos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Datos</button>
        <button @click="tab='documentos'" :class="tab==='documentos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Documentos ({{ $documentos->count() }})</button>
        <button @click="tab='activos'" :class="tab==='activos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Activos ({{ $asignaciones->whereNull('fecha_devolucion')->count() }})</button>
        <button @click="tab='epp'" :class="tab==='epp' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">EPP ({{ $entregasEpp->count() }})</button>
    </div>

    {{-- DATOS --}}
    <section x-show="tab==='datos'" class="bg-surface border border-line rounded-xl p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4 text-sm">
            @php
                $campos = [
                    'Fecha de nacimiento' => optional($empleado->fecha_nacimiento)->format('d/m/Y'),
                    'Nacionalidad' => $empleado->nacionalidad,
                    'Teléfono' => $empleado->telefono,
                    'Correo' => $empleado->correo,
                    'Dirección' => $empleado->direccion,
                    'Sede' => $empleado->sede?->nombre,
                    'Supervisor' => $empleado->supervisor ? $empleado->supervisor->apellidos.', '.$empleado->supervisor->nombres : null,
                    'Fecha de ingreso' => optional($empleado->fecha_ingreso)->format('d/m/Y'),
                    'Tipo de contrato' => $empleado->tipo_contrato,
                    'Sistema pensionario' => $empleado->sistema_pensionario,
                    'Régimen de salud' => $empleado->regimen_salud,
                    'Banco' => $empleado->banco,
                    'N° de cuenta' => $empleado->numero_cuenta,
                ];
            @endphp
            @foreach ($campos as $label => $valor)
                <div>
                    <dt class="text-faint text-xs uppercase tracking-wide">{{ $label }}</dt>
                    <dd class="text-ink mt-0.5">{{ $valor ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- DOCUMENTOS --}}
    <section x-show="tab==='documentos'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Emisión</th>
                    <th class="px-4 py-3">Vencimiento</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Archivo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $d)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $d->tipoDocumento?->nombre }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($d->fecha_emision)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($d->fecha_vencimiento)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                [$c, $t] = match ($d->estado) {
                                    'vigente' => ['bg-success-tint text-success', 'Vigente'],
                                    'por_vencer' => ['bg-warning-tint text-warning', 'Por vencer'],
                                    'vencido' => ['bg-danger-tint text-danger', 'Vencido'],
                                    default => ['bg-canvas text-faint', 'Sin vigencia'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>{{ $t }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($d->archivo_path)
                                <a href="{{ Storage::url($d->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                            @else <span class="text-faint">—</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin documentos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- ACTIVOS --}}
    <section x-show="tab==='activos'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Activo</th>
                    <th class="px-4 py-3">Entregado</th>
                    <th class="px-4 py-3">Devuelto</th>
                    <th class="px-4 py-3">Situación</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($asignaciones as $a)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-ink">{{ $a->activo?->nombre }} @if ($a->activo?->codigo)<span class="text-faint">· {{ $a->activo->codigo }}</span>@endif</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($a->fecha_entrega)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($a->fecha_devolucion)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($a->esta_activa)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-tint text-primary px-2.5 py-0.5 text-xs font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-current"></span>En su poder
                                </span>
                            @else
                                <span class="text-muted capitalize">Devuelto{{ $a->estado_devolucion ? ' · '.$a->estado_devolucion : '' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Sin activos asignados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- EPP --}}
    <section x-show="tab==='epp'" x-cloak>
        <div class="flex justify-end mb-3">
            <button wire:click="abrirEpp" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">+ Entregar EPP</button>
        </div>
        <div class="bg-surface border border-line rounded-xl overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">EPP</th>
                        <th class="px-4 py-3">Cantidad</th>
                        <th class="px-4 py-3">Talla</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Firma</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entregasEpp as $e)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $e->tipoEpp?->nombre }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $e->cantidad }}</td>
                            <td class="px-4 py-3 text-muted">{{ $e->talla ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ optional($e->fecha)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($e->firma_path)
                                    <a href="{{ Storage::url($e->firma_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                                @else <span class="text-faint">—</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-faint">Sin entregas de EPP.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Modal: Entregar EPP --}}
    @if ($mostrarEpp)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Entregar EPP</h3>
                    <button wire:click="$set('mostrarEpp', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="entregarEpp" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Tipo de EPP *</label>
                            <select wire:model="tipo_epp_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="">— Seleccionar —</option>
                                @foreach ($tiposEpp as $t)
                                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                @endforeach
                            </select>
                            @error('tipo_epp_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Cantidad *</label>
                            <input type="number" min="1" wire:model="cantidad" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('cantidad') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Talla</label>
                            <input type="text" wire:model="talla" placeholder="S / M / 42…" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-muted mb-1">Observación</label>
                            <input type="text" wire:model="eppObservacion" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Firma de recepción *</label>
                        <x-firma model="firmaEpp" />
                        @error('firmaEpp') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarEpp', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Registrar entrega</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
