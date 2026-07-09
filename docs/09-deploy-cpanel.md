# Despliegue en cPanel (yachay.lat) SIN SSH — rrhh.gds.pe

> Guía basada en el **despliegue real** (2026-07-09). El repo viene listo para
> producción: versiona `vendor/` y `public/build`, trae `.cpanel.yml` y una ruta
> de instalación protegida. No hace falta composer ni npm en el servidor.

## Datos de la cuenta

- Dominio: **gds.pe** (principal, en `/public_html`) · Usuario cPanel: **oipfutlf**
- Servidor: yl-ubinas.yachay.pe · IP: **161.132.57.236** · NS: ns1/ns2.yachay.pe
- App: **https://rrhh.gds.pe** · SSL de la cuenta válido (el subdominio hereda HTTPS)
- Panel: interfaz "Gestionar Hosti Plus" de yachay (parecida a cPanel pero con
  algunas pantallas propias).

## ⚠️ Cosas necesarias / que hay que saber ANTES

1. **El repo debe estar PÚBLICO al clonar.** cPanel de yachay **rechaza URLs con
   token/contraseña** ("contains a password. You cannot use this URL."), así que
   un Personal Access Token en la URL **no funciona**. Opciones:
   - **A (usada):** poner el repo público en GitHub mientras se clona, y luego
     volverlo privado (lo ya clonado sigue funcionando; solo los *pull* futuros
     necesitarán público otra vez).
   - **B (alternativa):** desplegar por **ZIP** vía File Manager (repo sigue privado).
2. **Secretos fuera del repo:** `APP_KEY`, contraseña de BD y `APP_SETUP_TOKEN`
   NUNCA se commitean (el `.env` está en `.gitignore`). Se guardan en el gestor de
   contraseñas del admin / notas privadas.
3. **Sesión y caché en archivo** (no en BD): así la ruta `/_setup` funciona aunque
   las tablas aún no existan. Ya viene así en la plantilla.
4. **El DNS del subdominio tarda** en propagar (15 min – 2 h).

---

## Paso a paso (lo que hicimos)

### 1. Poner el repo público (temporal)
GitHub → repo → **Settings → Danger Zone → Change visibility → Public**.

### 2. Clonar en Git Version Control
cPanel → **Git Version Control** → **Create Repository** →
- **Clone From Existing Repository:** ON
- **Source Repository URL:** `https://github.com/PercyTechX/recursoshumanos.git`  *(sin token)*
- **Repository Root:** `repositories/recursoshumanos`
- **Repository Name:** `recursoshumanos` · **Remote Name:** `origin`
- **Confirm** (la primera clonación tarda: baja `vendor/`).

### 3. Crear el subdominio (interfaz "Dominios")
cPanel → **Dominios → Create A New Domain**:
- **Domain:** `rrhh.gds.pe`
- **"Share document root…":** DESmarcado
- **Document Root:** `/home/oipfutlf/repositories/recursoshumanos/public`  *(¡debe terminar en `/public`!)*
- **Enviar**.

### 4. Fijar PHP 8.2
cPanel → **PHP Version / MultiPHP Manager** → seleccionar `rrhh.gds.pe` → **PHP 8.2 (ea-php82)** → **Aplicar**.
(Laravel 12 exige 8.2; no sirve 8.1.)

### 5. Base de datos
cPanel → **MySQL Databases**:
- **Databases** → Create Database `rrhh` → queda **`oipfutlf_rrhh`**.
- **Users** → crear usuario `rrhh` (queda `oipfutlf_rrhh`) + contraseña fuerte.
- **Manage Privileges** → enlazar usuario + base con **ALL PRIVILEGES**.

### 6. Crear el `.env` (File Manager)
cPanel → **File Manager**:
- Menú **⋮ / Settings** → activar **"Show Hidden Files (dotfiles)"**.
- Entrar a **`repositories/recursoshumanos`** (verifica que ves `app`, `public`,
  `vendor`, `artisan`).
- **+ Create File** → `.env` → editarlo (con el ✏️ / "Show Content") y pegar la
  plantilla de abajo con los valores reales. **Guardar**.

### 7. Esperar el DNS
El subdominio no resuelve al instante. Verificar en **Zone Editor** que exista un
registro **A**: `rrhh.gds.pe → 161.132.57.236` (si falta, agregarlo). Luego esperar.
Probar `https://rrhh.gds.pe` cada tanto (o desde el celular con datos móviles).

### 8. Prueba de arranque
Con el DNS ya resolviendo, abrir **https://rrhh.gds.pe**:
- Bienvenida/login → OK.
- Error → con `APP_DEBUG=true` sale detallado; corregir y reintentar.

### 9. Migraciones (una vez, sin SSH)
Abrir `https://rrhh.gds.pe/_setup/EL_TOKEN` (el `APP_SETUP_TOKEN` del `.env`).
Corre `migrate --force` + seed de catálogos (roles + tipos de documento).

### 10. Cerrar la instalación
En `.env`: **vaciar** `APP_SETUP_TOKEN=` y poner `APP_DEBUG=false`. Volver a
desplegar / guardar. La ruta `/_setup` vuelve a dar 404.

### 11. Crear el usuario admin
No hay seeder de admin en producción (seguridad). Opciones:
- Ruta temporal de alta de admin *(a pedir a Claude, se agrega y luego se quita)*, o
- Insertar por **phpMyAdmin** en `users` + `model_has_roles` (SQL provisto aparte).

### 12. Enlace de storage (para ver archivos/firmas)
El `.cpanel.yml` crea `public/storage → ../storage/app/public`. En esta interfaz el
deploy se dispara con **"Add Deployments"** (no hay pestaña "Pull or Deploy"). Si no,
crear el symlink a mano por File Manager. Sin esto, los archivos subidos no se ven,
pero el login y el resto funcionan.

### 13. Volver el repo a privado (opcional)
GitHub → Settings → visibility → Private. Lo ya desplegado sigue igual; solo los
*pull* futuros pedirán público otra vez (o usar ZIP).

---

## Plantilla `.env` de producción (sin secretos)

```dotenv
APP_NAME="Sistema RRHH"
APP_ENV=production
APP_KEY=            # base64:... (privado, no commitear)
APP_DEBUG=false     # true solo durante el primer arranque
APP_TIMEZONE=America/Lima
APP_URL=https://rrhh.gds.pe

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_PE

APP_SETUP_TOKEN=    # solo durante la instalación; luego vaciar

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oipfutlf_rrhh
DB_USERNAME=oipfutlf_rrhh
DB_PASSWORD=        # privado, entre comillas si tiene símbolos

# Archivo (no BD): evita depender de tablas antes de migrar
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=log     # smtp con cuenta de cPanel más adelante
VITE_APP_NAME="Sistema RRHH"
```

---

## Actualizaciones futuras (nuevo deploy)

1. En tu PC: `npm run build` → `git add` → `commit` → `push` a `main`.
2. Repo público un momento (o método ZIP) → Git Version Control → **Update from
   Remote** / **Add Deployments** para traer los cambios.
3. Si hay **migraciones nuevas:** poner de nuevo `APP_SETUP_TOKEN` en `.env`, abrir
   `/_setup/EL_TOKEN`, y luego vaciarlo.

## Problemas que encontramos (y solución)

| Síntoma | Causa | Solución |
|---|---|---|
| `error 128: could not read Username` al clonar | repo privado, cPanel no autentica | repo público (o ZIP) |
| `contains a password. You cannot use this URL` | token en la URL | no usar token; repo público |
| `Outdated security token` en cPanel | sesión del panel caducó | refrescar la página y reintentar |
| `DNS_PROBE / no se encuentra rrhh.gds.pe` | DNS del subdominio propagando | esperar; verificar registro A en Zone Editor |
| No veo el `.env` en File Manager | archivos ocultos escondidos | activar "Show Hidden Files" |
| 500 al abrir la app | `APP_KEY` vacío o `.env` mal | `APP_DEBUG=true` para ver el error; corregir |
| Estilos no cargan | falta `public/build` o `APP_URL` malo | verificar que `public/build` se versionó y `APP_URL` |
| Archivos/firmas no se ven | falta `public/storage` | re-deploy (`.cpanel.yml`) o crear symlink |

## Credenciales

`APP_KEY`, contraseña de BD y `APP_SETUP_TOKEN` **no están en el repo** (público).
Viven solo en el `.env` del servidor y en las notas privadas del admin.
