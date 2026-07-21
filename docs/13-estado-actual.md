# Estado actual del proyecto

> Actualizado: **2026-07-21**. Resumen consolidado de lo construido, su estado de
> despliegue y lo pendiente. **195 tests en verde.**

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
| **DNI y CV al registrar / desde expediente** | Adjuntos iniciales + "Subir documento" en expediente; tipo DNI con vigencia; servicio ArchivoDocumento compartido | — | ✅ producción |
| **Licencias** | Solicitud del trabajador + doble aprobación (Supervisor visa → RRHH aprueba) + sustento a SharePoint; sobre Ausencias | 17 | ✅ producción |
| **Asistencia: Refrigerios + VB** | Resumen diario con checks D/A/C (−1h c/u) + VB del supervisor; horas netas en reportes/Excel | 18 | ✅ producción |
| **Borrado en dos niveles** | Archivar (soft delete, RRHH) + purga permanente (SuperAdmin, solo sin historial) | — | ✅ producción |
| **Cifrado en reposo** | numero_cuenta, cci, sueldo cifrados con APP_KEY (cast encrypted) | — | ✅ producción |
| **Catálogos del negocio** | CRUD SuperAdmin de áreas/cargos/tipos doc/categorías activo/tipos EPP | — | ✅ producción |
| **Certificado de Trabajo** | PDF autogenerado (logo, cargo, fechas, tiempo servicios), personalizado por género; + tipos de cese (Carta Renuncia, Liquidación, Certificado) | — | ✅ producción |
| **Tablero: Cumpleaños del mes** | Panel para RRHH con los cumpleaños del mes, resalta hoy, edad que cumple | — | ✅ producción |
| **Backups automáticos de BD** | `backup:crear` diario → `.sql.gz` a `IT/BACKUP_SISTEMAS/RRHH_Sistemas`; dumper PHP puro; purga 30d; cron cPanel | 19 | ✅ producción |

## Estado de despliegue

- **Producción `rrhh.gds.pe`** (re-clonado **2026-07-21**): TODO lo de arriba en producción,
  con **SharePoint operativo** (graph:ping verde desde el hosting) y **backups diarios activos**.
- Deploy 2026-07-21 (docs/20): 4 migraciones nuevas OK (incl. **cifrado** — datos legibles
  tras verificar), seeders, symlink, graph:ping verde, smoke test de backup subió a IT.
  `/_setup` cerrado (404) y token vaciado. **Secret de Graph nuevo** (rrhh-redeploy-2026).
- **Cron de backups activo** en cPanel: diario 02:00,
  `/opt/cpanel/ea-php82/root/usr/bin/php /home/oipfutlf/repositories/recursoshumanos/artisan backup:crear`.
- La ruta **`/_setup/{token}`** hace en una visita: migraciones + seeders + symlink storage +
  graph:ping + **smoke test de backup**.

## PENDIENTES (ordenados por prioridad)

### 1. ✅ Re-deploy a producción (HECHO 2026-07-21)
Todo lo acumulado desde 2026-07-16 está en prod (DNI/CV, Licencias, Refrigerios/VB, borrado
dos niveles, dropdowns, cifrado, Catálogos, Certificado, Cumpleaños, Backups). Ver docs/20.

### 2. Ajustes post-deploy (revisar en prod cuando toque)
- [x] Vaciar `APP_SETUP_TOKEN` (/_setup da 404). — hecho 2026-07-21.
- [x] Cron de backups diario creado y probado. — hecho 2026-07-21.
- [ ] **Restringir acceso** a la carpeta `IT/BACKUP_SISTEMAS` en SharePoint (PII).
- [ ] **Roles y accesos**: revisar `rendiciones.*` / `boletas.*` / `ausencias.vb` según haga falta.

### 3. Cron en cPanel — reintento de archivos (opcional, recomendado)
`rendiciones:subir-pendientes` (reintenta si Microsoft falló). Mismo binario del cron de backups:
`/opt/cpanel/ea-php82/root/usr/bin/php /home/oipfutlf/repositories/recursoshumanos/artisan rendiciones:subir-pendientes`.

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
- ~~Backups sin SSH~~ ✅ resuelto: backup diario a SharePoint (docs/19).
- **Tardanzas** (requiere definir horarios/turnos).
- Excel nativo .xlsx (hoy .xls SpreadsheetML, funciona bien) — opcional.

### 8. Futuro (scope Perú, docs/memoria)
Planilla completa (cálculo de boletas, conceptos remunerativos), exportadores
SUNAT (PLAME/T-Registro), RENIEC DNI autocomplete, SSOMA, IA Fase 2 (extracción de
vencimientos de documentos escaneados).

### 9. Recordatorios de seguridad 🔒
- **Secret de Graph "rrhh-redeploy-2026"** (creado 2026-07-21, vence ~2028) → renovar antes
  en Entra ID. Existe uno viejo "RRHH-Docs prod" (vence 13/07/2028) que se puede borrar.
- **GitHub privado** de nuevo tras el deploy (correcto). Próximo re-deploy: público temporal o ZIP.
- Guardar el **APP_KEY** junto a los backups (sin él, los datos cifrados no se recuperan).

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
