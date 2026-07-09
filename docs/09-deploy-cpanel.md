# Despliegue en cPanel (yachay.lat) SIN SSH — rrhh.gds.pe

> Guía paso a paso. El repo ya viene **listo para producción**: versiona
> `vendor/` y `public/build`, trae `.cpanel.yml` y una ruta de instalación
> protegida. No hace falta composer ni npm en el servidor.

## Datos de la cuenta (referencia)

- Dominio: **gds.pe** · Usuario cPanel: **oipfutlf** · Servidor: yl-ubinas.yachay.pe
- App vivirá en el subdominio **https://rrhh.gds.pe**
- SSL de la cuenta válido (el subdominio hereda HTTPS)

---

## Resumen del método

GitHub → cPanel **Git Version Control** clona el repo → el subdominio apunta a la
carpeta `public/` del repo → se corre **una** ruta protegida que ejecuta las
migraciones. Cero SSH.

---

## Paso a paso

### 1. Clonar el repositorio (Git Version Control)

1. cPanel → **Git Version Control** → **Create**.
2. **Clone URL:** `https://github.com/PercyTechX/recursoshumanos.git`
   - Si el repo es privado, usar un *Personal Access Token* en la URL:
     `https://USUARIO:TOKEN@github.com/PercyTechX/recursoshumanos.git`
3. **Repository Path:** `repositories/recursoshumanos`
4. **Repository Name:** `recursoshumanos` → **Create**.

Espera a que clone (tarda: incluye `vendor/`).

### 2. Crear el subdominio apuntando a `public/`

1. cPanel → **Subdomains**.
2. **Subdomain:** `rrhh` · **Domain:** `gds.pe`.
3. **Document Root:** borra lo sugerido y pon:
   `repositories/recursoshumanos/public`
4. **Create**.

> Clave: el Document Root apunta a `.../public`, no a la raíz del repo (seguridad).

### 3. Fijar PHP 8.2

cPanel → **PHP Version** (o *MultiPHP Manager*) → para `rrhh.gds.pe` elegir **8.2**.

### 4. Crear la base de datos

cPanel → **MySQL Databases**:

1. **Create New Database:** `rrhh` → queda como **`oipfutlf_rrhh`**.
2. **Add New User:** `rrhh` (queda `oipfutlf_rrhh`) + contraseña fuerte (guárdala).
3. **Add User To Database** → marcar **ALL PRIVILEGES**.

### 5. Crear el archivo `.env` (File Manager)

1. cPanel → **File Manager** → entra a `repositories/recursoshumanos`.
2. Copia `.env.production.example` y renómbralo a **`.env`**.
3. Edítalo y llena:
   - `APP_KEY=` → la clave `base64:...` que te dio Claude.
   - `APP_SETUP_TOKEN=` → el token que te dio Claude (para el paso 7).
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` → los del paso 4.
   - Verifica `APP_URL=https://rrhh.gds.pe`.
4. Guardar.

### 6. Desplegar (crea el enlace de storage)

Git Version Control → tu repo → pestaña **Pull or Deploy** → **Deploy HEAD Commit**.
Esto ejecuta `.cpanel.yml` y crea el enlace `public/storage` (para ver archivos y firmas).

### 7. Correr las migraciones (una sola vez, sin SSH)

En el navegador abre:

```
https://rrhh.gds.pe/_setup/EL_TOKEN
```

(reemplaza `EL_TOKEN` por el `APP_SETUP_TOKEN` que pusiste). Verás el resultado de
las migraciones y el seed de catálogos (roles + tipos de documento).

### 8. Cerrar la puerta de instalación

1. File Manager → `.env` → **vacía** `APP_SETUP_TOKEN=` (déjalo sin valor).
2. Git Version Control → **Deploy HEAD Commit** otra vez (o edita y guarda) para
   que tome el cambio. Ahora `/_setup/...` responde 404. ✅

### 9. Crear el usuario administrador

No hay seeder de admin en producción (por seguridad). Dos opciones:

- **A (recomendada):** agrega temporalmente en `.env` `APP_SETUP_TOKEN` de nuevo y
  usamos una segunda ruta de alta de admin *(dímelo y la agrego)*, **o**
- **B:** por **phpMyAdmin**, inserta un usuario en `users` (te paso el SQL con el
  hash de la contraseña) y su rol en `model_has_roles`.

### 10. Probar

- `https://rrhh.gds.pe/login` → entra con el admin.
- Revisa Tablero, Empleados, Documentos, Doc. compartidos, Activos.

---

## Actualizaciones futuras (nuevo deploy)

1. En tu PC: `npm run build` → `git add` → `commit` → `push` a `main`.
2. cPanel → Git Version Control → **Update from Remote** → **Deploy HEAD Commit**.
3. Si agregaste migraciones nuevas: repite el paso 7 (token) y luego el 8.

## Problemas comunes

- **500 / página en blanco:** `APP_KEY` vacío, o `.env` mal. Pon `APP_DEBUG=true`
  temporalmente para ver el error, luego vuelve a `false`.
- **No cargan estilos:** faltó `public/build` (verifica que se versionó) o `APP_URL`
  incorrecto.
- **Archivos/firmas no se ven:** no se creó `public/storage` → re-deploy (paso 6).
- **`/_setup` da 404 cuando lo necesitas:** `APP_SETUP_TOKEN` está vacío o no
  coincide; ponlo y re-deploy.
