<?php

use App\Models\RendicionDeposito;
use App\Models\RendicionGasto;
use App\Models\RendicionLiquidacion;
use App\Services\SharePoint\RendicionArchivos;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $depositoId;

    // Comprobante de gasto
    public string $c_tipo = 'Boleta';
    public string $c_nro = '';
    public string $c_monto = '';
    public string $c_fecha = '';
    public $c_archivo = null;

    // Liquidación
    public $voucherDevolucion = null;

    public function mount(RendicionDeposito $deposito): void
    {
        $this->depositoId = $deposito->id;
        $this->c_fecha = now()->toDateString();
    }

    private function deposito(): RendicionDeposito
    {
        return RendicionDeposito::with(['ticket.cliente', 'gastos', 'liquidacion'])->findOrFail($this->depositoId);
    }

    public function agregarComprobante(): void
    {
        $dep = $this->deposito();
        abort_unless($dep->editable_por_tecnico, 403);

        $this->validate([
            'c_tipo' => ['required', Rule::in(RendicionGasto::TIPOS)],
            'c_nro' => ['nullable', 'string', 'max:60'],
            'c_monto' => ['required', 'numeric', 'min:0.01'],
            'c_fecha' => ['required', 'date'],
            'c_archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [], ['c_tipo' => 'tipo', 'c_monto' => 'monto', 'c_fecha' => 'fecha', 'c_archivo' => 'archivo']);

        $g = new RendicionGasto([
            'deposito_id' => $dep->id,
            'tipo_comprobante' => $this->c_tipo,
            'nro_comprobante' => $this->c_nro ?: 'S/N',
            'monto_gasto' => $this->c_monto,
            'fecha_comprobante' => $this->c_fecha,
        ]);
        // Fase C: archivo local; subida a SharePoint es la Fase D.
        $g->archivo_nombre = $this->c_archivo->getClientOriginalName();
        $g->archivo_path = $this->c_archivo->store('rendiciones/comprobantes', 'public');
        $g->archivo_status = 'pendiente';
        $g->save();

        app(RendicionArchivos::class)->subir($g, 'archivo', $dep->carpetaSharePoint());

        $this->reset(['c_nro', 'c_monto', 'c_archivo']);
        $this->c_tipo = 'Boleta';
        $this->c_fecha = now()->toDateString();
        session()->flash('ok', 'Comprobante agregado.');
    }

    public function eliminarComprobante(int $id): void
    {
        $dep = $this->deposito();
        abort_unless($dep->editable_por_tecnico, 403);

        $g = RendicionGasto::where('deposito_id', $dep->id)->findOrFail($id);
        if ($g->archivo_path) {
            Storage::disk('public')->delete($g->archivo_path);
        }
        $g->delete();
    }

    public function liquidar(): void
    {
        $dep = $this->deposito();
        abort_unless($dep->editable_por_tecnico, 403);

        $totalGastado = round((float) $dep->gastos->sum('monto_gasto'), 2);
        $diferencia = round((float) $dep->monto - $totalGastado, 2);
        $tipo = RendicionLiquidacion::tipoPorDiferencia($diferencia);

        if ($tipo === RendicionDeposito::LIQ_DEVOLUCION) {
            $this->validate(
                ['voucherDevolucion' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
                [], ['voucherDevolucion' => 'voucher de devolución']
            );
        }

        $liq = RendicionLiquidacion::updateOrCreate(
            ['deposito_id' => $dep->id],
            ['monto_depositado' => $dep->monto, 'total_gastado' => $totalGastado, 'diferencia' => $diferencia, 'estado_liquidacion' => $tipo],
        );

        if ($tipo === RendicionDeposito::LIQ_DEVOLUCION && $this->voucherDevolucion) {
            $liq->comprobante_nombre = $this->voucherDevolucion->getClientOriginalName();
            $liq->comprobante_path = $this->voucherDevolucion->store('rendiciones/liquidacion', 'public');
            $liq->comprobante_status = 'pendiente';
            $liq->save();
            app(RendicionArchivos::class)->subir($liq, 'comprobante', $dep->carpetaSharePoint());
        }

        $dep->transicionar('liquidar');
        $dep->fecha_rendido = now()->toDateString();
        $dep->save();

        $this->reset(['voucherDevolucion']);
        session()->flash('ok', 'Rendición enviada para revisión. ¡Gracias!');
    }

    public function with(): array
    {
        $dep = $this->deposito();
        $totalGastado = round((float) $dep->gastos->sum('monto_gasto'), 2);
        $diferencia = round((float) $dep->monto - $totalGastado, 2);

        return [
            'dep' => $dep,
            'totalGastado' => $totalGastado,
            'diferencia' => $diferencia,
            'tipoLiq' => RendicionLiquidacion::tipoPorDiferencia($diferencia),
            'cuentas' => config('rendiciones.cuentas'),
        ];
    }
}; ?>

@php
    $money = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $badge = [
        'Rindiendo' => 'bg-primary-tint text-primary',
        'Por Revisar' => 'bg-warning-tint text-warning',
        'Finalizado' => 'bg-success-tint text-success',
        'Observado' => 'bg-danger-tint text-danger',
        'Anulado' => 'bg-canvas text-faint',
    ];
@endphp

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-3 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    {{-- Cabecera --}}
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-navy">Hola, {{ $dep->tecnico_nombre }}</h2>
        <p class="text-muted text-sm">
            @if ($dep->editable_por_tecnico)
                Ingresa los comprobantes correspondientes a este depósito.
            @else
                Estado de tu rendición.
            @endif
        </p>
    </div>

    {{-- Banner Observado --}}
    @if ($dep->estado === 'Observado' && $dep->observaciones)
        <div class="mb-5 rounded-xl bg-danger-tint text-danger px-4 py-3 text-sm">
            <strong>Tu rendición fue observada.</strong> Motivo: {{ $dep->observaciones }} — corrígela y vuelve a enviar.
        </div>
    @endif

    {{-- Estados cerrados --}}
    @unless ($dep->editable_por_tecnico)
        <div class="mb-5 rounded-xl border border-line bg-surface p-6 text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold {{ $badge[$dep->estado] ?? '' }}"><span class="w-2 h-2 rounded-full bg-current"></span>{{ $dep->estado }}</span>
            <p class="text-muted text-sm mt-3">
                @switch($dep->estado)
                    @case('Por Revisar') Tu rendición fue enviada y está en revisión por tu supervisor. @break
                    @case('Finalizado') Tu rendición fue aprobada y cerrada. @break
                    @case('Anulado') Este depósito fue anulado. @break
                @endswitch
            </p>
        </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Datos del depósito --}}
        <div class="bg-surface border border-line rounded-xl p-5">
            <h3 class="font-semibold text-navy border-b border-line pb-2 mb-3">Datos del depósito</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-faint">Ticket trabajo</dt><dd class="font-semibold text-ink tabular-nums">{{ $dep->ticket?->ticket_atencion }}</dd></div>
                <div class="flex justify-between"><dt class="text-faint">Local de trabajo</dt><dd class="text-ink text-right">{{ $dep->local_nombre }}</dd></div>
                <div class="flex justify-between"><dt class="text-faint">Fecha recibido</dt><dd class="text-ink tabular-nums">{{ $dep->dia?->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-faint">Monto entregado</dt><dd class="font-bold text-primary tabular-nums">{{ $money($dep->monto) }}</dd></div>
            </dl>
            <div class="mt-4 pt-3 border-t border-line flex justify-between text-sm">
                <span class="text-faint">Total gastos</span><span class="font-semibold tabular-nums">{{ $money($totalGastado) }}</span>
            </div>
        </div>

        {{-- Balance / liquidar --}}
        <div class="bg-surface border border-line rounded-xl p-5 border-l-4 {{ $tipoLiq === 'Reembolso' ? 'border-l-danger' : ($tipoLiq === 'Devolucion' ? 'border-l-warning' : 'border-l-success') }}">
            <h3 class="font-semibold text-navy mb-3">Balance de rendición</h3>
            <div class="text-center py-2">
                <div class="text-xs uppercase tracking-wide text-faint">Diferencia calculada</div>
                <div class="text-3xl font-extrabold tabular-nums {{ $tipoLiq === 'Reembolso' ? 'text-danger' : ($tipoLiq === 'Devolucion' ? 'text-warning' : 'text-success') }}">{{ $money(abs($diferencia)) }}</div>
                <div class="text-sm text-muted mt-1">
                    @switch($tipoLiq)
                        @case('Devolucion') Te sobró dinero (debes devolver el vuelto) @break
                        @case('Reembolso') Gastaste de más (se te reembolsará) @break
                        @default Balance exacto (sin vuelto ni reembolso)
                    @endswitch
                </div>
            </div>

            @if ($dep->editable_por_tecnico)
                {{-- Instrucciones de devolución --}}
                @if ($tipoLiq === 'Devolucion')
                    <div class="rounded-lg bg-warning-tint/60 border border-warning/30 p-3 text-xs text-ink mb-3">
                        Deposita el saldo sobrante a la <strong>misma cuenta desde la que recibiste el dinero</strong>: si fue la empresa, a la cuenta de la empresa; si te lo depositó tu <strong>supervisor</strong>, devuélveselo a él. Luego adjunta el voucher.
                        <div class="mt-2 space-y-1">
                            @foreach ($cuentas as $cta)
                                <div class="flex items-center justify-between bg-surface rounded px-2 py-1">
                                    <span><strong>{{ $cta['banco'] }}</strong> <span class="tabular-nums">{{ $cta['numero'] }}</span></span>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $cta['numero'] }}'); this.textContent='Copiado'" class="text-primary text-[11px] font-semibold">Copiar</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-muted mb-1 font-medium">Voucher de la devolución *</label>
                        <input type="file" wire:model="voucherDevolucion" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                        <div wire:loading wire:target="voucherDevolucion" class="text-xs text-faint mt-1">Subiendo…</div>
                        @error('voucherDevolucion') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                @elseif ($tipoLiq === 'Reembolso')
                    <div class="rounded-lg bg-danger-tint/50 border border-danger/20 p-3 text-xs text-ink mb-3">
                        Al aprobar tu rendición, tu supervisor registrará el <strong>reembolso</strong> de {{ $money(abs($diferencia)) }} y adjuntará el comprobante.
                    </div>
                @endif

                <button wire:click="liquidar"
                        wire:confirm="¿Enviar la rendición para revisión? Ya no podrás editar los comprobantes."
                        class="w-full rounded-lg text-white text-sm font-semibold px-4 py-2.5 {{ $tipoLiq === 'Reembolso' ? 'bg-danger hover:brightness-95' : ($tipoLiq === 'Devolucion' ? 'bg-warning hover:brightness-95' : 'bg-success hover:brightness-95') }}">
                    Enviar rendición
                    @if ($tipoLiq === 'Devolucion') (con vuelto) @elseif ($tipoLiq === 'Reembolso') (con reembolso) @endif
                </button>
            @endif
        </div>
    </div>

    @if ($dep->editable_por_tecnico)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
            {{-- Agregar comprobante --}}
            <div class="bg-surface border border-line rounded-xl p-5">
                <h3 class="font-semibold text-navy mb-3">Agregar comprobante de gasto</h3>
                <form wire:submit="agregarComprobante" class="space-y-3">
                    <div>
                        <label class="block text-xs text-muted mb-1 font-medium">Tipo de comprobante</label>
                        <select wire:model="c_tipo" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @foreach (\App\Models\RendicionGasto::TIPOS as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-muted mb-1 font-medium">Número de comprobante / documento</label>
                        <input type="text" wire:model="c_nro" placeholder="Ej. F001-000412 (o S/N)" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-muted mb-1 font-medium">Monto gastado (S/)</label>
                            <input type="number" step="0.01" min="0" wire:model="c_monto" placeholder="0.00" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary tabular-nums">
                            @error('c_monto') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1 font-medium">Fecha de gasto</label>
                            <input type="date" wire:model="c_fecha" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-muted mb-1 font-medium">Foto o PDF del comprobante *</label>
                        <input type="file" wire:model="c_archivo" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                        <div wire:loading wire:target="c_archivo" class="text-xs text-faint mt-1">Subiendo…</div>
                        @error('c_archivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2.5">Agregar comprobante</button>
                </form>
            </div>

            {{-- Comprobantes agregados --}}
            <div class="bg-surface border border-line rounded-xl p-5">
                <h3 class="font-semibold text-navy mb-3">Comprobantes agregados ({{ $dep->gastos->count() }})</h3>
                @forelse ($dep->gastos as $g)
                    <div class="flex items-center justify-between border-b border-line py-2 last:border-0">
                        <div>
                            <div class="text-sm text-ink">{{ $g->tipo_comprobante }} <span class="text-faint">{{ $g->nro_comprobante }}</span></div>
                            <div class="text-xs text-faint tabular-nums">{{ $g->fecha_comprobante?->format('d/m/Y') }} · {{ $g->archivo_nombre }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-sm tabular-nums">{{ $money($g->monto_gasto) }}</span>
                            <button wire:click="eliminarComprobante({{ $g->id }})" wire:confirm="¿Eliminar este comprobante?" class="text-danger text-xs">✕</button>
                        </div>
                    </div>
                @empty
                    <p class="text-faint text-sm text-center py-6">Aún no has agregado ningún comprobante. Empieza rellenando el formulario de la izquierda.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
