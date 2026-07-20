@php
    /** @var \App\Models\Empleado $empleado */
    use Illuminate\Support\Carbon;

    $empresa = config('empresa');
    $logo = file_exists(public_path('images/brand/logo-gds.png'))
        ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/logo-gds.png')))
        : null;

    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fechaLarga = function ($d) use ($meses) {
        if (! $d) {
            return '____________';
        }
        $c = Carbon::parse($d);

        return $c->day.' de '.$meses[(int) $c->month].' de '.$c->year;
    };

    $cesado = $empleado->situacion === 'cesado';
    $ingreso = $empleado->fecha_ingreso;
    $fin = $cesado ? $empleado->fecha_cese : now();

    // Tiempo de servicios
    $tiempo = '____________';
    if ($ingreso && $fin) {
        $d = Carbon::parse($ingreso)->diff(Carbon::parse($fin));
        $partes = [];
        if ($d->y) {
            $partes[] = $d->y.' año'.($d->y > 1 ? 's' : '');
        }
        if ($d->m) {
            $partes[] = $d->m.' mes'.($d->m > 1 ? 'es' : '');
        }
        if ($d->d && ! $d->y) {
            $partes[] = $d->d.' día'.($d->d > 1 ? 's' : '');
        }
        $tiempo = $partes ? implode(', ', $partes) : 'menos de un mes';
    }

    $genero = $empleado->sexo === 'F' ? 'la señora' : ($empleado->sexo === 'M' ? 'el señor' : 'el(la) señor(a)');
    $cargoTxt = $empleado->cargo?->nombre ? 'desempeñando el cargo de <strong>'.$empleado->cargo->nombre.'</strong>' : 'como parte de nuestro personal';
    $areaTxt = $empleado->area?->nombre ? ', en el área de '.$empleado->area->nombre : '';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 13px; margin: 40px 55px; line-height: 1.7; }
        .head { text-align: center; border-bottom: 2px solid #1d3b6b; padding-bottom: 12px; margin-bottom: 30px; }
        .head img { height: 44px; }
        .head .emp { font-size: 10px; color: #555; margin-top: 6px; }
        h1 { text-align: center; font-size: 18px; letter-spacing: 2px; margin: 20px 0 34px; }
        p { text-align: justify; margin: 0 0 16px; }
        .firma { margin-top: 90px; text-align: center; }
        .firma .linea { border-top: 1px solid #333; width: 260px; margin: 0 auto 4px; }
        .firma .cargo { font-size: 11px; color: #555; }
        .foot { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; color: #9a9a9a; font-size: 8px; }
    </style>
</head>
<body>
    <div class="head">
        @if ($logo)<img src="{{ $logo }}">@endif
        <div class="emp">{{ $empresa['nombre'] }} · RUC {{ $empresa['ruc'] }}@if ($empresa['direccion']) · {{ $empresa['direccion'] }}@endif</div>
    </div>

    <h1>CERTIFICADO DE TRABAJO</h1>

    <p>
        <strong>{{ $empresa['nombre'] }}</strong>, con RUC N° {{ $empresa['ruc'] }}, deja constancia que
        {{ $genero }} <strong>{{ trim($empleado->nombres.' '.$empleado->apellidos) }}</strong>,
        identificado(a) con {{ $empleado->tipo_documento ?? 'DNI' }} N° <strong>{{ $empleado->numero_documento }}</strong>,
        {{ $cesado ? 'laboró' : 'labora' }} en nuestra empresa {!! $cargoTxt !!}{{ $areaTxt }},
        desde el <strong>{{ $fechaLarga($ingreso) }}</strong>
        {{ $cesado ? 'hasta el '.$fechaLarga($fin) : 'a la fecha' }},
        acumulando un tiempo de servicios de <strong>{{ $tiempo }}</strong>.
    </p>

    <p>
        Se expide el presente certificado a solicitud del(la) interesado(a), para los fines que estime conveniente.
    </p>

    <p style="margin-top:26px">{{ $empresa['ciudad'] }}, {{ $fechaLarga(now()) }}.</p>

    <div class="firma">
        <div class="linea"></div>
        <strong>{{ $empresa['nombre'] }}</strong>
        <div class="cargo">Firma y sello del empleador</div>
    </div>

    <div class="foot">Documento generado por el Sistema RRHH</div>
</body>
</html>
