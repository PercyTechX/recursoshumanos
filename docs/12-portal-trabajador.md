# Portal del trabajador (autoservicio)

> **Estado:** ✅ IMPLEMENTADO (2026-07-10, rama `feature/usuarios`).

Cada usuario **vinculado a un empleado** (menú Usuarios → campo "Empleado
vinculado") tiene un **"Mi espacio"** donde ve **solo lo suyo**.

## Qué ve el trabajador (menú "Mi espacio")

- **Mis datos** — su ficha en solo lectura (documento, contacto, datos laborales,
  pensión/salud, banco). Si algo está mal, lo comunica a RRHH.
- **Mis documentos** — sus documentos con el semáforo de vigencia.
- **Mis vacaciones** — su **saldo** + sus solicitudes. Puede **Solicitar vacaciones**
  (queda pendiente para que el supervisor/RRHH apruebe) y **cancelar** las suyas
  que sigan pendientes.
- **Mis ausencias** — sus descansos médicos / licencias (solo lectura).

## Seguridad (alcance por fila)

- Todo se filtra por `auth()->user()->empleado`. Un trabajador **no puede** ver ni
  tocar datos de otros.
- Solo puede **cancelar** solicitudes **suyas** y **pendientes**.
- El enlace "Mi espacio" aparece solo si el usuario tiene un empleado vinculado.

## Cierra el ciclo de vacaciones

La solicitud del trabajador aparece como **pendiente** en el módulo Vacaciones de
RRHH/Supervisor, que la **aprueba o rechaza** (y ahí se descuenta del saldo).

## Demo

Usuario `tecnico@empresa.test` / `password` (rol Empleado, vinculado a Juan Carlos
Pérez) para probar el portal.

## Pendiente / futuro

- **Mis boletas** (cuando exista el módulo de planilla).
- Redirigir al trabajador a "Mi espacio" al iniciar sesión (hoy entra al Tablero).
- Forzar cambio de contraseña en el primer ingreso.
