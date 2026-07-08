# Despliegue (sin SSH)

Estrategia oficial para publicar la app en el hosting de **yachay.lat**
(subdominio `rrhh.gds.pe`). Se aplica en la **Fase 4**; aquí queda documentada.

> ⚠️ **Restricción confirmada por Yachay (soporte, 2026-07-08):** el hosting de
> `gds.pe` **NO permite conexiones SSH**. Por lo tanto **no se pueden ejecutar
> comandos en el servidor** (`composer install`, `php artisan migrate`, `npm`).
> Todo lo que requiera comandos se resuelve **desde la PC local** y se sube ya listo.

---

## Idea general

```
[ Tu PC ]                          [ GitHub ]              [ Hosting yachay ]
 desarrollar                        repo privado            cPanel + MySQL
 composer install --no-dev  ─┐
 npm run build               ├─► commit (incluye ─► git ─► Deploy HEAD Commit
 (vendor/ + public/build)    ┘   vendor y build)   clone   (.cpanel.yml copia a
                                                            la carpeta pública)
```

Como el servidor no puede instalar dependencias ni compilar, **subimos el
proyecto "compilado"**: con `vendor/` (dependencias PHP) y `public/build`
(CSS/JS ya generados) incluidos.

---

## 1. Preparación en la PC (antes de cada despliegue)

```bash
# 1. Dependencias de PRODUCCIÓN (sin paquetes de desarrollo, optimizado)
composer install --no-dev --optimize-autoloader

# 2. Compilar assets para producción
npm run build

# 3. Optimizar Laravel (cachés de config/rutas/vistas)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> 💡 Tras desplegar, para volver a desarrollar en local ejecuta
> `composer install` (sin `--no-dev`) para recuperar las herramientas de desarrollo.

---

## 2. Cómo suben `vendor/` y `public/build`

Normalmente están en `.gitignore` (no se versionan). Para el despliegue sin SSH
hay **dos opciones**:

### Opción A — Rama de despliegue `deploy` (recomendada)
Una rama aparte que **sí** incluye `vendor/` y `public/build`:

```bash
git checkout -b deploy
git add -f vendor public/build      # -f fuerza a incluir lo ignorado
git commit -m "chore(deploy): artefacto de producción"
git push origin deploy
```
En cPanel se hace *pull/deploy* de la rama `deploy` (no de `main`).
`main` queda limpio (sin `vendor`); `deploy` es solo el "paquete" para el server.

### Opción B — Subir un ZIP por el Administrador de archivos
Comprimir el proyecto (con `vendor` y `public/build`) y subirlo/descomprimirlo
en cPanel. Más manual; útil como respaldo si Git falla.

---

## 3. cPanel Git + `.cpanel.yml`

Con **Git Version Control** de cPanel:
1. **Clone** del repo de GitHub (rama `deploy`).
2. **Deploy HEAD Commit** → ejecuta el archivo `.cpanel.yml` de la raíz.

`.cpanel.yml` de ejemplo (copia los archivos a la carpeta pública del subdominio):

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/USUARIO/rrhh.gds.pe
    - /bin/cp -R * $DEPLOYPATH
    - /bin/cp -R public/* $DEPLOYPATH/public
```

> Los rutas exactas (`USUARIO`, carpeta del subdominio) se confirman en cPanel al
> momento del despliegue. Lo importante: el **document root** del subdominio
> `rrhh.gds.pe` debe apuntar a la carpeta **`public/`** de Laravel (nunca a la raíz,
> por seguridad).

---

## 4. Base de datos (sin `php artisan migrate`)

Como no hay comandos en el servidor, las tablas se crean así:

### Opción A — Exportar/Importar SQL (recomendada)
1. En la PC, exportar la estructura (y catálogos) de la BD local:
   ```bash
   # con las tablas ya migradas en local:
   mysqldump -u root recursoshumanos > recursoshumanos.sql
   ```
   (o exportar desde HeidiSQL: clic derecho en la base → *Exportar base de datos como SQL*).
2. En cPanel → **phpMyAdmin** → crear la base → pestaña **Importar** → subir `recursoshumanos.sql`.

### Opción B — Ruta web protegida de un solo uso
Crear una ruta temporal (protegida por una clave) que ejecute `Artisan::call('migrate')`
desde el navegador, y **eliminarla** después. Solo si la Opción A no es viable.

---

## 5. Configuración del servidor (`.env` de producción)

El `.env` **no se sube** (está en `.gitignore`). Se crea **a mano** en el servidor
(Administrador de archivos de cPanel) con los datos del hosting:

```env
APP_NAME="Sistema RRHH"
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # copiar el APP_KEY generado
APP_URL=https://rrhh.gds.pe
APP_TIMEZONE=America/Lima
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=   # base creada en cPanel
DB_USERNAME=   # usuario MySQL de cPanel
DB_PASSWORD=   # contraseña MySQL de cPanel
```

> ⚠️ En producción: `APP_ENV=production` y `APP_DEBUG=false` (nunca mostrar errores
> técnicos al usuario final).

---

## 6. Checklist de despliegue (Fase 4)

- [ ] `composer install --no-dev --optimize-autoloader` en local
- [ ] `npm run build` en local
- [ ] Cachés: `config:cache`, `route:cache`, `view:cache`
- [ ] Preparar rama `deploy` con `vendor/` + `public/build`
- [ ] Subdominio `rrhh.gds.pe` con document root → carpeta `public/`
- [ ] SSL activo (gratis en cPanel)
- [ ] `.env` de producción creado a mano (APP_ENV=production, APP_DEBUG=false)
- [ ] Base de datos creada + `.sql` importado por phpMyAdmin
- [ ] Deploy HEAD Commit (o subida por ZIP)
- [ ] Probar: entrar a `https://rrhh.gds.pe`, iniciar sesión, revisar módulos
- [ ] Cargar datos reales (y vaciar datos de prueba)

---

## Notas

- **OneDrive/SharePoint** (documentos) funciona igual en producción — es una API
  externa, no depende del hosting.
- Cada actualización futura repite: build en local → actualizar rama `deploy` →
  Deploy HEAD Commit. Si más adelante Yachay habilitara SSH, se simplifica
  (composer/artisan en el servidor).
