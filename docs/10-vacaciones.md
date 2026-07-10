# Vacaciones y permisos

> **Estado:** ✅ IMPLEMENTADO (2026-07-10, rama `feature/vacaciones`).

## Diseño: saldo como libro mayor (ledger)

El saldo de cada empleado **no es un número editable**, sino la **suma de sus
movimientos** (`movimientos_vacaciones`). Así queda **auditable** y con historial.

Tipos de movimiento:
- **apertura** — saldo inicial a la fecha de corte (+). Se carga una vez por empleado.
- **devengado** — acumulación por tiempo trabajado (+). Manual por ahora.
- **gozado** — días tomados (−). Se genera **solo** al aprobar una solicitud.
- **ajuste** — corrección manual (+/−).

`saldo = SUM(dias)`  ·  accessor `Empleado::saldo_vacaciones`.

## Solicitudes con aprobación

Tabla `solicitudes_vacaciones`: empleado, fecha_inicio, fecha_fin, **días
calendario** (inclusivos), motivo, estado (pendiente / aprobada / rechazada /
cancelada), quién decidió y cuándo, comentario.

Flujo (menú **Vacaciones**, roles RRHH / Gerencia / Supervisor):
1. **+ Nueva solicitud** → empleado + fechas (calcula los días y muestra el saldo).
2. **Aprobar** → crea el movimiento `gozado` (−días) y descuenta del saldo.
3. **Rechazar** → con comentario opcional; no toca el saldo.
4. **Cancelar** → si sigue pendiente.

## En el expediente (pestaña Vacaciones)

- **Saldo** actual (verde / rojo si es negativo).
- **Solicitudes** del empleado.
- **Libro mayor** (todos los movimientos).
- **+ Registrar movimiento** (RRHH/Gerencia): apertura, devengado o ajuste.

## Decisiones

- **Días calendario inclusivos** (Perú: vacaciones = 30 días calendario). Si luego
  se necesita descontar feriados/fines de semana, se ajusta el cálculo.
- La **aprobación** hoy la puede hacer RRHH / Gerencia / Supervisor (no se valida
  aún que sea el supervisor directo del empleado — mejora futura).
- **Auto-servicio del rol Empleado** (que cada quien pida sus vacaciones) queda para
  cuando los empleados tengan usuario propio.

## Futuro

- Devengo automático mensual (2.5 días/mes) vía Cron.
- Validar que apruebe el supervisor directo.
- Exportar a Excel el saldo y el historial.
- Auto-servicio para el rol Empleado.
