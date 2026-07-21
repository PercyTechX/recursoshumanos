# Estado actual del proyecto

> Actualizado: **2026-07-16**. Resumen consolidado de lo construido, su estado de
> despliegue y lo pendiente. **156 tests en verde.**

## Módulos construidos

| Módulo | Qué hace | Doc | Estado |
|---|---|---|---|
| **Núcleo / Auth / Roles / Usuarios** | Login, roles+permisos configurables (matriz), usuarios | 02, 11 | ✅ producción |
| **Empleados + Ficha + Expediente** | Ficha legal completa, expediente con pestañas, derechohabientes | 07 | ✅ producción |
| **Documentos + semáforo** | Vigencias, semáforo, historial, export, avisos WhatsApp | 02 | ✅ producción |
| **Documentos → SharePoint** | Suben a `RRHH/Doc_Sistemas/{persona}` vía Graph app-only | 15 | ✅ producción |
| **Documentos compartidos** | SCTR colectivo: 1 archivo → muchas personas | 08 | ✅ producción (archivo aún local) |
| **Activos / EPP / Hoja de ruta / Descuentos** | Inventario, entregas con firma, liquidación, PDF | 06 | ✅ producción (archivos aún locales) |
| **Vacaciones (+ devengo al vuelo, retorno anticipado)** | Solicitudes, saldo ledger, 2.5/mes prorrateado | 10 | ✅ producción |
| **Ausencias** | Descansos médicos, licencias, permisos, faltas | 10 | ✅ producción |
| **Clientes / Sucursales / Sedes** | Catálogos con geocerca + ubigeos | 14 | ✅ producción |
| **Tickets (órdenes de trabajo)** | Crear/cerrar, cliente + ubicación (geocerca) | 14 | ✅ producción |
| **Asistencia GPS + control + reportes** | Marcación GPS, control supervisor, reportes General/Detallado, export Excel con GPS y links a Maps, filtro fechas | 14 | ✅ producción |
| **Portal "Mi espacio"** | Asistencia, tickets, datos, documentos, boletas, vacaciones, ausencias, rendiciones | 12 | ✅ producción |
| **RENDICIONES (caja chica)** | Depósitos→técnico por link/portal→liquida→aprueba→**Hoja Resumen PDF**; archivos a `CONTABILIDAD/Rend_Sistemas/{ticket - técnico}` | 16 | ✅ producción |
| **Boletas de pago (MVP)** | RRHH sube PDF por periodo; trabajador ve y **confirma recepción**; a SharePoint `{persona}/Boletas` | — | ✅ producción |
| **DNI y CV al registrar / desde expediente** | Adjuntos iniciales + "Subir documento" en expediente; tipo DNI con vigencia; servicio ArchivoDocumento compartido | — | 🟡 **local (pendiente re-deploy)** |

## Estado de despliegue

- **Producción `rrhh.gds.pe`** (re-clonado 2026-07-16): TODO hasta boletas + rendiciones,
  con **SharePoint operativo** (graph:ping verde desde el hosting).
- La ruta **`/_setup/{token}`** ahora hace todo en una visita: migraciones + seeders +
  symlink storage + graph:ping.
- 🟡 **Pendiente de subir:** commit `cdda1a8` (DNI/CV al registrar + expediente + fix "Ver"
  del expediente). Próximo re-clonado lo incluye; el seeder crea el tipo "DNI" solo.

## PENDIENTES (ordenados por prioridad)

### 1. Re-deploy a producción 🟡
Subir `cdda1a8+` (DNI/CV). Flujo re-clonar (docs/09) + `/_setup/{token}` con token nuevo.

### 2. Cierre del deploy de hoy (confirmar en prod)
- [ ] Vaciar `APP_SETUP_TOKEN` en el `.env` de prod (verificar que `/_setup/...` dé 404).
- [ ] **Roles y accesos**: asignar `rendiciones.*` (Supervisor…) y `boletas.*` (RRHH…) —
      el seeder no pisa roles que ya tienen permisos.
- [ ] Prueba de humo: rendición con voucher → ver en `CONTABILIDAD/Rend_Sistemas`;
      boleta → `Doc_Sistemas/{persona}/Boletas`; link del técnico en incógnito.

### 3. Cron en cPanel (opcional, recomendado)
`php artisan rendiciones:subir-pendientes` (reintenta archivos si Microsoft falló).
Ruta típica: `cd /home/oipfutlf/repositories/recursoshumanos && /usr/local/bin/ea-php82 artisan rendiciones:subir-pendientes`.

### 4. SharePoint Fase 2 — módulos que aún guardan archivos LOCAL
Documentos compartidos, ausencias (CITT), derechohabientes, activos/EPP (firmas),
Hoja de Ruta PDF. El adaptador ya es reusable (`ArchivoDocumento` / `RendicionArchivos`).

### 5. SharePoint Fase 3 — migrar históricos
Script una vez: documentos ya guardados en el servidor → SharePoint.

### 6. Portal del trabajador — mejoras detectadas (análisis 2026-07-16)
- **Mis datos**: autoservicio para proponer cambios (tel/dirección/correo) con aprobación RRHH.
- Pestañas faltantes: **Mis descuentos**, **Mis activos/EPP** (lo asignado a su cargo).
- Vacaciones: ver **kardex** de movimientos del saldo.
- **Notificaciones** (correo/WhatsApp) al aprobar/rechazar vacaciones → requiere SMTP.

### 7. Go-live / plataforma
- **SMTP** (olvidé mi contraseña + avisos).
- Vaciar **datos de prueba** en prod antes del uso real.
- **Backups sin SSH** (exportar .sql/.zip desde la app para SuperAdmin).
- **Tardanzas** (requiere definir horarios/turnos).
- Excel nativo .xlsx (hoy .xls SpreadsheetML, funciona bien) — opcional.

### 8. Futuro (scope Perú, docs/memoria)
Planilla completa (cálculo de boletas, conceptos remunerativos), exportadores
SUNAT (PLAME/T-Registro), RENIEC DNI autocomplete, SSOMA, IA Fase 2 (extracción de
vencimientos de documentos escaneados).

### 9. Recordatorios de seguridad 🔒
- **Client secret de Graph vence 13/07/2028** → renovar antes en Entra ID.
- Revocar el **GitHub PAT** usado para clonar en cPanel si sigue activo.

### 10. Deuda técnica menor (conocida, baja prioridad)
- **Certificado de trabajo — nombre del cargo sin escapar.** En
  `resources/views/pdf/certificado-trabajo.blade.php` el cargo se inserta con
  `{!! $cargoTxt !!}` (HTML crudo) porque `$cargoTxt` trae `<strong>…</strong>`.
  El nombre del cargo va sin `e()`, así que un `<` o `>` en el nombre de un cargo
  descuadraría ese PDF. **No es explotable por trabajadores**: los cargos solo los
  crea el SuperAdmin en Catálogos. Fix pendiente: aplicar `e()` al nombre del cargo
  dentro de `$cargoTxt`. Revisado 2026-07-21 al auditar "¿un caracter raro rompe algo?"
  (el resto del sistema quedó limpio: SQL con bindings, Blade escapa, export Excel
  escapa cada celda con `ENT_XML1`).

## Cómo se prueba en local

`php artisan serve` (http://127.0.0.1:8000). Usuarios demo:
- **admin@rrhh.test / password** — Super Admin (ve y hace todo).
- **tecnico@empresa.test / password** — trabajador (portal "Mi espacio").

⚠️ El `.env` local tiene credenciales **reales** de Graph: subir archivos en local
escribe en el SharePoint real (Doc_Sistemas / Rend_Sistemas).
