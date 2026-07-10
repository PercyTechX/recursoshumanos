# Estado actual del proyecto

> Actualizado: 2026-07-10. Resumen consolidado de lo construido, su estado de
> despliegue y lo pendiente. **81 tests en verde.**

## Módulos construidos

| Módulo | Qué hace | Doc | Estado |
|---|---|---|---|
| **Núcleo / Auth / Roles** | Login, roles (spatie), sedes/áreas/cargos | 02 | ✅ en producción |
| **Empleados + Ficha** | Ficha legal completa, sueldo por rol, cese | 07 | ✅ en producción |
| **Derechohabientes** | Cónyuge/hijos con documentos (pestaña Familia) | 07 | ✅ en producción |
| **Documentos + semáforo** | Vigencias, semáforo, historial, export | 02 | ✅ en producción |
| **Documentos compartidos** | SCTR colectivo: 1 archivo → muchas personas | 08 | ✅ en producción |
| **Activos / EPP** | Inventario, asignar/devolver con firma, trazabilidad | 06 | ✅ en producción |
| **Hoja de ruta / Descuentos** | Liquidación firmada → descuentos → Contador → PDF | 06 | ✅ en producción |
| **Vacaciones** | Solicitudes + saldo tipo ledger | 10 | ✅ en producción |
| **Vacaciones: retorno anticipado** | Interrupción reintegra días al saldo | 10 | 🟡 local (feature/usuarios) |
| **Aviso al supervisor (WhatsApp)** | Genera mensaje, el usuario elige contacto | 11 | 🟡 local |
| **Íconos SVG** | Sin emojis; íconos en menú y acciones | — | 🟡 local |
| **Ausencias** | Descanso médico (CITT), licencias, permisos, faltas | 10 | 🟡 local |
| **Usuarios y Super Admin** | CRUD, reset clave, activar, vincular empleado | 11 | 🟡 local |
| **Roles y accesos (matriz)** | Permisos por módulo × acción, configurable | 11 | 🟡 local |
| **Portal del trabajador** | Autoservicio: mis datos/documentos/vacaciones/ausencias | 12 | 🟡 local |

## Estado de despliegue

- **En producción (`rrhh.gds.pe`)** está lo marcado ✅: hasta el módulo de
  Vacaciones (base). Ver [09-deploy-cpanel.md](09-deploy-cpanel.md).
- **Pendiente de subir (🟡):** todo lo construido después está en la rama
  **`feature/usuarios`** (9 commits sobre `main`), **validado en local** por el
  usuario. Para publicarlo: fusionar a `main` → push → *Update from Remote* en
  cPanel → `/_setup/{token}` (hay migraciones nuevas: retorno, ausencias,
  avisos_documento, users.activo, permisos).

## Cómo se prueba en local

`php artisan serve` (http://127.0.0.1:8000). Usuarios demo:
- **admin@rrhh.test / password** — Super Admin (ve y hace todo).
- **tecnico@empresa.test / password** — trabajador (portal "Mi espacio").

## Arquitectura de accesos (resumen)

- **Roles** + **permisos** `<modulo>.<accion>` (config/permisos.php).
- Menú y rutas por permiso (`@can` / `role_or_permission`); acciones protegidas en
  cada pantalla (`abort_unless`). **SuperAdmin** pasa siempre (Gate::before).
- Configurable desde **"Roles y accesos"** (sin tocar código).

## Pendientes / próximos

- **Asistencia** con geocerca — siguiente módulo (ver [14-asistencia.md](14-asistencia.md)).
- **Boletas de pago** (requiere módulo de Planilla).
- **OneDrive/Graph** para archivos (requiere registro de app en Azure por el usuario).
- **Excel real (.xlsx)** (hoy CSV).
- Correo SMTP (para "olvidé mi contraseña" y otros avisos).
- Exportadores SUNAT (PLAME / T-Registro) y bancos.
- SSOMA (capacitaciones, IPERC, incidentes).
- Devengo automático de vacaciones (Cron).
- Backups sin SSH (exportar .sql / .zip en PHP puro para Super Admin).
