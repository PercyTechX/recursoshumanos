# 16 · Módulo Rendiciones (caja chica) — Plan de diseño

> **Estado:** PLAN CERRADO ✅. **Fase A construida** (datos/modelos/estados/permisos, 2026-07-14).
> En curso: Fase B (panel supervisor, mockup aprobado). Único nice-to-have: muestra real del
> PDF para afinar la Hoja Resumen (logos ya definidos).
> Fuente original: `docs/ADAPTACION_PHP_LARAGON.md` (sistema Node+React+Google que se porta).
> Aquí lo adaptamos como **módulo** de recursoshumanos (sin Google; con MySQL + SharePoint).

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
4. **Sin Google:** NO se usa Google Drive ni Google Sheets. Todo con el stack actual
   (Laravel + MySQL) + SharePoint (OneDrive) para archivos.
5. **Histórico:** arrancar **limpio** (sin migración). El sistema Google viejo queda como
   consulta aparte si hiciera falta.
6. **Técnico:** siempre empleado (FK). **Supervisor:** usuario logueado (sin campo). **Ticket:**
   FK obligatorio al módulo tickets. **Local:** automático del ticket (cliente + sucursal/sede).

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
- **Acceso del técnico:** DOS vías, ambas apuntan al mismo depósito (por `empleado_id`):
  1. **Link único por token** (sin login), ruta pública `rendir/{token}` (patrón `_setup/{token}`).
     Universal y sin fricción; sirve para cualquier técnico, tenga usuario o no.
  2. **Pestaña "Rendiciones" en "Mi espacio"** (portal): para los técnicos que **sí** tienen
     usuario. Y sí tienen, porque para **marcar asistencia GPS** y **tomar/avanzar tickets**
     ya se loguean en el portal (verificado: `portal/index` exige `auth()->user()->empleado`).
  - **Continuidad garantizada:** como el depósito se guarda contra `empleado_id` (no contra el
    usuario), si un técnico rinde **sin** usuario (por link) y luego se le **crea el usuario**
    (módulo Usuarios enlaza `empleados.user_id`), su **historial de rendiciones aparece solo**
    en "Mi espacio" — igual que hereda asistencia/tickets/documentos. Nada que migrar.
  - El **supervisor** usa el login normal de la app.
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
   UI de referencia recibida: panel supervisor + pantalla técnico (capturas 2026-07-14).
   Sistema en vivo de referencia: https://rendicionesgoogle-app.onrender.com/rendir/{token}
2. **Ticket:** RESUELTO → **enlazar siempre** (FK al ticket del módulo tickets; obligatorio).
3. **Local:** RESUELTO → **automático del ticket** (se muestra cliente + sucursal/sede del
   ticket elegido; se guarda snapshot para el histórico). No hay campo Local aparte.
4. **Técnicos:** RESUELTO → **siempre empleado registrado** (FK `empleado_id`; usa su celular/DNI).
5. **Supervisores:** RESUELTO → **usuario logueado** (`supervisor_id = auth()->id()`; sin campo).
6. **Datos de negocio:** RESUELTO →
   - **Empresa dueña de la caja (encabezado PDF):** GDS INFRAESTRUCTURA SAC · RUC **20551555187**.
   - **Pie "Elaborado por":** PercyTech - Solutions · RUC **10463288271** · WhatsApp soporte **966804286**.
   - **Cuentas de devolución (empresa):** Interbank **169-30010821-43** · BCP **191-98435080-71**
     (+ nota "si te lo depositó tu supervisor, coordina con él").
   - **Logos:** SÍ. GDS en el **encabezado**, PercyTech en el **pie**. Archivos van en
     `public/images/rendiciones/logo-gds.png` y `logo-percytech.png` (el usuario los deja ahí;
     idealmente PNG con fondo transparente).
7. **Migración de datos:** RESUELTO → **arranca limpio**, sin Google (ni Drive ni Sheets).

**Solo queda "nice to have" (no bloquea construir):** muestra real del PDF para afinarlo, y
si quieren **logo de GDS** en la Hoja Resumen.

### Ajustes al formulario "Registrar Depósito" (según lo decidido)
- **Técnico Beneficiario:** select de empleados (muestra su celular/DNI). Sin alta rápida
  (los empleados se gestionan en su módulo).
- **Supervisor:** desaparece del formulario (= usuario logueado).
- **Nº Ticket:** select de tickets (idealmente filtrado a los del técnico elegido).
- **Local:** solo lectura, se autocompleta del ticket (cliente + sucursal/sede).
- **Monto, Fecha, Voucher:** igual (voucher → SharePoint CONTABILIDAD/Rend_Sistemas).

---

## 7. Plan por fases

- **Fase A — Datos y estados:** ✅ HECHA (migraciones + modelos + máquina de estados + permisos).
- **Fase B — Panel supervisor (Livewire):** ✅ HECHA. Ruta `/rendiciones`, KPIs, registrar
  depósito (ticket→local automático), pestañas, buscador, filtro por técnico, acciones
  Aprobar/Rechazar/Anular/Ampliar + Link/WhatsApp/Detalles. Voucher local (SharePoint = Fase D).
- **Fase C — Acceso del técnico:** (1) vista pública por token `rendir/{token}` y (2) pestaña
  "Rendiciones" en el portal "Mi espacio". Subir comprobantes + liquidar (Exacto/Devolución/
  Reembolso). Ambas vían por `empleado_id`.
- **Fase D — SharePoint multi-destino:** vouchers/comprobantes → CONTABILIDAD/Rend_Sistemas.
- **Fase E — PDF Hoja Resumen** (al aprobar; logos GDS/PercyTech) + tests de los flujos
  críticos (§15 de ADAPTACION_PHP_LARAGON.md).

---

*Este plan se irá completando con el contexto que aporte el usuario.*
