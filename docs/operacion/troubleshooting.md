# Runbook — Solución de problemas

> **Tipo:** Runbook (operación) · **Audiencia:** Sistemas / TI · **Actualizado:** 2026-07-23
>
> Síntomas frecuentes y su solución. Ordenado por "qué ve el usuario". Basado en
> incidentes reales del despliegue y la operación.

## Índice rápido
- [El sitio da 403 (Forbidden)](#el-sitio-da-403-forbidden)
- [El sitio da 500 o "requires PHP >= 8.2"](#el-sitio-da-500-o-requires-php--82)
- [Error de conexión a la base de datos](#error-de-conexión-a-la-base-de-datos)
- [Los archivos no suben a SharePoint](#los-archivos-no-suben-a-sharepoint)
- [Datos financieros se ven ilegibles (eyJpdiI6…)](#datos-financieros-se-ven-ilegibles)
- [El backup diario no corre](#el-backup-diario-no-corre)
- [Un trabajador no ve sus documentos/boletas](#un-trabajador-no-ve-sus-documentosboletas)
- [`/_setup` da 404](#_setup-da-404)
- [En local: "php no se reconoce"](#en-local-php-no-se-reconoce)

---

## El sitio da 403 (Forbidden)

**Síntoma:** *"Server unable to read htaccess file, denying access to be safe"*.

**Causa:** al re-clonar, la carpeta del repo quedó en permisos **700**; Apache no puede leerla.

**Solución:** permisos **carpetas = 755, archivos = 644**. En File Manager:
- `repositories/recursoshumanos` → 755 (la culpable habitual)
- `.../public` → 755 · `public/.htaccess` y `public/index.php` → 644

Detalle: [docs/09 §⚑](../09-deploy-cpanel.md).

## El sitio da 500 o "requires PHP >= 8.2"

**Causa:** el `.htaccess` del repo pisó el handler de PHP → el sitio corre en PHP 8.1.

**Solución:** agrega al **inicio** de `public/.htaccess` el handler `___lsphp` de PHP 8.2:
```apache
# php -- BEGIN cPanel-generated handler, do not edit
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php82___lsphp .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
```
**Verifica:** abre la home; si carga la bienvenida de Laravel, PHP 8.2 está activo.

## Error de conexión a la base de datos

**Síntoma:** *"SQLSTATE[HY000] [2002]"* o *"Access denied"*.

**Causas y solución:**
- **En local:** MySQL de Laragon apagado → enciéndelo ("Start All", que quede verde).
- **En producción:** revisar en el `.env` `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`
  (la contraseña con símbolos va entre comillas). Confirmar que el usuario MySQL
  tenga permisos sobre la base.

## Los archivos no suben a SharePoint

**Síntoma:** documentos/vouchers/boletas quedan en estado **"pendiente"** (guardados
local, no en SharePoint).

**Diagnóstico:** corre `graph:ping` (vía `/_setup` en el deploy, o por cron) — prueba
token + sitio + biblioteca.

**Causas y solución:**
- **Secret de Graph vencido** (`AADSTS7000222 ... secret expired`) → renovar el secret:
  [rotar-secret-graph.md](rotar-secret-graph.md).
- **Credenciales mal copiadas** en `.env` (tenant/client/secret) → verificar valores.
- **Fallo temporal de Microsoft** → los archivos quedan locales; reintenta con el
  comando `rendiciones:subir-pendientes` (ver [crons-y-tareas.md](crons-y-tareas.md)).

## Datos financieros se ven ilegibles

**Síntoma:** número de cuenta / CCI / sueldo se muestran como `eyJpdiI6...` (texto cifrado).

**Causa:** el `APP_KEY` del `.env` **no es** el que cifró esos datos (típico tras
restaurar un backup con otra llave).

**Solución:** pon en el `.env` el **APP_KEY correcto** (el que corresponde a esos datos)
y limpia caché de config. Sin el APP_KEY original, esos datos **no se recuperan**.

## El backup diario no corre

**Diagnóstico:** revisa el log `/home/oipfutlf/backup-cron.log`.

**Causas y solución:**
- **Ruta de PHP incorrecta** en el cron → usar `/opt/cpanel/ea-php82/root/usr/bin/php`.
- **El cron no existe / mal horario** → revisar en cPanel → Cron Jobs (debe ser `0 2 * * *`).
- **Graph caído** → el backup cae a local (`storage/app/private/backups/`); revisar Graph.

Detalle: [crons-y-tareas.md](crons-y-tareas.md).

## Un trabajador no ve sus documentos/boletas

**Causa más común:** su usuario **no está vinculado** a la ficha de empleado.

**Solución:** Usuarios → editar su usuario → campo **"Empleado vinculado"** → seleccionar
su ficha → Guardar. El portal "Mi espacio" funciona por esa vinculación.

## `/_setup` da 404

Es **el comportamiento esperado** cuando `APP_SETUP_TOKEN` está **vacío** (se vacía tras
instalar, por seguridad). Para volver a usarlo: pon un token en el `.env`, abre
`/_setup/EL_TOKEN`, y vuelve a vaciarlo. Si da 404 con token puesto, revisa que el
token de la URL coincida exactamente.

## En local: "php no se reconoce"

PHP no está en el PATH de la terminal. Antes de `php artisan …`:
```powershell
$env:Path = "C:\laragon\bin\php\php-8.2.32;" + $env:Path
```
O usa el `.bat` de arranque del proyecto.

---

## Si nada de esto aplica
- Revisa `storage/logs/laravel.log` (últimas líneas) para el error exacto.
- Con `APP_DEBUG=true` temporalmente en el `.env` verás el detalle en pantalla
  (**vuélvelo a `false`** después: en producción no debe quedar en true).
