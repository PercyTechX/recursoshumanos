@php
    $firma = ($hoja->firma_path && \Storage::disk('public')->exists($hoja->firma_path))
        ? 'data:image/png;base64,'.base64_encode(\Storage::disk('public')->get($hoja->firma_path))
        : null;
    $motivos = ['cese' => 'Cese del trabajador', 'perdida' => 'Pérdida', 'otro' => 'Otro'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #10233A; font-size: 12px; }
        .head { border-bottom: 3px solid #2496ED; padding-bottom: 10px; margin-bottom: 16px; }
        .head h1 { color: #0D3B66; font-size: 18px; margin: 0; }
        .head .sub { color: #46607C; font-size: 11px; margin-top: 2px; }
        .meta td { padding: 3px 0; font-size: 12px; }
        .meta .k { color: #46607C; width: 130px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #F2F8FD; color: #46607C; text-align: left; font-size: 10px;
            text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid #DCE7F1; }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #DCE7F1; }
        .r { text-align: right; }
        .c { text-align: center; }
        .total { margin-top: 8px; text-align: right; font-size: 14px; font-weight: bold; }
        .total .amt { color: #C62828; }
        .firma-box { margin-top: 40px; width: 260px; }
        .firma-box img { max-height: 90px; }
        .firma-line { border-top: 1px solid #10233A; padding-top: 4px; text-align: center; font-size: 11px; color: #46607C; }
        .foot { margin-top: 30px; font-size: 9px; color: #8AA0B8; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Hoja de Ruta — Liquidación de activos</h1>
        <div class="sub">Sistema RRHH · Documento generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <table class="meta">
        <tr><td class="k">Trabajador</td><td><strong>{{ $hoja->empleado?->apellidos }}, {{ $hoja->empleado?->nombres }}</strong></td></tr>
        <tr><td class="k">Documento</td><td>{{ $hoja->empleado?->tipo_documento }} {{ $hoja->empleado?->numero_documento }}</td></tr>
        <tr><td class="k">Motivo</td><td>{{ $motivos[$hoja->motivo] ?? $hoja->motivo }}</td></tr>
        <tr><td class="k">Fecha</td><td>{{ optional($hoja->fecha)->format('d/m/Y') }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Activo</th>
                <th class="c">¿Devuelto?</th>
                <th class="r">Monto a descontar (S/)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($hoja->items as $it)
                <tr>
                    <td>{{ $it->activo?->nombre }}@if ($it->activo?->codigo) · {{ $it->activo->codigo }}@endif</td>
                    <td class="c">{{ $it->devuelto ? 'Sí' : 'No' }}</td>
                    <td class="r">{{ $it->devuelto ? '—' : number_format((float) $it->monto_descuento, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="c">Sin activos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">Total a descontar: <span class="amt">S/ {{ number_format((float) $hoja->total_descuento, 2) }}</span></div>

    <div class="firma-box">
        @if ($firma)
            <img src="{{ $firma }}" alt="firma">
        @endif
        <div class="firma-line">Firma del trabajador (autoriza el descuento)</div>
    </div>

    <div class="foot">
        Constancia interna de entrega/devolución de activos y autorización de descuento. La firma tiene
        valor probatorio interno (no es firma digital certificada).
    </div>
</body>
</html>
