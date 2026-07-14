# Guía de Adaptación: Sistema de Rendiciones → PHP (Laragon)

> **Propósito de este documento:** Entregar a una IA (o desarrollador) todo el contexto necesario para reimplementar la aplicación **rendicionesGoogle** en **PHP sobre Laragon**, manteniendo la misma funcionalidad de negocio, flujos y experiencia de usuario.

---

## 1. Resumen ejecutivo

### 1.1 Qué hace la aplicación

Sistema web para **rendición de cuentas de caja chica** entregada a técnicos de campo:

- El **supervisor** registra depósitos de dinero, sube vouchers y genera un enlace único por WhatsApp.
- El **técnico** accede sin contraseña (solo con token), sube comprobantes de gasto y cierra la rendición.
- El **supervisor** valida (aprueba / observa / anula), puede ampliar montos y al aprobar se genera una **Hoja Resumen PDF** archivada en Google Drive.

### 1.2 Stack actual (origen)

| Capa | Tecnología |
|------|------------|
| Backend | Node.js 18+, Express 4 (ESM), Multer |
| Frontend | React 19 + Vite + TypeScript + React Router |
| Base de datos | **Google Sheets** (6 pestañas) |
| Archivos | **Google Drive** (carpeta por depósito) |
| PDF | pdfkit (Node) |
| Auth supervisor | Contraseña compartida en header `x-admin-key` |
| Auth técnico | Token secreto en URL (`/rendir/:token`) |

### 1.3 Stack objetivo recomendado (Laragon)

| Capa | Tecnología recomendada |
|------|------------------------|
| Entorno | **Laragon** (Apache/Nginx + PHP 8.2+ + MySQL 8) |
| Framework | **Laravel 11** (recomendado; Laragon lo soporta nativamente) |
| Base de datos | **MySQL** (reemplaza Google Sheets; más natural en Laragon) |
| Archivos | **Opción A:** Google Drive (mantener integración) · **Opción B:** almacenamiento local `storage/app/rendiciones/` |
| PDF | **dompdf** o **barryvdh/laravel-dompdf** / **TCPDF** |
| Frontend | **Opción A:** Mantener React compilado como SPA · **Opción B:** Blade + Alpine.js/Livewire |

> **Decisión clave para la IA implementadora:** El usuario pidió adaptación a PHP/Laragon. Se recomienda **Laravel + MySQL + almacenamiento local de archivos** para simplicidad en desarrollo local. Si el cliente exige conservar Google Sheets/Drive, ver sección 12.

---

## 2. Roles y permisos

| Rol | Ruta | Autenticación | Permisos |
|-----|------|---------------|----------|
| **Supervisor** | `/` (panel admin) | Contraseña compartida `SUPERVISOR_PASSWORD` | CRUD depósitos, técnicos, supervisores; aprobar/rechazar/anular/ampliar |
| **Técnico** | `/rendir/{token}` | Token UUID en URL (sin login) | Ver su depósito, subir comprobantes, liquidar rendición |
| **Público** | `/api/status` | Ninguna | Health check |

### 2.1 Autenticación del supervisor (implementación actual)

```
Header: x-admin-key: {SUPERVISOR_PASSWORD}
```

- El frontend guarda la clave en `localStorage` bajo la clave `adminKey`.
- Si el servidor responde `401`, el frontend limpia la sesión y muestra login.
- Si `SUPERVISOR_PASSWORD` no está configurada en el servidor → respuesta `500`.
- **No hay usuarios individuales**, JWT ni sesiones server-side: es una clave compartida.

### 2.2 Autenticación del técnico

- Al crear un depósito se genera: `crypto.randomBytes(16).toString('hex')` → token de 32 caracteres hex.
- URL de rendición: `{APP_URL}/rendir/{token}`
- Cualquiera con el token puede acceder; la seguridad es por obscuridad del enlace.

---

## 3. Máquina de estados (CRÍTICO — respetar exactamente)

```
Supervisor registra depósito (+ voucher inicial)
        │
        ▼
   ● RINDIENDO ──── técnico sube comprobantes ──── supervisor puede AMPLIAR o ANULAR
        │
        │ técnico finaliza liquidación:
        │    Exacto (dif=0) · Devolución (sobró) · Reembolso (gastó de más)
        ▼
   ● POR REVISAR ── supervisor valida
        │
   ┌────┼──────────────────┐
   ▼    ▼                  ▼
 APRUEBA  RECHAZA         ANULAR
   │      (motivo)          │
   │        │               ▼
   │        ▼            ● ANULADO
   │     ● OBSERVADO ──┐
   │        │           └─ técnico corrige y reenvía ─► POR REVISAR
   ▼
 Reembolso → supervisor sube voucher → ● FINALIZADO (+ PDF)
 Exacto/Devolución → directo         → ● FINALIZADO (+ PDF)
```

### 3.1 Tabla de estados

| Estado | Valor exacto en BD | Significado | Acciones permitidas |
|--------|-------------------|-------------|---------------------|
| Rindiendo | `Rindiendo` | Técnico cargando comprobantes | Ampliar, Anular |
| Por Revisar | `Por Revisar` | Técnico envió rendición | Aprobar, Rechazar, Anular |
| Finalizado | `Finalizado` | Cerrada y aprobada | Solo lectura |
| Observado | `Observado` | Rechazada con motivo | Técnico edita; Ampliar, Anular |
| Anulado | `Anulado` | Cancelada por error | Solo lectura |

### 3.2 Reglas de transición (validar en backend)

| Acción | Estado previo requerido | Estado resultante |
|--------|------------------------|-------------------|
| Crear depósito | — | `Rindiendo` |
| Liquidar (técnico) | `Rindiendo` o `Observado` | `Por Revisar` |
| Aprobar | `Por Revisar` | `Finalizado` |
| Rechazar | `Por Revisar` | `Observado` |
| Anular | `Rindiendo`, `Por Revisar`, `Observado` | `Anulado` |
| Ampliar | `Rindiendo`, `Observado` | (sin cambio de estado) |

### 3.3 Tipos de liquidación (`Estado_Liquidacion`)

| Valor | Condición | Comportamiento |
|-------|-----------|----------------|
| `Exacto` | `Monto_Depositado - Total_Gastado == 0` | Cierre directo |
| `Devolucion` | Diferencia > 0 (sobró dinero) | Técnico **debe** subir voucher de devolución |
| `Reembolso` | Diferencia < 0 (gastó de más) | Al aprobar, supervisor **debe** subir voucher de reembolso |

---

## 4. Modelo de datos

### 4.1 Modelo actual (Google Sheets — referencia)

El sistema original usa 6 pestañas. La IA debe replicar esta lógica en MySQL.

#### Tabla `depositos` (pestaña Depositos, columnas A:R)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `item` | INT PK AUTO | Correlativo (Item) |
| `tecnico` | VARCHAR | Nombre del técnico (snapshot histórico) |
| `ticket` | VARCHAR | Número de ticket de trabajo |
| `local` | VARCHAR | Local/lugar del trabajo |
| `monto` | DECIMAL(10,2) | **Total entregado** (inicial + ampliaciones) |
| `dia` | DATE | Fecha del depósito inicial |
| `token` | VARCHAR(64) UNIQUE | Token secreto del enlace |
| `link_rendicion` | VARCHAR | URL completa `/rendir/{token}` |
| `drive_folder_id` | VARCHAR | ID carpeta Drive (o path local si no usa Drive) |
| `estado` | ENUM | Ver sección 3 |
| `telefono` | VARCHAR | Celular del técnico (snapshot) |
| `observaciones` | TEXT | Motivo de rechazo/anulación |
| `tecnico_id` | INT FK | Referencia a tabla técnicos |
| `supervisor` | VARCHAR | Nombre supervisor (snapshot) |
| `supervisor_id` | INT FK | Referencia a tabla supervisores |
| `fecha_rendido` | DATE NULL | Fecha VB técnico (al liquidar) |
| `fecha_aprobado` | DATE NULL | Fecha VB supervisor (al aprobar) |
| `resumen_pdf` | VARCHAR NULL | URL del PDF resumen |

> **Importante:** `tecnico`, `supervisor`, `telefono` se guardan como **snapshot** al momento del depósito para que el histórico no cambie si se edita la tabla maestra.

> **Cálculo monto inicial:** `monto - SUM(ampliaciones.monto)`

#### Tabla `detalle_gastos` (pestaña Detalle_Gastos)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `item_gasto` | INT PK AUTO | Correlativo |
| `item_deposito` | INT FK | Referencia al depósito |
| `tipo_comprobante` | VARCHAR | Boleta, Factura, Recibo de Honorarios, Declaración Jurada, Otros |
| `nro_comprobante` | VARCHAR | Número de documento |
| `monto_gasto` | DECIMAL(10,2) | Monto del gasto |
| `fecha_comprobante` | DATE | Fecha del comprobante |
| `drive_file_id` | VARCHAR | URL webViewLink del archivo (o path local) |

#### Tabla `liquidacion` (pestaña Liquidacion)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `item_deposito` | INT PK/FK UNIQUE | Un registro por depósito |
| `monto_depositado` | DECIMAL(10,2) | Monto total entregado al liquidar |
| `total_gastado` | DECIMAL(10,2) | Suma de comprobantes |
| `diferencia` | DECIMAL(10,2) | depositado - gastado (puede ser negativo) |
| `estado_liquidacion` | ENUM | Exacto, Devolucion, Reembolso |
| `comprobante_liquidacion_id` | VARCHAR NULL | URL voucher devolución (técnico) o reembolso (supervisor) |

#### Tabla `tecnicos` (pestaña Tecnicos)

| Campo | Tipo |
|-------|------|
| `item` | INT PK AUTO |
| `nombre` | VARCHAR |
| `celular` | VARCHAR |
| `dni` | VARCHAR |
| `estado` | ENUM('Activo','Inactivo') DEFAULT 'Activo' |

#### Tabla `supervisores` (pestaña Supervisores)

| Campo | Tipo |
|-------|------|
| `item` | INT PK AUTO |
| `nombre` | VARCHAR |
| `celular` | VARCHAR |
| `estado` | ENUM('Activo','Inactivo') DEFAULT 'Activo' |

#### Tabla `ampliaciones` (pestaña Ampliaciones)

| Campo | Tipo |
|-------|------|
| `item` | INT PK AUTO |
| `item_deposito` | INT FK |
| `monto` | DECIMAL(10,2) |
| `fecha` | DATE |
| `motivo` | VARCHAR |
| `drive_file_id` | VARCHAR | URL del voucher adicional |
| `supervisor` | VARCHAR | Snapshot nombre |
| `supervisor_id` | INT FK |

### 4.2 Migraciones Laravel sugeridas (orden)

```
1. create_tecnicos_table
2. create_supervisores_table
3. create_depositos_table
4. create_detalle_gastos_table
5. create_liquidacion_table
6. create_ampliaciones_table
```

### 4.3 Seeders opcionales

- 2-3 técnicos de prueba
- 1-2 supervisores de prueba
- 1 depósito en estado `Rindiendo` con token conocido para testing

---

## 5. API REST — Especificación completa

**Base URL en Laragon (ejemplo):** `http://rendiciones.test/api`

**Formato de errores:**
```json
{ "error": "Mensaje descriptivo en español" }
```

**Formato de éxito:** JSON según endpoint.

### 5.1 Middleware de admin

```php
// Pseudocódigo Laravel
function requireAdmin(Request $request) {
    $configured = env('SUPERVISOR_PASSWORD');
    if (!$configured) return response()->json(['error' => 'Autenticación no configurada...'], 500);
    $provided = $request->header('x-admin-key');
    if (!$provided || $provided !== $configured) {
        return response()->json(['error' => 'No autorizado.'], 401);
    }
}
```

---

### POST `/api/depositos` — Admin

**Crea depósito, carpeta de archivos y sube voucher inicial.**

**Content-Type:** `multipart/form-data`

**Campos del formulario:**

| Campo | Requerido | Tipo |
|-------|-----------|------|
| Tecnico | Sí | string (nombre) |
| Tecnico_Id | No | string/int |
| Telefono | No | string |
| Supervisor | No | string |
| Supervisor_Id | No | string |
| Ticket | Sí | string |
| Local | Sí | string |
| Monto | Sí | decimal |
| Dia | Sí | date (YYYY-MM-DD) |
| voucher | No* | file (imagen/PDF) |

*En la UI es requerido; el backend original lo acepta opcional pero la UI lo exige.

**Lógica del servidor:**

1. Validar campos obligatorios → `400` si faltan.
2. Generar token: `bin2hex(random_bytes(16))` (32 chars hex).
3. Construir link: `{APP_URL}/rendir/{token}`.
4. Crear carpeta: `TICKET-{Ticket}_{Tecnico}_{Local}` (espacios → `_`).
5. Subir voucher como `Voucher_Deposito_{Ticket}.{ext}`.
6. Insertar fila con `estado = 'Rindiendo'`, `monto` con 2 decimales.
7. Limpiar archivo temporal tras subida.
8. Responder `201` con el objeto depósito creado (incluye `Item`).

**Respuesta 201:**
```json
{
  "Item": "1",
  "Tecnico": "Juan Pérez",
  "Ticket": "10294",
  "Local": "Centro",
  "Monto": "500.00",
  "Dia": "2026-07-14",
  "Token": "a1b2c3...",
  "LinkRendicion": "http://rendiciones.test/rendir/a1b2c3...",
  "DriveFolderId": "abc123",
  "Estado": "Rindiendo",
  "Telefono": "987654321",
  "Observaciones": "",
  "Tecnico_Id": "1",
  "Supervisor": "María López",
  "Supervisor_Id": "1"
}
```

> **Nota:** Los nombres de campos en JSON usan **PascalCase** como en el original (compatibilidad con frontend React existente).

---

### GET `/api/depositos` — Admin

Lista todos los depósitos ordenados por `item` DESC (recomendado).

**Respuesta 200:** Array de objetos depósito.

---

### GET `/api/depositos/{itemId}` — Admin

**Respuesta 200:**
```json
{
  "deposito": { /* objeto depósito */ },
  "gastos": [ /* array */ ],
  "liquidacion": { /* objeto o null */ },
  "ampliaciones": [ /* array */ ]
}
```

**404** si no existe.

---

### GET `/api/depositos/token/{token}` — Público

Igual estructura que GET por itemId. Usado por el técnico.

**404:** `{ "error": "Depósito no encontrado o el enlace es inválido." }`

---

### POST `/api/depositos/{itemId}/comprobantes` — Público

**Content-Type:** `multipart/form-data`

| Campo | Requerido |
|-------|-----------|
| Tipo_Comprobante | Sí |
| Nro_Comprobante | No (default 'S/N') |
| Monto_Gasto | Sí |
| Fecha_Comprobante | Sí |
| comprobante | Sí (file) |

**Lógica:**

1. Verificar depósito existe.
2. Nombre archivo Drive: `Gasto_{Tipo}_{Nro}.{ext}` (espacios en Nro → `_`).
3. Guardar en carpeta del depósito.
4. Insertar en `detalle_gastos`; `DriveFileId` = URL pública del archivo.
5. Responder `201` con gasto creado.

---

### POST `/api/depositos/{itemId}/liquidar` — Público

**Content-Type:** `multipart/form-data`

| Campo | Requerido |
|-------|-----------|
| Monto_Depositado | Sí |
| Total_Gastado | Sí |
| Diferencia | Sí |
| Estado_Liquidacion | Sí (Exacto/Devolucion/Reembolso) |
| voucherDevolucion | Solo si Devolucion |

**Lógica:**

1. Si hay voucher devolución → subir como `Voucher_Devolucion_Saldo_{Ticket}.{ext}`.
2. Upsert en tabla `liquidacion`.
3. Actualizar depósito: `estado = 'Por Revisar'`, `fecha_rendido = hoy (YYYY-MM-DD)`.
4. Responder: `{ "message": "Rendición enviada para revisión.", "estado": "Por Revisar" }`

---

### POST `/api/depositos/{itemId}/aprobar` — Admin

**Content-Type:** `multipart/form-data` (si Reembolso)

**Lógica:**

1. Depósito debe estar en `Por Revisar` → sino `400`.
2. Si liquidación es `Reembolso`:
   - Requiere file `voucherReembolso` → sino `400`.
   - Subir como `Voucher_Reembolso_{Ticket}.{ext}`.
   - Actualizar `liquidacion.comprobante_liquidacion_id`.
3. Actualizar depósito: `estado = 'Finalizado'`, `fecha_aprobado = hoy`.
4. **Generar PDF resumen** (try/catch — fallo NO debe revertir aprobación):
   - Generar PDF con datos completos.
   - Subir como `Resumen_Rendicion_{Ticket}.pdf`.
   - Guardar URL en `deposito.resumen_pdf`.
5. Responder: `{ "message": "Rendición aprobada.", "estado": "Finalizado" }`

---

### POST `/api/depositos/{itemId}/rechazar` — Admin

**Content-Type:** `application/json`

```json
{ "motivo": "Falta comprobante del taxi" }
```

**Lógica:** `estado = 'Observado'`, `observaciones = motivo`.

---

### POST `/api/depositos/{itemId}/anular` — Admin

**Content-Type:** `application/json`

```json
{ "motivo": "Depósito duplicado por error" }
```

**Lógica:** Solo estados `Rindiendo`, `Por Revisar`, `Observado`. Resultado: `Anulado`.

---

### POST `/api/depositos/{itemId}/ampliar` — Admin

**Content-Type:** `multipart/form-data`

| Campo | Requerido |
|-------|-----------|
| monto | Sí (> 0) |
| motivo | No |
| Fecha | No (default hoy) |
| Supervisor | No |
| Supervisor_Id | No |
| voucher | Sí |

**Lógica:**

1. Solo estados `Rindiendo` u `Observado`.
2. Contar ampliaciones previas → correlativo `n`.
3. Subir voucher: `Voucher_Deposito_Adicional_{n}_{Ticket}.{ext}`.
4. Insertar ampliación.
5. Actualizar `deposito.monto += monto`.
6. Responder `201`:
```json
{
  "message": "Depósito adicional registrado.",
  "montoAdicional": "100.00",
  "nuevoTotal": "600.00"
}
```

---

### GET/POST `/api/tecnicos` — Admin

**GET:** Lista técnicos.

**POST JSON:**
```json
{ "Nombre": "Juan Pérez", "Celular": "987654321", "DNI": "12345678" }
```
- `Nombre` obligatorio.
- Responder `201` con técnico creado (`Estado: "Activo"` por defecto).

---

### GET/POST `/api/supervisores` — Admin

**POST JSON:**
```json
{ "Nombre": "María López", "Celular": "912345678" }
```

---

### GET `/api/status` — Público

```json
{
  "status": "online",
  "googleConfigured": true
}
```

> En versión MySQL, cambiar `googleConfigured` por algo como `databaseConfigured: true` o mantener por compatibilidad.

---

## 6. Almacenamiento de archivos

### 6.1 Estructura en Google Drive (original)

```
[Carpeta Raíz GOOGLE_DRIVE_PARENT_FOLDER_ID]/
  └── TICKET-{Ticket}_{Tecnico}_{Local}/
        ├── Voucher_Deposito_{Ticket}.jpg
        ├── Voucher_Deposito_Adicional_1_{Ticket}.pdf
        ├── Gasto_Boleta_F001-123.jpg
        ├── Voucher_Devolucion_Saldo_{Ticket}.jpg
        ├── Voucher_Reembolso_{Ticket}.jpg
        └── Resumen_Rendicion_{Ticket}.pdf
```

- Carpetas y archivos se hacen **públicos** (anyone with link, role reader).
- Se guarda `webViewLink` en BD, no el file ID puro (excepto `DriveFolderId` del depósito).

### 6.2 Estructura local recomendada (Laragon)

```
storage/app/rendiciones/
  └── {deposito_item}_{ticket}/
        ├── voucher_deposito.jpg
        ├── gastos/
        ├── ampliaciones/
        ├── liquidacion/
        └── resumen.pdf
```

**Servir archivos:** Ruta pública vía symlink `storage:link` o endpoint `/storage/rendiciones/...`.

**En BD:** Guardar URL absoluta o path relativo accesible, ej: `http://rendiciones.test/storage/rendiciones/1_10294/gastos/boleta.jpg`

### 6.3 Validaciones de upload

- Tipos aceptados: `image/*`, `application/pdf`
- Tamaño máximo recomendado: 10 MB (configurar `upload_max_filesize` y `post_max_size` en php.ini de Laragon)
- Guardar temporalmente, subir/mover, eliminar temp (como `cleanLocalFile` en Node)

---

## 7. Generación de PDF (Hoja Resumen)

### 7.1 Cuándo se genera

Solo al **aprobar** una rendición en estado `Por Revisar`.

### 7.2 Contenido exacto del PDF

**Título:** `Hoja Resumen de Rendición`

**Secciones:**

1. **Encabezado**
   - Fecha emisión: `fecha_aprobado`
   - Ticket, Técnico, Local, Supervisor

2. **Depósitos**
   - Depósito inicial: `monto_total - sum(ampliaciones)` con fecha `dia`
   - Cada ampliación: monto, fecha, motivo
   - **Total entregado:** `deposito.monto`

3. **Rendición (comprobantes)**
   - Cada gasto: `{Tipo} {Nro} - S/ {monto} - {fecha}`
   - **Total gastado:** suma gastos

4. **Vuelto o Reembolso**
   - Si `Reembolso`: "Reembolso al técnico: S/ {abs(diferencia)}"
   - Si `Devolucion`: "Vuelto devuelto por el técnico: S/ {abs(diferencia)}"
   - Si `Exacto`: "Balance exacto: sin vuelto ni reembolso."

5. **Visto Bueno (VB°)**
   - VB Técnico: `{tecnico}` ({fecha_rendido})
   - VB Supervisor: `{supervisor}` ({fecha_aprobado})

6. **Pie de página**
   - `Elaborado por PercyTech · RUC 10463288271`
   - `Soporte: +51 966 804 286 (WhatsApp)`

### 7.3 Formato moneda

```php
function money($n) {
    $v = floatval($n);
    return 'S/ ' . number_format(is_nan($v) ? 0 : $v, 2, '.', '');
}
```

### 7.4 Estilo visual

- Tamaño A4, márgenes ~50pt
- Títulos de sección en azul `#1d63ed`, bold
- Línea separadora gris `#cccccc`
- Fuente: Helvetica o equivalente sans-serif

---

## 8. Frontend — Pantallas y comportamiento

### 8.1 Rutas

| Ruta | Componente | Descripción |
|------|------------|-------------|
| `/` | Dashboard + Login | Panel supervisor |
| `/rendir/:token` | RendicionForm | Vista técnico |

### 8.2 Opciones de implementación frontend en PHP

**Opción A — Mantener React (menor esfuerzo UI):**
1. Compilar React con `npm run build`.
2. Copiar `frontend/dist/*` a `public/`.
3. Laravel route catch-all sirve `index.html` excepto `/api/*`.
4. Cambiar `VITE_API_URL` a `http://rendiciones.test`.

**Opción B — Reescribir en Blade:**
Replicar las 3 vistas con el mismo layout y clases CSS del archivo `frontend/src/index.css`.

### 8.3 Dashboard (Supervisor) — Funcionalidades

**KPIs (calculados client-side):**
- Total Depositado: suma `Monto` de todos los depósitos
- Total Finalizado: suma `Monto` donde `Estado === 'Finalizado'`
- Cuentas en Proceso: count donde estado NOT IN (`Finalizado`, `Anulado`)

**Formulario registrar depósito:**
- Select técnicos (activos) + botón ➕ alta rápida
- Select supervisores + botón ➕
- Al elegir técnico → mostrar celular y DNI
- Campos: ticket, local, monto, fecha, voucher (file)
- POST multipart a `/api/depositos`

**Lista con pestañas:**

| Tab | Estados incluidos |
|-----|-------------------|
| Pendientes (default) | Rindiendo, Por Revisar, Observado |
| Por revisar | Por Revisar |
| Finalizados | Finalizado |
| Anulados | Anulado |
| Todos | todos |

**Filtros:** texto libre (ticket/local/técnico/supervisor) + select técnico + botón Limpiar.

**Acciones por fila:**
- Copiar Link
- WhatsApp (requiere teléfono)
- Detalles (modal)
- Aprobar / Rechazar (solo Por Revisar)
- Ampliar (Rindiendo, Observado)
- Anular (Rindiendo, Por Revisar, Observado)
- Abrir carpeta Drive/archivos

**Modal Detalles:** desglose depósitos, comprobantes, liquidación, link PDF.

**Modal Aprobar:** si Reembolso → exige voucher file.

**Modal Rechazar/Anular:** textarea motivo obligatorio.

**Modal Ampliar:** monto, fecha, supervisor, motivo, voucher.

**Modal WhatsApp post-acción:** muestra mensaje pre-escrito (evita popup blockers).

### 8.4 RendicionForm (Técnico)

**Estados editables:** `Rindiendo`, `Observado`

**Cuando NO editable:** muestra tarjeta de cierre según estado.

**Balance en tiempo real:**
```javascript
totalGasto = sum(gastos.monto_gasto)
diferencia = montoOriginal - totalGasto
// > 0 → Devolucion, < 0 → Reembolso, == 0 → Exacto
```

**Devolución — cuentas bancarias hardcodeadas (IMPORTANTE):**
```javascript
COMPANY_ACCOUNTS = [
  { bank: 'Interbank', number: '169-30010821-43' },
  { bank: 'BCP', number: '191-98435080-71' },
]
```
Mostrar con botón copiar. Incluir instrucciones de devolver a empresa o supervisor.

**Tipos de comprobante (select):**
- Boleta, Factura, Recibo de Honorarios, Declaración Jurada, Otros

**Banner Observado:** muestra `deposito.Observaciones` y permite re-editar.

**Banner Anulado:** solo lectura con motivo.

### 8.5 Login

- Input password
- Valida contra GET `/api/depositos` con header `x-admin-key`
- Guarda en localStorage

### 8.6 WhatsApp

```javascript
function buildWhatsappLink(phone, message) {
  const clean = phone.replace(/\D/g, '');
  const withCC = clean.startsWith('51') ? clean : '51' + clean;
  return `https://wa.me/${withCC}?text=${encodeURIComponent(message)}`;
}
```

**Plantillas de mensajes:**

1. **Envío link rendición:**
```
Hola {Tecnico}

Te comparto el enlace para rendir tu depósito

Ticket {Ticket}
Local {Local}
Monto S/ {Monto}

Ingresa y sube tus comprobantes aquí
{LinkRendicion}
```

2. **Rechazo:**
```
Hola {Tecnico}

Tu rendición del ticket {Ticket} fue OBSERVADA

Motivo {motivo}

Por favor corrígela e ingresa nuevamente
{LinkRendicion}
```

3. **Reembolso aprobado:**
```
Hola {Tecnico}

Se te reembolsó S/ {monto} del ticket {Ticket}

Puedes ver el comprobante e ingresar aquí
{LinkRendicion}
```

4. **Depósito adicional:**
```
Hola {Tecnico}

Se te depositó S/ {montoAdicional} adicionales para el ticket {Ticket}
Nuevo total entregado S/ {nuevoTotal}
Motivo {motivo}

Revisa tu rendición aquí
{LinkRendicion}
```

### 8.7 Estilos CSS

El diseño usa variables CSS en `:root` (tema claro estilo Docker blue). Archivo fuente: `frontend/src/index.css`.

Clases principales:
- `.glass-card`, `.glass-input`, `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-success`
- `.badge`, `.badge-rindiendo`, `.badge-por-revisar`, `.badge-finalizado`, `.badge-observado`, `.badge-anulado`
- `.navbar`, `.app-footer`, `.custom-table`, `.grid-stats`, `.grid-two-cols`
- `.animate-fade-in`, `.spinner`

**Copiar íntegramente** `index.css` y `App.css` al proyecto PHP.

---

## 9. Variables de entorno

### 9.1 Original (Node `.env`)

```env
PORT=3002
CORS_ORIGIN=http://localhost:5173
SUPERVISOR_PASSWORD=cambia_esta_clave

GOOGLE_SPREADSHEET_ID=...
GOOGLE_DRIVE_PARENT_FOLDER_ID=...

# OAuth2 (opcional, cuenta Gmail personal)
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REFRESH_TOKEN=...

# O Service Account
GOOGLE_APPLICATION_CREDENTIALS=credentials.json
GOOGLE_SERVICE_ACCOUNT_EMAIL=...
GOOGLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n..."
```

### 9.2 Laravel `.env` propuesto

```env
APP_NAME=Rendiciones
APP_URL=http://rendiciones.test
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rendiciones
DB_USERNAME=root
DB_PASSWORD=

SUPERVISOR_PASSWORD=cambia_esta_clave

# Solo si se mantiene Google Drive
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google/credentials.json
GOOGLE_DRIVE_PARENT_FOLDER_ID=

FILESYSTEM_DISK=local
# o 'google' si implementan driver Drive
```

---

## 10. Estructura de proyecto Laravel propuesta

```
rendiciones-laragon/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DepositoController.php
│   │   │   ├── TecnicoController.php
│   │   │   ├── SupervisorController.php
│   │   │   └── StatusController.php
│   │   └── Middleware/
│   │       └── RequireAdmin.php
│   ├── Models/
│   │   ├── Deposito.php
│   │   ├── DetalleGasto.php
│   │   ├── Liquidacion.php
│   │   ├── Tecnico.php
│   │   ├── Supervisor.php
│   │   └── Ampliacion.php
│   └── Services/
│       ├── FileStorageService.php      # local o Drive
│       ├── ResumenPdfService.php
│       └── DepositoService.php         # lógica de negocio/estados
├── database/migrations/
├── routes/
│   ├── api.php                         # todos los endpoints /api/*
│   └── web.php                         # SPA fallback o Blade views
├── storage/app/rendiciones/            # archivos subidos
├── public/                             # React dist o assets
├── resources/views/                    # si usa Blade
└── .env
```

### 10.1 Routes Laravel (`routes/api.php`)

```php
Route::get('/status', [StatusController::class, 'index']);

Route::get('/depositos/token/{token}', [DepositoController::class, 'showByToken']);
Route::post('/depositos/{item}/comprobantes', [DepositoController::class, 'addComprobante']);
Route::post('/depositos/{item}/liquidar', [DepositoController::class, 'liquidar']);

Route::middleware('admin')->group(function () {
    Route::get('/depositos', [DepositoController::class, 'index']);
    Route::post('/depositos', [DepositoController::class, 'store']);
    Route::get('/depositos/{item}', [DepositoController::class, 'show']);
    Route::post('/depositos/{item}/aprobar', [DepositoController::class, 'aprobar']);
    Route::post('/depositos/{item}/rechazar', [DepositoController::class, 'rechazar']);
    Route::post('/depositos/{item}/anular', [DepositoController::class, 'anular']);
    Route::post('/depositos/{item}/ampliar', [DepositoController::class, 'ampliar']);
    Route::get('/tecnicos', [TecnicoController::class, 'index']);
    Route::post('/tecnicos', [TecnicoController::class, 'store']);
    Route::get('/supervisores', [SupervisorController::class, 'index']);
    Route::post('/supervisores', [SupervisorController::class, 'store']);
});
```

---

## 11. Configuración Laragon paso a paso

### 11.1 Crear proyecto

```bash
cd C:\laragon\www
composer create-project laravel/laravel rendiciones
cd rendiciones
```

### 11.2 Virtual host

En Laragon: **Menu → Apache → sites-enabled → rendiciones.test**

O automático si la carpeta está en `C:\laragon\www\rendiciones`.

### 11.3 Base de datos

```sql
CREATE DATABASE rendiciones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
php artisan storage:link
```

### 11.4 PHP.ini (Laragon)

Ajustar en `C:\laragon\bin\php\php-8.x.x-win32-vs16-x64\php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 120
```

### 11.5 CORS (si frontend separado)

En Laravel 11, publicar/configurar `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_origins' => [env('CORS_ORIGIN', 'http://rendiciones.test')],
'allowed_headers' => ['*'],
```

Si se sirve todo desde el mismo dominio, CORS no es necesario.

---

## 12. Alternativa: mantener Google Sheets/Drive en PHP

Si el cliente **no quiere MySQL** y prefiere conservar Sheets:

### 12.1 Librería PHP

```bash
composer require google/apiclient:"^2.15"
```

### 12.2 Servicios a portar

| Node (original) | PHP equivalente |
|---------------|-----------------|
| `googleAuth.js` | Clase `GoogleAuthService` con OAuth2 o Service Account |
| `sheetsService.js` | Clase `SheetsService` — mismos métodos exportados |
| `driveService.js` | Clase `DriveService` — `createFolder()`, `uploadFile()` |
| `pdfService.js` | Clase `ResumenPdfService` con dompdf/TCPDF |

### 12.3 Inicialización al boot

El Node ejecuta `initializeSheets()` al arrancar. En Laravel:

```php
// AppServiceProvider::boot() o comando artisan rendiciones:init-sheets
```

Debe crear las 6 pestañas si no existen y migrar headers de Depositos.

### 12.4 Mapeo Google Sheets API (PHP)

```php
$sheets = new Google_Service_Sheets($client);
$response = $sheets->spreadsheets_values->get($spreadsheetId, 'Depositos!A:R');
$values = $response->getValues();
// rowsToObjects() — misma lógica que JS
```

---

## 13. Compatibilidad con frontend React existente

Para **reutilizar el frontend sin reescribirlo**:

1. Mantener **exactamente** los mismos endpoints y nombres de campos JSON (PascalCase).
2. Mantener códigos HTTP: 200, 201, 400, 401, 404, 500.
3. Mantener nombres de campos multipart: `voucher`, `comprobante`, `voucherDevolucion`, `voucherReembolso`.
4. Compilar React apuntando API a Laragon:
   ```env
   VITE_API_URL=http://rendiciones.test
   ```
5. Copiar build a `public/`.
6. Agregar fallback route en Laravel para SPA.

---

## 14. Plan de implementación sugerido (orden para la IA)

### Fase 1 — Infraestructura
- [ ] Crear proyecto Laravel en Laragon
- [ ] Configurar `.env`, base de datos
- [ ] Crear migraciones y modelos Eloquent
- [ ] Middleware `RequireAdmin`

### Fase 2 — API core
- [ ] CRUD técnicos y supervisores
- [ ] POST/GET depósitos
- [ ] FileStorageService (local)
- [ ] GET depósito por token e itemId con relaciones

### Fase 3 — Flujo técnico
- [ ] POST comprobantes
- [ ] POST liquidar con validaciones de estado

### Fase 4 — Flujo supervisor
- [ ] Aprobar / rechazar / anular / ampliar
- [ ] Validaciones de máquina de estados

### Fase 5 — PDF
- [ ] ResumenPdfService con contenido exacto
- [ ] Integrar en aprobación (try/catch)

### Fase 6 — Frontend
- [ ] Opción A: servir React compilado
- [ ] Opción B: vistas Blade

### Fase 7 — Pruebas
- [ ] Flujo completo: crear → rendir → aprobar
- [ ] Flujo observado → corregir → reenviar
- [ ] Flujo reembolso con voucher supervisor
- [ ] Flujo devolución con voucher técnico
- [ ] Ampliación de monto
- [ ] Anulación

---

## 15. Checklist de pruebas funcionales

| # | Escenario | Resultado esperado |
|---|-----------|-------------------|
| 1 | Login con clave incorrecta | 401, mensaje error |
| 2 | Login sin SUPERVISOR_PASSWORD en .env | 500 |
| 3 | Crear depósito completo | 201, token, carpeta, voucher guardado |
| 4 | Acceder `/rendir/{token}` válido | Datos del depósito |
| 5 | Token inválido | 404 |
| 6 | Subir comprobante | 201, archivo en carpeta, fila en gastos |
| 7 | Liquidar exacto | Estado Por Revisar, liquidación Exacto |
| 8 | Liquidar devolución sin voucher | 400 |
| 9 | Liquidar devolución con voucher | OK, voucher guardado |
| 10 | Liquidar reembolso | OK, sin voucher aún |
| 11 | Aprobar reembolso sin voucher | 400 |
| 12 | Aprobar reembolso con voucher | Finalizado, PDF generado |
| 13 | Rechazar con motivo | Observado, observaciones guardadas |
| 14 | Técnico en Observado puede re-editar | Formulario visible |
| 15 | Ampliar en Rindiendo | Monto incrementado, ampliación registrada |
| 16 | Ampliar en Finalizado | 400 |
| 17 | Anular con motivo | Anulado |
| 18 | KPIs dashboard | Cálculos correctos |
| 19 | WhatsApp links | URL wa.me válida con prefijo 51 |
| 20 | PDF resumen | Contenido y formato correctos |

---

## 16. Archivos fuente de referencia (repo original)

| Archivo | Contenido |
|---------|-----------|
| `backend/server.js` | Todos los endpoints y lógica HTTP |
| `backend/sheetsService.js` | CRUD Google Sheets + migración esquema |
| `backend/driveService.js` | Carpetas y uploads Drive |
| `backend/pdfService.js` | Generación PDF resumen |
| `backend/googleAuth.js` | OAuth2 y Service Account |
| `frontend/src/pages/Dashboard.tsx` | UI supervisor (~1040 líneas) |
| `frontend/src/pages/RendicionForm.tsx` | UI técnico (~568 líneas) |
| `frontend/src/pages/Login.tsx` | Login supervisor |
| `frontend/src/utils/api.ts` | API_URL y auth helpers |
| `frontend/src/utils/whatsapp.ts` | Links WhatsApp |
| `frontend/src/index.css` | Sistema de estilos completo |
| `docs/FUNCIONALIDADES.md` | Documentación funcional |

---

## 17. Dependencias PHP recomendadas

```json
{
  "require": {
    "laravel/framework": "^11.0",
    "barryvdh/laravel-dompdf": "^3.0",
    "google/apiclient": "^2.15"
  }
}
```

`google/apiclient` solo si se mantiene integración Google.

---

## 18. Consideraciones de seguridad

1. **HTTPS en producción** — la clave `x-admin-key` viaja en headers.
2. **No commitear** `.env`, `credentials.json`, claves privadas.
3. **Validar tipos MIME** en uploads (no confiar solo en extensión).
4. **Sanitizar nombres** de archivo (como en Node: reemplazar espacios).
5. **Rate limiting** en endpoints públicos (token brute-force teórico).
6. Los tokens son 128 bits de entropía — suficientes para enlaces no adivinables.

---

## 19. Datos de negocio hardcodeados (no olvidar)

| Dato | Valor |
|------|-------|
| Empresa | PercyTech |
| RUC | 10463288271 |
| WhatsApp soporte | +51 966 804 286 |
| Cuenta Interbank | 169-30010821-43 |
| Cuenta BCP | 191-98435080-71 |
| Moneda | Soles peruanos (S/) |
| País teléfonos | Perú (+51) |

---

## 20. Prompt sugerido para la IA implementadora

```
Implementa el sistema de rendiciones descrito en ADAPTACION_PHP_LARAGON.md usando:
- Laravel 11 en Laragon (Windows)
- MySQL como base de datos
- Almacenamiento local de archivos en storage/app/rendiciones
- dompdf para la Hoja Resumen PDF
- Reutiliza el frontend React compilado desde ../frontend (mantener compatibilidad API)
- Respeta EXACTAMENTE la máquina de estados, nombres de campos JSON en PascalCase,
  endpoints REST y reglas de negocio documentadas
- Incluye migraciones, seeders de prueba, middleware RequireAdmin y tests Feature
  para los flujos críticos
- Idioma de la UI y mensajes de error: español
```

---

*Documento generado a partir del análisis del repositorio rendicionesGoogle. Versión origen: Node.js + React + Google Sheets/Drive.*
