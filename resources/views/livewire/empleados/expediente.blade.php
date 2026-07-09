<?php

use App\Models\Asignacion;
use App\Models\Derechohabiente;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\EntregaEpp;
use App\Models\HojaRuta;
use App\Models\TipoEpp;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public int $empleadoId;

    // Entrega de EPP
    public bool $mostrarEpp = false;
    public ?int $tipo_epp_id = null;
    public int $cantidad = 1;
    public string $talla = '';
    public string $eppObservacion = '';
    public string $firmaEpp = '';

    // Derechohabientes (familia)
    public bool $mostrarDh = false;
    public ?int $dhId = null;
    public string $dh_tipo = 'hijo';
    public string $dh_nombres = '';
    public string $dh_apellidos = '';
    public string $dh_tipo_documento = 'DNI';
    public string $dh_numero_documento = '';
    public string $dh_fecha_nacimiento = '';
    public string $dh_parentesco = '';
    public $dh_archivo = null;

    public function mount(Empleado $empleado): void
    {
        $this->empleadoId = $empleado->id;
    }

    public function abrirDh(?int $id = null): void
    {
        $this->reset(['dhId', 'dh_nombres', 'dh_apellidos', 'dh_numero_documento', 'dh_fecha_nacimiento', 'dh_parentesco', 'dh_archivo']);
        $this->dh_tipo = 'hijo';
        $this->dh_tipo_documento = 'DNI';
        $this->resetErrorBag();

        if ($id) {
            $dh = Derechohabiente::where('empleado_id', $this->empleadoId)->findOrFail($id);
            $this->dhId = $dh->id;
            $this->dh_tipo = $dh->tipo;
            $this->dh_nombres = $dh->nombres ?? '';
            $this->dh_apellidos = $dh->apellidos ?? '';
            $this->dh_tipo_documento = $dh->tipo_documento ?? 'DNI';
            $this->dh_numero_documento = $dh->numero_documento ?? '';
            $this->dh_fecha_nacimiento = optional($dh->fecha_nacimiento)->format('Y-m-d') ?? '';
            $this->dh_parentesco = $dh->parentesco ?? '';
        }

        $this->mostrarDh = true;
    }

    public function guardarDh(): void
    {
        $datos = $this->validate([
            'dh_tipo' => ['required', 'in:conyuge,conviviente,hijo,otro'],
            'dh_nombres' => ['required', 'string', 'max:120'],
            'dh_apellidos' => ['nullable', 'string', 'max:120'],
            'dh_tipo_documento' => ['required', 'string', 'max:20'],
            'dh_numero_documento' => ['nullable', 'string', 'max:20'],
            'dh_fecha_nacimiento' => ['nullable', 'date'],
            'dh_parentesco' => ['nullable', 'string', 'max:40'],
            'dh_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [], ['dh_nombres' => 'nombres', 'dh_archivo' => 'documento']);

        $dh = Derechohabiente::firstOrNew([
            'id' => $this->dhId,
            'empleado_id' => $this->empleadoId,
        ]);

        $dh->fill([
            'empleado_id' => $this->empleadoId,
            'tipo' => $datos['dh_tipo'],
            'nombres' => $datos['dh_nombres'],
            'apellidos' => $datos['dh_apellidos'] ?: null,
            'tipo_documento' => $datos['dh_tipo_documento'],
            'numero_documento' => $datos['dh_numero_documento'] ?: null,
            'fecha_nacimiento' => $datos['dh_fecha_nacimiento'] ?: null,
            'parentesco' => $datos['dh_parentesco'] ?: null,
        ]);

        if ($this->dh_archivo) {
            if ($dh->archivo_path) {
                Storage::disk('public')->delete($dh->archivo_path);
            }
            $dh->archivo_path = $this->dh_archivo->store('derechohabientes', 'public');
            $dh->archivo_nombre = $this->dh_archivo->getClientOriginalName();
        }

        $dh->save();

        $this->mostrarDh = false;
        session()->flash('ok', 'Derechohabiente guardado.');
    }

    public function eliminarDh(int $id): void
    {
        $dh = Derechohabiente::where('empleado_id', $this->empleadoId)->findOrFail($id);
        if ($dh->archivo_path) {
            Storage::disk('public')->delete($dh->archivo_path);
        }
        $dh->delete();
        session()->flash('ok', 'Derechohabiente eliminado.');
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
            'hojasRuta' => HojaRuta::where('empleado_id', $this->empleadoId)
                ->orderByDesc('fecha')->orderByDesc('id')->get(),
            'derechohabientes' => Derechohabiente::where('empleado_id', $this->empleadoId)
                ->orderBy('tipo')->orderBy('nombres')->get(),
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
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $empleado->situacion === 'activo' ? 'bg-white/15' : 'bg-danger/30' }}">
                    {{ ucfirst($empleado->situacion) }}
                </span>
                <a href="{{ route('empleados.hoja-ruta', $empleado) }}" wire:navigate
                   class="rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm font-semibold px-4 py-2">
                    Generar hoja de ruta
                </a>
            </div>
        </div>
    </div>

    {{-- Pestañas --}}
    @php $tabBtn = 'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors'; @endphp
    <div class="border-b border-line mb-5 flex flex-wrap gap-1">
        <button @click="tab='datos'" :class="tab==='datos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Datos</button>
        <button @click="tab='documentos'" :class="tab==='documentos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Documentos ({{ $documentos->count() }})</button>
        <button @click="tab='familia'" :class="tab==='familia' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Familia ({{ $derechohabientes->count() }})</button>
        <button @click="tab='activos'" :class="tab==='activos' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Activos ({{ $asignaciones->whereNull('fecha_devolucion')->count() }})</button>
        <button @click="tab='epp'" :class="tab==='epp' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">EPP ({{ $entregasEpp->count() }})</button>
        <button @click="tab='hojas'" :class="tab==='hojas' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" class="{{ $tabBtn }}">Hojas de ruta ({{ $hojasRuta->count() }})</button>
    </div>

    {{-- DATOS --}}
    <section x-show="tab==='datos'" class="bg-surface border border-line rounded-xl p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4 text-sm">
            @php
                $emerg = collect([$empleado->emergencia_nombre, $empleado->emergencia_parentesco, $empleado->emergencia_telefono])->filter()->implode(' · ');
                $campos = [
                    'Fecha de nacimiento' => optional($empleado->fecha_nacimiento)->format('d/m/Y'),
                    'Sexo' => $empleado->sexo === 'M' ? 'Masculino' : ($empleado->sexo === 'F' ? 'Femenino' : null),
                    'Estado civil' => $empleado->estado_civil,
                    'Nacionalidad' => $empleado->nacionalidad,
                    'Teléfono' => $empleado->telefono,
                    'Correo' => $empleado->correo,
                    'Dirección' => $empleado->direccion,
                    'Contacto de emergencia' => $emerg ?: null,
                    'Sede' => $empleado->sede?->nombre,
                    'Supervisor' => $empleado->supervisor ? $empleado->supervisor->apellidos.', '.$empleado->supervisor->nombres : null,
                    'Fecha de ingreso' => optional($empleado->fecha_ingreso)->format('d/m/Y'),
                    'Tipo de contrato' => $empleado->tipo_contrato,
                    'Tipo de trabajador' => $empleado->tipo_trabajador,
                    'Régimen laboral' => $empleado->regimen_laboral,
                    'Modalidad de pago' => match ($empleado->modalidad_pago) {
                        'planilla' => 'Planilla (5ta)',
                        'honorarios' => 'Recibos por honorarios (4ta)',
                        default => null,
                    },
                    'Sistema pensionario' => $empleado->sistema_pensionario,
                    'AFP' => $empleado->afp_nombre,
                    'CUSPP' => $empleado->cuspp,
                    'Régimen de salud' => $empleado->regimen_salud,
                    'Estado de seguro' => $empleado->tiene_seguro === null ? null : ($empleado->tiene_seguro ? 'Con seguro' : 'Falta de seguro'),
                    'Cantidad de hijos' => (string) $empleado->cantidad_hijos,
                    'Banco' => $empleado->banco,
                    'N° de cuenta' => $empleado->numero_cuenta,
                    'CCI' => $empleado->cci,
                    'Fecha de cese' => optional($empleado->fecha_cese)->format('d/m/Y'),
                ];
                if (auth()->user()->hasAnyRole(['RRHH', 'Gerencia', 'Contador'])) {
                    $campos['Sueldo'] = $empleado->sueldo ? 'S/ '.number_format((float) $empleado->sueldo, 2) : null;
                }
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

    {{-- FAMILIA (derechohabientes) --}}
    <section x-show="tab==='familia'" x-cloak>
        <div class="flex justify-end mb-3">
            <button wire:click="abrirDh" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">+ Agregar familiar</button>
        </div>
        <div class="bg-surface border border-line rounded-xl overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Vínculo</th>
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">F. nacimiento</th>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($derechohabientes as $dh)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3 text-ink">{{ $dh->nombre_completo }}</td>
                            <td class="px-4 py-3 text-muted">{{ $dh->tipo_label }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $dh->numero_documento ? $dh->tipo_documento.' '.$dh->numero_documento : '—' }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ optional($dh->fecha_nacimiento)->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($dh->archivo_path)
                                    <a href="{{ Storage::url($dh->archivo_path) }}" target="_blank" class="text-primary hover:underline">Ver</a>
                                @else <span class="text-faint">—</span> @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="abrirDh({{ $dh->id }})" class="text-primary hover:underline text-sm font-medium">Editar</button>
                                <button wire:click="eliminarDh({{ $dh->id }})" wire:confirm="¿Eliminar a {{ $dh->nombres }}?" class="ml-3 text-danger hover:underline text-sm font-medium">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-faint">Sin derechohabientes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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

    {{-- HOJAS DE RUTA --}}
    <section x-show="tab==='hojas'" x-cloak class="bg-surface border border-line rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[520px]">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Motivo</th>
                    <th class="px-4 py-3 text-right">Total descontado</th>
                    <th class="px-4 py-3">PDF</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hojasRuta as $h)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-4 py-3 text-muted tabular-nums">{{ optional($h->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-ink capitalize">{{ $h->motivo }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">S/ {{ number_format((float) $h->total_descuento, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($h->pdf_path)
                                <a href="{{ Storage::url($h->pdf_path) }}" target="_blank" class="text-primary hover:underline">Descargar</a>
                            @else <span class="text-faint">—</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-faint">Sin hojas de ruta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- Modal: Derechohabiente --}}
    @if ($mostrarDh)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-10 mb-10 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $dhId ? 'Editar familiar' : 'Agregar familiar' }}</h3>
                    <button wire:click="$set('mostrarDh', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="guardarDh" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Vínculo *</label>
                            <select wire:model="dh_tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="conyuge">Cónyuge</option>
                                <option value="conviviente">Conviviente</option>
                                <option value="hijo">Hijo(a)</option>
                                <option value="otro">Otro</option>
                            </select>
                            @error('dh_tipo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Parentesco (detalle)</label>
                            <input type="text" wire:model="dh_parentesco" placeholder="Ej. hija, padre…" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Nombres *</label>
                            <input type="text" wire:model="dh_nombres" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('dh_nombres') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Apellidos</label>
                            <input type="text" wire:model="dh_apellidos" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Tipo doc.</label>
                            <select wire:model="dh_tipo_documento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                <option value="DNI">DNI</option>
                                <option value="CE">Carné de Extranjería</option>
                                <option value="PAS">Pasaporte</option>
                                <option value="PARTIDA">Partida de nacimiento</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">N° documento</label>
                            <input type="text" wire:model="dh_numero_documento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Fecha de nacimiento</label>
                            <input type="date" wire:model="dh_fecha_nacimiento" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Documento (PDF/imagen)</label>
                            <input type="file" wire:model="dh_archivo" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-tint file:px-3 file:py-1.5 file:text-primary file:font-semibold">
                            <div wire:loading wire:target="dh_archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                            @error('dh_archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('mostrarDh', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
