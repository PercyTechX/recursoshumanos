<?php

use App\Models\Empleado;
use App\Models\RendicionAmpliacion;
use App\Models\RendicionDeposito;
use App\Models\Ticket;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $tab = 'pendientes'; // pendientes | revisar | finalizados | anulados | todos
    public string $buscar = '';
    public ?int $filtroTecnico = null;

    // Registrar depósito (form inline)
    public ?int $empleado_id = null;
    public ?int $ticket_id = null;
    public string $monto = '';
    public string $dia = '';
    public $voucher = null;

    // Modal de acción (aprobar/rechazar/anular/ampliar)
    public bool $mostrarAccion = false;
    public string $accion = '';
    public ?int $accionId = null;
    public string $motivo = '';
    public string $ampMonto = '';
    public string $ampFecha = '';
    public $ampVoucher = null;
    public $reembolsoVoucher = null;

    // Detalle
    public ?int $detalleId = null;

    public function mount(): void
    {
        $this->dia = now()->toDateString();
        $this->ampFecha = now()->toDateString();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroTecnico(): void
    {
        $this->resetPage();
    }

    // ---- Registrar depósito ----
    public function registrar(): void
    {
        abort_unless(auth()->user()->can('rendiciones.registrar'), 403);
        $this->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'ticket_id' => ['required', 'exists:tickets,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'dia' => ['required', 'date'],
            'voucher' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [], ['empleado_id' => 'técnico', 'ticket_id' => 'ticket', 'dia' => 'fecha']);

        $emp = Empleado::findOrFail($this->empleado_id);
        $ticket = Ticket::with(['cliente', 'sucursal', 'sede'])->findOrFail($this->ticket_id);

        $dep = new RendicionDeposito([
            'empleado_id' => $emp->id,
            'ticket_id' => $ticket->id,
            'supervisor_id' => auth()->id(),
            'monto' => $this->monto,
            'dia' => $this->dia,
            'token' => RendicionDeposito::nuevoToken(),
            'estado' => RendicionDeposito::RINDIENDO,
            'tecnico_nombre' => trim($emp->apellidos.', '.$emp->nombres),
            'tecnico_celular' => $emp->telefono,
            'tecnico_documento' => $emp->numero_documento,
            'supervisor_nombre' => auth()->user()->name,
            'local_nombre' => $this->localDeTicket($ticket),
        ]);

        if ($this->voucher) {
            // Fase B: se guarda local; la subida a SharePoint es la Fase D.
            $dep->voucher_nombre = $this->voucher->getClientOriginalName();
            $dep->voucher_path = $this->voucher->store('rendiciones/vouchers', 'public');
            $dep->storage_driver = 'local';
            $dep->voucher_status = 'pendiente';
        }

        $dep->save();

        $this->reset(['empleado_id', 'ticket_id', 'monto', 'voucher']);
        $this->dia = now()->toDateString();
        session()->flash('ok', 'Depósito registrado. Comparte el enlace con el técnico por WhatsApp.');
    }

    private function localDeTicket(Ticket $t): string
    {
        return trim(($t->cliente?->nombre ?? '').' · '.$t->ubicacion_nombre, ' ·');
    }

    // ---- Acciones del supervisor ----
    public function abrirAccion(string $accion, int $id): void
    {
        $this->accion = $accion;
        $this->accionId = $id;
        $this->reset(['motivo', 'ampMonto', 'ampVoucher', 'reembolsoVoucher']);
        $this->ampFecha = now()->toDateString();
        $this->resetErrorBag();
        $this->mostrarAccion = true;
    }

    public function confirmarAccion(): void
    {
        $dep = RendicionDeposito::findOrFail($this->accionId);

        match ($this->accion) {
            'aprobar' => $this->aprobar($dep),
            'rechazar' => $this->rechazar($dep),
            'anular' => $this->anular($dep),
            'ampliar' => $this->ampliar($dep),
            default => abort(400),
        };
    }

    private function aprobar(RendicionDeposito $dep): void
    {
        abort_unless(auth()->user()->can('rendiciones.aprobar'), 403);

        // Si la liquidación es Reembolso, el supervisor debe adjuntar el voucher del reembolso.
        $liq = $dep->liquidacion;
        if ($liq && $liq->estado_liquidacion === RendicionDeposito::LIQ_REEMBOLSO) {
            $this->validate(
                ['reembolsoVoucher' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
                [], ['reembolsoVoucher' => 'voucher de reembolso']
            );
            $liq->comprobante_nombre = $this->reembolsoVoucher->getClientOriginalName();
            $liq->comprobante_path = $this->reembolsoVoucher->store('rendiciones/liquidacion', 'public');
            $liq->comprobante_status = 'pendiente';
            $liq->save();
        }

        $dep->transicionar('aprobar');
        $dep->fecha_aprobado = now()->toDateString();
        $dep->save();
        // Hoja Resumen PDF: Fase E.
        $this->cerrarAccion('Rendición aprobada.');
    }

    private function rechazar(RendicionDeposito $dep): void
    {
        abort_unless(auth()->user()->can('rendiciones.aprobar'), 403);
        $this->validate(['motivo' => ['required', 'string', 'max:300']], [], ['motivo' => 'motivo']);
        $dep->transicionar('rechazar');
        $dep->observaciones = $this->motivo;
        $dep->save();
        $this->cerrarAccion('Rendición observada. El técnico la corregirá.');
    }

    private function anular(RendicionDeposito $dep): void
    {
        abort_unless(auth()->user()->can('rendiciones.anular'), 403);
        $this->validate(['motivo' => ['required', 'string', 'max:300']], [], ['motivo' => 'motivo']);
        $dep->transicionar('anular');
        $dep->observaciones = $this->motivo;
        $dep->save();
        $this->cerrarAccion('Depósito anulado.');
    }

    private function ampliar(RendicionDeposito $dep): void
    {
        abort_unless(auth()->user()->can('rendiciones.ampliar'), 403);
        $this->validate([
            'ampMonto' => ['required', 'numeric', 'min:0.01'],
            'ampFecha' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:300'],
            'ampVoucher' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [], ['ampMonto' => 'monto', 'ampFecha' => 'fecha']);

        if (! $dep->puede('ampliar')) {
            abort(422, 'No se puede ampliar en este estado.');
        }

        $amp = new RendicionAmpliacion([
            'deposito_id' => $dep->id,
            'monto' => $this->ampMonto,
            'fecha' => $this->ampFecha,
            'motivo' => $this->motivo ?: null,
            'supervisor_id' => auth()->id(),
            'supervisor_nombre' => auth()->user()->name,
        ]);
        if ($this->ampVoucher) {
            $amp->voucher_nombre = $this->ampVoucher->getClientOriginalName();
            $amp->voucher_path = $this->ampVoucher->store('rendiciones/vouchers', 'public');
            $amp->voucher_status = 'pendiente';
        }
        $amp->save();

        $dep->increment('monto', (float) $this->ampMonto);
        $this->cerrarAccion('Depósito adicional registrado. Nuevo total: S/ '.number_format($dep->fresh()->monto, 2).'.');
    }

    private function cerrarAccion(string $msg): void
    {
        $this->mostrarAccion = false;
        session()->flash('ok', $msg);
    }

    // ---- Detalle ----
    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function with(): array
    {
        $q = RendicionDeposito::query()
            ->with(['empleado', 'supervisor', 'ticket'])
            ->when($this->buscar, fn ($x) => $x->where(fn ($w) => $w
                ->where('tecnico_nombre', 'like', '%'.$this->buscar.'%')
                ->orWhere('local_nombre', 'like', '%'.$this->buscar.'%')
                ->orWhereHas('ticket', fn ($t) => $t->where('ticket_atencion', 'like', '%'.$this->buscar.'%'))))
            ->when($this->filtroTecnico, fn ($x) => $x->where('empleado_id', $this->filtroTecnico));

        match ($this->tab) {
            'revisar' => $q->where('estado', RendicionDeposito::POR_REVISAR),
            'finalizados' => $q->where('estado', RendicionDeposito::FINALIZADO),
            'anulados' => $q->where('estado', RendicionDeposito::ANULADO),
            'todos' => $q,
            default => $q->pendientes(),
        };

        return [
            'depositos' => $q->orderByDesc('id')->paginate(10),
            'empleados' => Empleado::where('situacion', 'activo')->orderBy('apellidos')->get(),
            'ticketsAbiertos' => Ticket::abiertos()->with(['cliente', 'sucursal', 'sede'])->orderByDesc('id')->get(),
            'tecnicosConDeposito' => Empleado::whereIn('id', RendicionDeposito::select('empleado_id')->distinct())->orderBy('apellidos')->get(),
            'kpi' => [
                'depositado' => (float) RendicionDeposito::sum('monto'),
                'finalizado' => (float) RendicionDeposito::where('estado', RendicionDeposito::FINALIZADO)->sum('monto'),
                'proceso' => RendicionDeposito::pendientes()->count(),
            ],
            'detalle' => $this->detalleId
                ? RendicionDeposito::with(['empleado', 'ticket.cliente', 'gastos', 'liquidacion', 'ampliaciones'])->find($this->detalleId)
                : null,
            'accionDep' => ($this->mostrarAccion && $this->accion === 'aprobar' && $this->accionId)
                ? RendicionDeposito::with('liquidacion')->find($this->accionId)
                : null,
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
    $tabs = ['pendientes' => 'Pendientes', 'revisar' => 'Por revisar', 'finalizados' => 'Finalizados', 'anulados' => 'Anulados', 'todos' => 'Todos'];
    $ticketSel = $ticket_id ? $ticketsAbiertos->firstWhere('id', (int) $ticket_id) : null;
@endphp

<div>
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-success-tint text-success px-4 py-2 text-sm font-medium">{{ session('ok') }}</div>
    @endif

    <p class="text-muted mb-5">Control de depósitos, caja chica y comprobantes de técnicos.</p>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div class="bg-surface border border-line rounded-xl p-4 flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-primary-tint text-primary grid place-items-center"><x-icon name="chart" class="w-5 h-5" /></span>
            <div><div class="text-xs uppercase tracking-wide text-faint font-semibold">Total depositado</div><div class="text-2xl font-bold tabular-nums">{{ $money($kpi['depositado']) }}</div></div>
        </div>
        <div class="bg-surface border border-line rounded-xl p-4 flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-success-tint text-success grid place-items-center"><x-icon name="check" class="w-5 h-5" /></span>
            <div><div class="text-xs uppercase tracking-wide text-faint font-semibold">Total finalizado</div><div class="text-2xl font-bold tabular-nums">{{ $money($kpi['finalizado']) }}</div></div>
        </div>
        <div class="bg-surface border border-line rounded-xl p-4 flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-warning-tint text-warning grid place-items-center"><x-icon name="clock" class="w-5 h-5" /></span>
            <div><div class="text-xs uppercase tracking-wide text-faint font-semibold">Cuentas en proceso</div><div class="text-2xl font-bold tabular-nums">{{ $kpi['proceso'] }} <span class="text-sm text-faint font-medium">pendientes</span></div></div>
        </div>
    </div>

    {{-- Registrar depósito --}}
    @can('rendiciones.registrar')
        <div class="bg-surface border border-line rounded-xl mb-5">
            <div class="px-4 py-3 border-b border-line flex items-center gap-2 text-navy font-semibold"><x-icon name="plus" class="w-4 h-4 text-primary" /> Registrar nuevo depósito</div>
            <form wire:submit="registrar" class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-xs text-muted mb-1 font-medium">Técnico beneficiario *</label>
                        <select wire:model.live="empleado_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                            @endforeach
                        </select>
                        @error('empleado_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-muted mb-1 font-medium">Ticket del trabajo *</label>
                        <select wire:model.live="ticket_id" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            <option value="">— Seleccionar —</option>
                            @foreach ($ticketsAbiertos as $t)
                                <option value="{{ $t->id }}">{{ $t->ticket_atencion }} · {{ $t->cliente?->nombre }}</option>
                            @endforeach
                        </select>
                        @error('ticket_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-muted mb-1 font-medium">Local del trabajo</label>
                        <div class="w-full rounded-lg border border-line bg-canvas text-sm text-muted px-3 py-2 h-[38px] truncate">
                            {{ $ticketSel ? trim(($ticketSel->cliente?->nombre ?? '').' · '.$ticketSel->ubicacion_nombre, ' ·') : '— automático del ticket —' }}
                        </div>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-muted mb-1 font-medium">Monto depositado (S/) *</label>
                        <input type="number" step="0.01" min="0" wire:model="monto" placeholder="0.00" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary tabular-nums">
                        @error('monto') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-muted mb-1 font-medium">Fecha de depósito *</label>
                        <input type="date" wire:model="dia" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        @error('dia') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs text-muted mb-1 font-medium">Voucher del depósito</label>
                        <input type="file" wire:model="voucher" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                        <div wire:loading wire:target="voucher" class="text-xs text-faint mt-1">Subiendo…</div>
                        @error('voucher') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2 flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2">Registrar</button>
                    </div>
                </div>
                <p class="text-xs text-faint mt-3">El <strong>supervisor</strong> se toma de tu sesión ({{ auth()->user()->name }}). El voucher se subirá a SharePoint (Fase D).</p>
            </form>
        </div>
    @endcan

    {{-- Lista --}}
    <div class="bg-surface border border-line rounded-xl">
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-line">
            <div class="inline-flex rounded-lg border border-line overflow-hidden">
                @foreach ($tabs as $k => $label)
                    <button wire:click="$set('tab', '{{ $k }}')" class="px-4 py-2 text-sm font-medium {{ $tab === $k ? 'bg-primary text-white' : 'bg-surface text-muted hover:bg-canvas' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 py-2 min-w-[220px]">
                <x-icon name="search" class="w-4 h-4 text-faint shrink-0" />
                <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar ticket, local, técnico…" class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-faint focus:ring-0">
            </div>
            <select wire:model.live="filtroTecnico" class="rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                <option value="">Todos los técnicos</option>
                @foreach ($tecnicosConDeposito as $e)
                    <option value="{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[860px]">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-faint bg-canvas border-b border-line">
                        <th class="px-4 py-3">Técnico</th>
                        <th class="px-4 py-3">Supervisor</th>
                        <th class="px-4 py-3">Ticket</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                        <th class="px-4 py-3">Día</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($depositos as $d)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium text-ink">{{ $d->tecnico_nombre }}</div>
                                <div class="text-xs text-faint">{{ $d->local_nombre }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $d->supervisor_nombre }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $d->ticket?->ticket_atencion }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $money($d->monto) }}</td>
                            <td class="px-4 py-3 text-muted tabular-nums">{{ $d->dia?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge[$d->estado] ?? 'bg-canvas text-faint' }}"><span class="w-2 h-2 rounded-full bg-current"></span>{{ $d->estado }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5 justify-end">
                                    @php $chip = 'text-xs font-semibold px-2.5 py-1 rounded-lg border border-line hover:bg-canvas'; @endphp
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ url('/rendir/'.$d->token) }}'); this.textContent='Copiado'" class="{{ $chip }} text-muted">Link</button>
                                    @if ($d->tecnico_celular)
                                        @php
                                            $tel = preg_replace('/\D/', '', $d->tecnico_celular);
                                            $tel = str_starts_with($tel, '51') ? $tel : '51'.$tel;
                                            $msg = "Hola {$d->tecnico_nombre}\n\nTe comparto el enlace para rendir tu depósito\n\nTicket {$d->ticket?->ticket_atencion}\nMonto S/ ".number_format($d->monto, 2)."\n\n".url('/rendir/'.$d->token);
                                        @endphp
                                        <a href="https://wa.me/{{ $tel }}?text={{ rawurlencode($msg) }}" target="_blank" class="{{ $chip }} text-[#1eaf5b]">WhatsApp</a>
                                    @endif
                                    <button wire:click="verDetalle({{ $d->id }})" class="{{ $chip }} text-muted">Detalles</button>
                                    @can('rendiciones.aprobar')
                                        @if ($d->puede('aprobar'))
                                            <button wire:click="abrirAccion('aprobar', {{ $d->id }})" class="{{ $chip }} text-success">Aprobar</button>
                                            <button wire:click="abrirAccion('rechazar', {{ $d->id }})" class="{{ $chip }} text-danger">Rechazar</button>
                                        @endif
                                    @endcan
                                    @can('rendiciones.ampliar')
                                        @if ($d->puede('ampliar'))
                                            <button wire:click="abrirAccion('ampliar', {{ $d->id }})" class="{{ $chip }} text-primary">Ampliar</button>
                                        @endif
                                    @endcan
                                    @can('rendiciones.anular')
                                        @if ($d->puede('anular'))
                                            <button wire:click="abrirAccion('anular', {{ $d->id }})" class="{{ $chip }} text-danger">Anular</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-faint">No hay depósitos en esta vista.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $depositos->links() }}</div>
    </div>

    {{-- Leyenda --}}
    <div class="mt-5 bg-surface border border-line rounded-xl p-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-muted">
        <span><span class="inline-block w-2 h-2 rounded-full bg-primary mr-1.5"></span><strong>Rindiendo:</strong> el técnico sube comprobantes.</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-warning mr-1.5"></span><strong>Por Revisar:</strong> requiere tu validación.</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-success mr-1.5"></span><strong>Finalizado:</strong> aprobada y cerrada.</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-danger mr-1.5"></span><strong>Observado:</strong> rechazada; el técnico corrige.</span>
        <span><span class="inline-block w-2 h-2 rounded-full bg-faint mr-1.5"></span><strong>Anulado:</strong> cancelada por error.</span>
    </div>

    {{-- Modal de acción --}}
    @if ($mostrarAccion)
        @php
            $titulos = ['aprobar' => 'Aprobar rendición', 'rechazar' => 'Rechazar (observar)', 'anular' => 'Anular depósito', 'ampliar' => 'Ampliar depósito'];
        @endphp
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-md mt-16 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">{{ $titulos[$accion] ?? '' }}</h3>
                    <button wire:click="$set('mostrarAccion', false)" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <form wire:submit="confirmarAccion" class="px-6 py-5 space-y-4">
                    @if ($accion === 'aprobar')
                        @if ($accionDep?->liquidacion?->estado_liquidacion === 'Reembolso')
                            <div class="rounded-lg bg-danger-tint/50 text-ink px-3 py-2 text-sm">
                                Esta rendición es un <strong>reembolso</strong> de S/ {{ number_format(abs($accionDep->liquidacion->diferencia), 2) }} al técnico. Adjunta el voucher del reembolso.
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Voucher del reembolso *</label>
                                <input type="file" wire:model="reembolsoVoucher" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                                <div wire:loading wire:target="reembolsoVoucher" class="text-xs text-faint mt-1">Subiendo…</div>
                                @error('reembolsoVoucher') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <p class="text-sm text-muted">Se marcará como <strong>Finalizado</strong>. (La Hoja Resumen PDF se generará en la Fase E.)</p>
                        @endif
                    @elseif (in_array($accion, ['rechazar', 'anular']))
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Motivo *</label>
                            <input type="text" wire:model="motivo" placeholder="{{ $accion === 'rechazar' ? 'Ej. falta comprobante del taxi' : 'Ej. depósito duplicado' }}" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                            @error('motivo') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    @elseif ($accion === 'ampliar')
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Monto adicional (S/) *</label>
                                <input type="number" step="0.01" min="0" wire:model="ampMonto" placeholder="0.00" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary tabular-nums">
                                @error('ampMonto') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Fecha *</label>
                                <input type="date" wire:model="ampFecha" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                                @error('ampFecha') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Motivo</label>
                            <input type="text" wire:model="motivo" placeholder="Ej. gastos adicionales de materiales" class="w-full rounded-lg border-line text-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Voucher del depósito adicional</label>
                            <input type="file" wire:model="ampVoucher" class="w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-canvas file:px-3 file:py-2 file:text-muted">
                            <div wire:loading wire:target="ampVoucher" class="text-xs text-faint mt-1">Subiendo…</div>
                            @error('ampVoucher') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('mostrarAccion', false)" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cancelar</button>
                        <button type="submit" class="rounded-lg text-white text-sm font-semibold px-4 py-2 {{ $accion === 'aprobar' ? 'bg-success hover:brightness-95' : ($accion === 'ampliar' ? 'bg-primary hover:bg-primary-dark' : 'bg-danger hover:brightness-95') }}">
                            {{ ['aprobar' => 'Aprobar', 'rechazar' => 'Rechazar', 'anular' => 'Anular', 'ampliar' => 'Registrar'][$accion] ?? 'Confirmar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal detalle --}}
    @if ($detalle)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/40 p-4">
            <div class="w-full max-w-lg mt-12 rounded-2xl bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-navy">Detalle del depósito</h3>
                    <button wire:click="cerrarDetalle" class="text-faint hover:text-ink text-xl leading-none">&times;</button>
                </div>
                <div class="px-6 py-5 space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="text-faint text-xs">Técnico</span><div class="text-ink">{{ $detalle->tecnico_nombre }}</div></div>
                        <div><span class="text-faint text-xs">Estado</span><div><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge[$detalle->estado] ?? '' }}">{{ $detalle->estado }}</span></div></div>
                        <div><span class="text-faint text-xs">Ticket</span><div class="text-ink tabular-nums">{{ $detalle->ticket?->ticket_atencion }}</div></div>
                        <div><span class="text-faint text-xs">Local</span><div class="text-ink">{{ $detalle->local_nombre }}</div></div>
                        <div><span class="text-faint text-xs">Depósito inicial</span><div class="text-ink tabular-nums">{{ $money($detalle->monto_inicial) }}</div></div>
                        <div><span class="text-faint text-xs">Total entregado</span><div class="text-ink font-semibold tabular-nums">{{ $money($detalle->monto) }}</div></div>
                    </div>

                    @if ($detalle->ampliaciones->count())
                        <div>
                            <div class="text-xs uppercase tracking-wide text-faint mb-1">Ampliaciones</div>
                            @foreach ($detalle->ampliaciones as $a)
                                <div class="flex justify-between border-b border-line py-1 tabular-nums"><span class="text-muted">{{ $a->fecha?->format('d/m/Y') }} · {{ $a->motivo ?: '—' }}</span><span class="font-medium">{{ $money($a->monto) }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <div class="text-xs uppercase tracking-wide text-faint mb-1">Comprobantes ({{ $detalle->gastos->count() }})</div>
                        @forelse ($detalle->gastos as $g)
                            <div class="flex justify-between border-b border-line py-1 tabular-nums"><span class="text-muted">{{ $g->tipo_comprobante }} {{ $g->nro_comprobante }}</span><span class="font-medium">{{ $money($g->monto_gasto) }}</span></div>
                        @empty
                            <p class="text-faint">Aún sin comprobantes (los sube el técnico).</p>
                        @endforelse
                    </div>

                    @if ($detalle->liquidacion)
                        <div class="rounded-lg bg-canvas p-3">
                            <div class="flex justify-between"><span class="text-muted">Liquidación</span><span class="font-semibold">{{ $detalle->liquidacion->estado_liquidacion }}</span></div>
                            <div class="flex justify-between tabular-nums"><span class="text-muted">Diferencia</span><span>{{ $money($detalle->liquidacion->diferencia) }}</span></div>
                        </div>
                    @endif

                    @if ($detalle->observaciones)
                        <div class="rounded-lg bg-danger-tint text-danger px-3 py-2 text-xs"><strong>Observación:</strong> {{ $detalle->observaciones }}</div>
                    @endif
                </div>
                <div class="px-6 py-4 border-t border-line flex justify-end">
                    <button wire:click="cerrarDetalle" class="rounded-lg border border-line text-muted text-sm font-semibold px-4 py-2 hover:bg-canvas">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>
