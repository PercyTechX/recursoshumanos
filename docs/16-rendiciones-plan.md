# 16 · Módulo Rendiciones (caja chica) — Plan de diseño

> **Estado:** PLANIFICACIÓN. El usuario irá pasando contexto (cómo genera la
> documentación/PDF, reglas, etc.). No construir hasta cerrar el plan.
> Fuente original: `docs/ADAPTACION_PHP_LARAGON.md` (sistema Node+React+Google
> que se porta). Aquí adaptamos ese sistema como **módulo** de recursoshumanos.

---

## 1. Qué hace (resumen)

Rendición de **caja chica** entregada a técnicos de campo:
- **Supervisor** registra un **depósito** de dinero (con voucher) a un técnico, ligado
  a un **ticket** y un **local**; le envía un **enlace por WhatsApp**.
- **Técnico** entra **sin login** (token en la URL), sube **comprobantes de gasto** y
  **liquida**: Exacto (dif=0) · Devolución (sobró) · Reembolso (gastó de más).
- **Supervisor** valida: Aprueba / Rechaza (Observado) / Anula; puede **Ampliar** el
  monto. Al aprobar se genera una **Hoja Resumen PDF**.
- Máquina de estados estricta (ver `ADAPTACION_PHP_LARAGON.md` §3):
  `Rindiendo → Por Revisar → Finalizado`, con ramas `Observado` y `Anulado`.

---

## 2. Decisiones tomadas (2026-07-14)

1. **Enfoque:** módulo integrado dentro de recursoshumanos (NO app separada, NO tablas
   propias de personas). Reusa empleados, usuarios/roles, tickets y clientes/sedes.
2. **Archivos:** SharePoint, biblioteca **CONTABILIDAD**, carpeta raíz **`Rend_Sistemas`**
   (separada de `Doc_Sistemas` de RRHH). El permiso a nivel de sitio ya cubre CONTABILIDAD.
3. **Momento:** planear ahora; construir después (el usuario aporta el contexto faltante).

---

## 3. Mapeo de reuso (integración)

| Concepto en Rendiciones (doc) | En recursoshumanos |
|---|---|
| técnico (nombre, celular, DNI) | **`empleados`** (FK `empleado_id`; snapshot de nombre/celular al crear) |
| supervisor + clave compartida | **`users` + rol** (Supervisor/RRHH); auth normal de la app |
| ticket | **módulo `tickets`** *(¿FK a ticket existente o número libre? → §6)* |
| local | **clientes / sucursales / sedes** *(¿FK o texto? → §6)* |
| enlace WhatsApp | patrón WhatsApp ya usado en documentos |
| archivos (Drive/local) | **adaptador SharePoint** (CONTABILIDAD/Rend_Sistemas) |
| PDF (pdfkit) | **barryvdh/laravel-dompdf** (ya instalado, se usa en Hoja de Ruta) |
| React SPA | **Livewire + Tailwind** (mismo estilo del resto de la app) |

---

## 4. Tablas nuevas (solo lo propio de rendición)

Ligadas a `empleados`/`users` en vez de tablas de personas propias:

- `rendicion_depositos` — item, empleado_id (FK), ticket (ref/num), local (ref/txt),
  monto (total), dia, token (unique), estado (enum), observaciones,
  supervisor_id (FK users), snapshots (tecnico_nombre, telefono, supervisor_nombre),
  fecha_rendido, fecha_aprobado, resumen_pdf (item-id SharePoint),
  + columnas SharePoint del voucher inicial (item_id/web_url/status).
- `rendicion_gastos` — deposito_id (FK), tipo_comprobante, nro_comprobante,
  monto_gasto, fecha_comprobante, archivo (item-id SharePoint + web_url).
- `rendicion_liquidacion` — deposito_id (unique FK), monto_depositado, total_gastado,
  diferencia, estado_liquidacion (Exacto/Devolucion/Reembolso), comprobante (item-id).
- `rendicion_ampliaciones` — deposito_id (FK), monto, fecha, motivo, supervisor_id,
  voucher (item-id SharePoint).

> Detalle de campos y tipos: base en `ADAPTACION_PHP_LARAGON.md` §4, adaptado a FKs.

---

## 5. Piezas técnicas a reutilizar / ajustar

- **SharePointDocs multi-destino (AJUSTE):** hoy apunta a una sola biblioteca (RRHH).
  Generalizar a destinos con nombre, ej. en `config/services.graph`:
  ```php
  'destinos' => [
      'documentos'   => ['drive' => 'RRHH',        'folder' => 'Doc_Sistemas'],
      'rendiciones'  => ['drive' => 'CONTABILIDAD', 'folder' => 'Rend_Sistemas'],
  ],
  ```
  y resolver/cachear el drive-id por nombre. Documentos seguiría igual (destino "documentos").
- **Auth del técnico:** ruta pública por **token** (sin login), estilo `rendir/{token}`
  (patrón parecido al de `_setup/{token}`). El supervisor usa el login normal.
- **Roles/permisos:** nuevos permisos `rendiciones.*` (ver/crear/aprobar/ampliar/anular)
  en `config/permisos.php` + asignación por rol.
- **PDF:** servicio `ResumenRendicionPdf` con dompdf; contenido según `ADAPTACION...` §7.
- **WhatsApp:** plantillas de mensajes según `ADAPTACION...` §8.6.
- **Datos de negocio fijos:** PercyTech, RUC 10463288271, cuentas Interbank/BCP,
  WhatsApp soporte +51 966 804 286, soles (S/). *(¿siguen vigentes para GDS? → §6)*

---

## 6. Preguntas abiertas / contexto que falta

1. **PDF / "cómo genera la documentación":** el usuario enviará el detalle real (muestra
   o layout) para replicar la Hoja Resumen con exactitud. ¿El §7 del doc es fiel al actual?
2. **Ticket:** ¿el depósito enlaza a un **ticket existente** del módulo tickets (FK) o es
   un **número/texto libre**? (afecta el formulario y la BD)
3. **Local:** ¿enlaza a **cliente/sucursal/sede** existente (FK) o es texto libre?
4. **Técnicos:** ¿siempre son **empleados registrados**? ¿o a veces terceros no-empleados?
5. **Supervisores:** ¿= usuarios con rol **Supervisor**? ¿varios supervisores?
6. **Datos de negocio:** ¿la Hoja Resumen sigue diciendo **PercyTech / RUC 10463288271 /
   cuentas Interbank-BCP / WhatsApp +51 966...**, o cambian para **GDS Infraestructura**?
7. **Migración de datos** existentes del sistema Google (Sheets/Drive): ¿hay histórico que
   migrar, o arranca limpio?

---

## 7. Plan por fases (borrador, se ajusta al cerrar §6)

- **Fase A — Datos y estados:** migraciones + modelos + máquina de estados + permisos.
- **Fase B — Panel supervisor (Livewire):** registrar depósito, listar por estado,
  aprobar/rechazar/anular/ampliar, enlace WhatsApp.
- **Fase C — Vista técnico (token):** subir comprobantes, liquidar (Exacto/Devolución/Reembolso).
- **Fase D — SharePoint multi-destino:** vouchers/comprobantes → CONTABILIDAD/Rend_Sistemas.
- **Fase E — PDF Hoja Resumen** (al aprobar) + tests de los flujos críticos (§15 del doc).

---

*Este plan se irá completando con el contexto que aporte el usuario.*
