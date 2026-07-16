@php
    /** @var \App\Models\RendicionDeposito $dep */
    $money = fn ($n) => 'S/ '.number_format((float) $n, 2, '.', '');
    $f = fn ($d) => $d?->format('Y-m-d') ?? '—';

    $logo = fn (string $file) => file_exists(public_path("images/rendiciones/{$file}"))
        ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path("images/rendiciones/{$file}")))
        : null;
    $logoGds = $logo('logo-azul-gds.png');
    $logoPercy = $logo('logo-percytech-pdf.png');

    $empresa = config('rendiciones.empresa');
    $dev = config('rendiciones.elaborado_por');
    $liq = $dep->liquidacion;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 24px 34px 70px; }
        .brand td { vertical-align: middle; }
        .brand img.gds { height: 34px; }
        .brand .emp { text-align: right; font-size: 9px; color: #777; line-height: 1.4; }
        h1 { text-align: center; font-size: 19px; margin: 14px 0 2px; }
        .emitida { text-align: center; color: #888; font-size: 10px; margin: 0 0 16px; }
        .datos p { margin: 2px 0; }
        h2 { color: #1d63ed; font-size: 13px; margin: 16px 0 4px; padding-bottom: 4px; border-bottom: 1px solid #cccccc; }
        .linea { margin: 5px 0; }
        .tot { font-weight: bold; margin: 5px 0; }
        .foot { position: fixed; bottom: -20px; left: 0; right: 0; text-align: center; color: #8a8a8a; font-size: 9px; }
        .foot img { height: 20px; vertical-align: middle; margin-bottom: 3px; }
    </style>
</head>
<body>
    {{-- Marca: GDS (dueña de la caja) --}}
    <table class="brand" width="100%">
        <tr>
            <td>@if ($logoGds)<img class="gds" src="{{ $logoGds }}">@endif</td>
            <td class="emp">{{ $empresa['nombre'] }}<br>RUC {{ $empresa['ruc'] }}</td>
        </tr>
    </table>

    <h1>Hoja Resumen de Rendición</h1>
    <p class="emitida">Emitida: {{ $f($dep->fecha_aprobado) }}</p>

    <div class="datos">
        <p><strong>Ticket:</strong> {{ $dep->ticket?->ticket_atencion }}</p>
        <p><strong>Técnico:</strong> {{ $dep->tecnico_nombre }}</p>
        <p><strong>Local:</strong> {{ $dep->local_nombre }}</p>
        <p><strong>Supervisor:</strong> {{ $dep->supervisor_nombre }}</p>
    </div>

    <h2>Depósitos</h2>
    <p class="linea">Depósito inicial: {{ $money($dep->monto_inicial) }} &nbsp; ({{ $f($dep->dia) }})</p>
    @foreach ($dep->ampliaciones as $a)
        <p class="linea">Depósito adicional: {{ $money($a->monto) }} &nbsp; ({{ $f($a->fecha) }}){{ $a->motivo ? ' — '.$a->motivo : '' }}</p>
    @endforeach
    <p class="tot">Total entregado: {{ $money($dep->monto) }}</p>

    <h2>Rendición (comprobantes)</h2>
    @forelse ($dep->gastos as $g)
        <p class="linea">{{ $g->tipo_comprobante }} {{ $g->nro_comprobante }} &nbsp;-&nbsp; {{ $money($g->monto_gasto) }} &nbsp;-&nbsp; {{ $f($g->fecha_comprobante) }}</p>
    @empty
        <p class="linea">Sin comprobantes registrados.</p>
    @endforelse
    <p class="tot">Total gastado: {{ $money($liq?->total_gastado ?? $dep->gastos->sum('monto_gasto')) }}</p>

    <h2>Vuelto o Reembolso</h2>
    <p class="linea">
        @if ($liq?->estado_liquidacion === \App\Models\RendicionDeposito::LIQ_REEMBOLSO)
            Reembolso al técnico: {{ $money(abs($liq->diferencia)) }}
        @elseif ($liq?->estado_liquidacion === \App\Models\RendicionDeposito::LIQ_DEVOLUCION)
            Vuelto devuelto por el técnico: {{ $money(abs($liq->diferencia)) }}
        @else
            Balance exacto: sin vuelto ni reembolso.
        @endif
    </p>

    <h2>Visto Bueno (VB°)</h2>
    <p class="linea">VB Técnico: &nbsp; {{ $dep->tecnico_nombre }} &nbsp; ({{ $f($dep->fecha_rendido) }})</p>
    <p class="linea">VB Supervisor: &nbsp; {{ $dep->supervisor_nombre }} &nbsp; ({{ $f($dep->fecha_aprobado) }})</p>

    <div class="foot">
        @if ($logoPercy)<img src="{{ $logoPercy }}"><br>@endif
        Elaborado por {{ $dev['nombre'] }} · RUC {{ $dev['ruc'] }} · Soporte: +51 {{ trim(chunk_split($dev['soporte'], 3, ' ')) }} (WhatsApp)
    </div>
</body>
</html>
