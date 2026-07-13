<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#F2F8FD;font-family:Arial,Helvetica,sans-serif;color:#10233A;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#0D3B66;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;">
            <div style="font-size:14px;opacity:.85;">Sistema RRHH</div>
            <div style="font-size:18px;font-weight:bold;">Aviso de vencimiento de documento</div>
        </div>
        <div style="background:#fff;padding:20px;border:1px solid #DCE7F1;border-top:0;border-radius:0 0 12px 12px;">
            <p style="margin:0 0 12px;">Estimado(a) supervisor(a):</p>
            <p style="margin:0 0 16px;">
                Le informamos que el siguiente documento del trabajador
                <strong>{{ $empleado?->nombres }} {{ $empleado?->apellidos }}</strong>
                @if ($estado === 'vencido')
                    se encuentra <strong style="color:#C62828;">VENCIDO</strong>.
                @else
                    está <strong style="color:#B26A0B;">POR VENCER</strong>.
                @endif
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:8px 0;color:#46607C;">Documento</td>
                    <td style="padding:8px 0;font-weight:bold;">{{ $tipo }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#46607C;">Trabajador</td>
                    <td style="padding:8px 0;">{{ $empleado?->nombres }} {{ $empleado?->apellidos }}
                        @if ($empleado?->numero_documento) ({{ $empleado->tipo_documento }} {{ $empleado->numero_documento }}) @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#46607C;">Vencimiento</td>
                    <td style="padding:8px 0;">{{ $fechaVencimiento ?? '—' }}</td>
                </tr>
                @if (! is_null($dias))
                    <tr>
                        <td style="padding:8px 0;color:#46607C;">Estado</td>
                        <td style="padding:8px 0;">
                            @if ($dias < 0)
                                Venció hace {{ abs($dias) }} día(s)
                            @else
                                Vence en {{ $dias }} día(s)
                            @endif
                        </td>
                    </tr>
                @endif
            </table>

            <p style="margin:16px 0 0;color:#46607C;font-size:13px;">
                Por favor, coordine con el trabajador la renovación o regularización del documento.
            </p>
        </div>
        <p style="text-align:center;color:#8AA0B8;font-size:12px;margin:16px 0 0;">
            Este es un aviso automático del Sistema RRHH.
        </p>
    </div>
</body>
</html>
