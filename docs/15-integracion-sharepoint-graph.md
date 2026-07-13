# 15 · Integración de documentos con SharePoint (Microsoft Graph)

> **Estado:** plan de diseño aprobado. Pendiente de construir.
> **Objetivo del usuario:** que al subir un documento en la app, el archivo aterrice
> **solo en SharePoint** (un paso, como hoy), sin el "copiar link / pegar link".
> Ya existe una **biblioteca de documentos de SharePoint** y hay **admin de M365**.

---

## 1. Estado actual (cómo se guarda hoy)

- El módulo **Documentos** (`resources/views/livewire/documentos/tabla.blade.php`) sube el
  archivo con Livewire y lo guarda en el **servidor**:
  ```php
  $payload['archivo_path']   = $this->archivo->store('documentos', 'public'); // storage/app/public/documentos
  $payload['archivo_nombre'] = $this->archivo->getClientOriginalName();
  ```
- Se sirve con `Storage::url($d->archivo_path)` vía el symlink `public/storage`.
- La BD (`documentos`) guarda metadata: `empleado_id`, `tipo_documento_id`,
  `fecha_emision`, `fecha_vencimiento`, `observacion`, `archivo_path`, `archivo_nombre`.
- **Otros módulos también suben archivos** con el mismo patrón `->store(...,'public')`:
  `portal/index`, `empleados/expediente`, `documentos-compartidos/tabla`,
  `ausencias/tabla`, `activos/tabla`. → El adaptador de SharePoint debe poder
  **reutilizarse** en todos (aunque la Fase 1 sea solo Documentos).

---

## 2. Arquitectura elegida

- **Flujo de autenticación: app-only / client credentials.** La app se autentica
  **como ella misma**, no como un usuario logueado.
  - ❌ Sin login de usuario · ❌ sin refresh tokens · ❌ **sin cron de refresco**.
  - Token de acceso ~1 h; se pide uno nuevo bajo demanda y se **cachea ~55 min**.
- **Permiso Graph mínimo: `Sites.Selected`** + consentimiento de admin, con
  **concesión explícita solo al sitio de RRHH** (no toca el resto del tenant).
- **Credencial: certificado** (recomendado, más duradero) o client secret.
- **Referencia durable en BD: el `driveItem id`** (no la ruta) → si mueven o
  renombran el archivo en SharePoint, el enlace **no se rompe**.
- **HTTP puro** (cliente `Http` de Laravel), **sin** el SDK `microsoft/microsoft-graph`
  (evita inflar `vendor/`, que se sube por re-clonar en cPanel).
- **Endpoints Graph que usaremos:**
  - Token: `POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`
    (`grant_type=client_credentials`, `scope=https://graph.microsoft.com/.default`).
  - Resolver sitio: `GET /sites/{hostname}:/sites/{ruta}` → `site-id`.
  - Resolver biblioteca (drive): `GET /sites/{site-id}/drives` → `drive-id`.
  - Subir (<250 MB): `PUT /drives/{drive-id}/root:/{ruta}/{archivo}:/content`.
  - Subir grande (>4 MB, opcional): `createUploadSession` + chunks.
  - Leer/descargar: `GET /drives/{drive-id}/items/{item-id}/content`
    (o `@microsoft.graph.downloadUrl`).
  - Borrar: `DELETE /drives/{drive-id}/items/{item-id}`.

### Patrón "guardar-temporal-y-reintentar" (resiliencia)
Para que un fallo puntual de Graph **no pierda el archivo**:
1. Livewire deja el archivo en tmp del servidor.
2. Se intenta subir a SharePoint.
3. Si SharePoint responde OK → se guarda `sharepoint_item_id`, `sharepoint_web_url`,
   `upload_status='subido'` y se borra el tmp.
4. Si falla → se conserva el archivo en el servidor (`storage_driver='local'` temporal),
   `upload_status='pendiente'` y aparece botón **Reintentar** (y/o cron opcional).

---

## 3. Decisiones de diseño PENDIENTES (resolver mañana)

1. **¿Disco custom vs servicio dedicado?**
   - **(a) Custom Flysystem disk `sharepoint`** (path-based): `Storage::disk('sharepoint')->put()`
     funciona en TODOS los módulos con cambios mínimos. Reutiliza `->store()` y `Storage::url()`.
     Contra: Flysystem indexa por **ruta**, no por item-id (vuelve el riesgo "movieron el archivo").
   - **(b) Servicio `SharePointDocs`** enchufado al módulo: guardamos **item-id** (durable),
     control total. Contra: hay que tocar cada módulo que suba archivos.
   - **Propuesta:** empezar con **(b)** solo en Documentos (Fase 1) guardando item-id;
     evaluar (a) para extender al resto si conviene.
2. **¿Certificado o client secret?** Recomendado **certificado** (a prueba de futuro y de
   políticas que bloqueen secrets). Confirmar qué prefiere el admin.
3. **Estructura de carpetas en la biblioteca:** p.ej. `/{Año}/{DNI-Apellidos}/tipo-fecha.pdf`.
   Definir convención + **sanitizar** nombres (SharePoint prohíbe `# % * : < > ? / \ |`).
4. **¿Migrar los documentos ya subidos** al servidor hacia SharePoint, o solo aplica a
   los nuevos? (script de migración una vez, respetando throttling).

---

## 4. Modelo de datos (migración nueva)

Agregar a la tabla `documentos` (y luego a las demás en fases siguientes):

| Columna | Tipo | Uso |
|---|---|---|
| `storage_driver` | string(20), default `local` | `local` \| `sharepoint` |
| `sharepoint_item_id` | string, nullable | ID durable del `driveItem` |
| `sharepoint_web_url` | string, nullable | URL para "Abrir en SharePoint" |
| `upload_status` | string(20), default `subido` | `subido` \| `pendiente` \| `error` |
| `upload_error` | string, nullable | último mensaje de error (para depurar) |

`archivo_path` / `archivo_nombre` se mantienen (tmp local + fallback de nombre).

---

## 5. Archivos a crear / tocar

**Nuevos:**
- `app/Services/SharePoint/GraphClient.php` — token (cacheado) + wrapper `Http` (get/put/delete).
- `app/Services/SharePoint/SharePointDocs.php` — `subir($file, $ruta): array`,
  `descargar($itemId)`, `eliminar($itemId)`, `urlWeb($itemId)`; resuelve site-id/drive-id (cacheados).
- `config/services.php` → bloque `graph` (tenant, client_id, cert/secret, site host+path, drive).
- Migración de las columnas del §4.
- `routes/web.php` → ruta de descarga protegida `documentos/{documento}/archivo` que hace stream
  desde Graph (para no exponer la URL directa).
- `tests/Feature/SharePointDocsTest.php` — con `Http::fake()` (token + upload + download + delete),
  y test del flujo "pendiente/reintentar".
- Comando artisan `graph:ping` — **test de conectividad** (obtiene token + resuelve el sitio).

**A modificar:**
- `documentos/tabla.blade.php` → `guardar()` usa `SharePointDocs::subir()` con el patrón
  guardar-temporal-y-reintentar; vista de archivo apunta a la ruta de descarga; botón "Reintentar".

---

## 6. Configuración (.env de producción)

```
GRAPH_TENANT_ID=
GRAPH_CLIENT_ID=
# opción A (secret):
GRAPH_CLIENT_SECRET=
# opción B (certificado): ruta + thumbprint/private key
GRAPH_CERT_PATH=
GRAPH_CERT_PASSWORD=
GRAPH_SITE_HOST=tuempresa.sharepoint.com
GRAPH_SITE_PATH=/sites/RRHH
GRAPH_DRIVE_NAME=Documentos            # nombre de la biblioteca
```

> Sensible: NO commitear. Va en el `.env` del hosting (como `APP_KEY`).

---

## 7. Pasos del ADMIN de M365 (una sola vez) — checklist

1. **Entra ID → App registrations → New registration** (nombre p.ej. `RRHH-Docs`).
   - Anotar **Application (client) ID** y **Directory (tenant) ID**.
2. **API permissions → Add → Microsoft Graph → Application permissions → `Sites.Selected`**
   → **Grant admin consent**.
3. **Credencial:**
   - Certificado (recomendado): subir `.cer` en *Certificates & secrets*, o
   - Client secret: crear y **copiar el valor al momento** (no se vuelve a mostrar). Anotar fecha de expiración.
4. **Conceder acceso SOLO al sitio de RRHH** (Sites.Selected requiere concesión por-sitio):
   ```
   POST https://graph.microsoft.com/v1.0/sites/{site-id}/permissions
   { "roles": ["write"],
     "grantedToIdentities": [ { "application": { "id": "{client-id}", "displayName": "RRHH-Docs" } } ] }
   ```
   (lo puede correr el admin desde Graph Explorer con permiso `Sites.FullControl.All`).
5. Pasarnos: **tenant id, client id, credencial, URL del sitio, nombre de la biblioteca**.

---

## 8. Test de conectividad (antes de construir el módulo)

Con `php artisan graph:ping` verificar, en orden:
1. El hosting **sale por HTTPS** a `login.microsoftonline.com` y `graph.microsoft.com`
   (descarta bloqueo de salida / CA bundle viejo).
2. Se obtiene **token** con las credenciales (descarta credencial mal puesta).
3. Se resuelve **site-id** y **drive-id** (descarta URL/biblioteca mal escritas y falta de
   concesión `Sites.Selected`).

Si los 3 pasan, el resto del módulo es directo.

---

## 9. Análisis de fallos y mitigaciones

| # | Qué se rompería | Prob. | Impacto | Mitigación |
|---|---|---|---|---|
| 1 | **Expira el client secret** (máx. 24 meses) → 401 en todo | Media | Alto | **Certificado** o anotar expiración + rotar; documentado con recordatorio |
| 2 | Admin borra/cambia el permiso o la app | Baja | Alto | Documentar dueño del registro; `Sites.Selected` da menos miedo de tocar |
| 3 | Falta la concesión por-sitio de `Sites.Selected` | Media (**setup**) | Bloquea todo al inicio | Paso explícito §7.4; lo valida `graph:ping` |
| 4 | cPanel no sale por HTTPS / CA viejo → TLS | Baja-Media | Alto | `graph:ping` antes de construir; si falla, hablar con hosting |
| 5 | Usuario mueve/borra el archivo en SharePoint | Media | Medio | Guardar **item-id** (sobrevive mover/renombrar); si lo borran, error claro |
| 6 | Throttling 429 | Muy baja | Bajo | Respetar `Retry-After` + backoff |
| 7 | Nombre de archivo inválido / ruta larga | Media | Falla esa subida | Sanitizar nombre antes de subir |
| 8 | Límites PHP del hosting en archivos grandes | Baja | Falla pesados | Tope de tamaño (ya 5 MB) + upload session si hace falta |
| 9 | IT endurece seguridad del tenant (Conditional Access, bloquear secrets) | Baja | Alto | Certificado > secret; coordinar con IT |
| 10 | Cuota del sitio llena / M365 vence | Muy baja | Alto | Fuera de control; SharePoint ~1 TB |

**Conclusión:** lo único que "se rompe con el tiempo" es el **#1 (credencial)** —
predecible y calendarizable. El resto es ajuste de **setup** (una vez) o está fuera de
nuestras manos (políticas del tenant).

---

## 10. Plan por fases

### Fase 0 — Prerrequisitos (usuario + admin)
- [ ] Admin completa el checklist §7 y nos pasa los datos.
- [ ] `graph:ping` en verde desde producción (§8).

### Fase 1 — Módulo Documentos sobre SharePoint
- [ ] Migración columnas §4.
- [ ] `GraphClient` + `SharePointDocs` (HTTP puro, token cacheado).
- [ ] Wire en `documentos/tabla.blade.php` (subir/leer/borrar + guardar-temporal-y-reintentar).
- [ ] Ruta de descarga protegida (stream desde Graph).
- [ ] Botón **Reintentar** para `upload_status='pendiente'`.
- [ ] Tests con `Http::fake()`.
- [ ] Documentar en `docs/` la fecha de expiración de la credencial.

### Fase 2 — (opcional) Extender a los demás módulos
- [ ] Decidir disco custom `sharepoint` vs servicio (§3.1) para reutilizar en
      portal, expediente, documentos-compartidos, ausencias, activos.

### Fase 3 — (opcional) Migrar históricos
- [ ] Script que sube a SharePoint los archivos ya guardados en el servidor
      (respetando throttling) y actualiza `storage_driver`/`item-id`.

---

## 11. Qué necesito del usuario para arrancar (mañana)

1. **Tenant ID** (Directory ID).
2. **URL del sitio** de SharePoint (`https://tuempresa.sharepoint.com/sites/...`) + **nombre de la biblioteca**.
3. App registrada con **`Sites.Selected`** + consentimiento admin + concesión al sitio (§7).
4. **Certificado o client secret**.
5. Que corramos **`graph:ping`** desde producción y salga en verde.

> Con eso, la Fase 1 es directa. Retomamos por aquí.
