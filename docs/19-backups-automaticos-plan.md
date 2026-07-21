# 19 — Backups automáticos de la base de datos a SharePoint

> Estado: ✅ **OPERATIVO EN PRODUCCIÓN** (2026-07-21). Cron diario 02:00 creado y
> probado en cPanel (binario `/opt/cpanel/ea-php82/root/usr/bin/php`); el `.sql.gz`
> sube a `IT/BACKUP_SISTEMAS/RRHH_Sistemas`. Pendiente menor: restringir el acceso
> a esa carpeta en SharePoint.
> Objetivo: respaldo diario y automático de la BD de producción, guardado
> **fuera del servidor**, sin intervención manual.

## 0. Lo construido (2026-07-21)

- `config/backups.php` — `retencion_dias` (env `BACKUP_RETENCION_DIAS`, def. 30).
- `config/services.php` → destino **`backups`** = biblioteca `IT`, carpeta
  `BACKUP_SISTEMAS/RRHH_Sistemas` (envs `GRAPH_DRIVE_BACKUPS` / `GRAPH_FOLDER_BACKUPS`).
- `app/Services/Backups/DbDump.php` — volcado **PHP puro por PDO** (sin `mysqldump`).
  Verificado contra MySQL de Laragon: 49 tablas, estructura + datos, restaurable.
- `SharePointDocs::listar()` — lista una carpeta del destino (para purgar); 404 → [].
- `app/Console/Commands/BackupCrear.php` — `backup:crear` (dump → gzip → subir →
  purgar). Flag `--local` guarda solo en `storage/app/private/backups/`. Si Graph
  falla, cae a local para no perder el backup.
- `routes/console.php` — `Schedule::command('backup:crear')->dailyAt('02:00')`.
- Tests: `tests/Feature/BackupCrearTest.php` (local + subida/purga con dobles).
- **Subida real probada:** el `.sql.gz` llegó a `IT/BACKUP_SISTEMAS/RRHH_Sistemas`
  y `listar()` lo lee de vuelta. **195 tests en verde.**

### Falta para producción (al desplegar)
- [ ] Agregar los 3 envs al `.env` de prod (`GRAPH_DRIVE_BACKUPS=IT`,
  `GRAPH_FOLDER_BACKUPS=BACKUP_SISTEMAS/RRHH_Sistemas`, `BACKUP_RETENCION_DIAS=30`).
- [ ] Verificar en SharePoint que la carpeta `IT/BACKUP_SISTEMAS` sea de **acceso
  restringido** (PII).
- [ ] Crear el **Cron Job** en cPanel (ver §5) y correr una vez a mano:
  `php artisan backup:crear`.

## 1. Decisiones tomadas

| Punto | Decisión |
|---|---|
| ¿Qué se respalda? | **La base de datos** (`oipfutlf_rrhh`). El código ya vive en GitHub y los archivos subidos ya viven en SharePoint (Microsoft los respalda). |
| ¿Dónde se guarda? | **SharePoint**, reutilizando la integración Graph existente. |
| Biblioteca (drive) | **`IT`** |
| Carpeta | **`BACKUP_SISTEMAS/RRHH_Sistemas`** |
| Nombre de archivo | `AAAA-MM-DD_HHMMSS_rrhh.sql.gz` (ordena solo por fecha) |
| Retención | **30 días** (configurable). Backups más viejos se borran solos. |
| Frecuencia | **Diaria**, madrugada (ej. 02:00). |

**Por qué SharePoint y no el propio cPanel:** un backup en el mismo servidor que
protege no sirve si el servidor se cae/suspende. SharePoint está *fuera* del
hosting, no cuesta nada nuevo (ya se paga OneDrive), reusa el Graph ya probado y
es coherente con la migración a Microsoft (sin Google).

## 2. Advertencias de seguridad (IMPORTANTES)

1. **APP_KEY:** ciframos `numero_cuenta`, `cci` y `sueldo`. El `.sql` guarda esos
   campos **cifrados**; solo se descifran con el `APP_KEY` del `.env` de producción.
   → **Restaurar en un servidor con otro APP_KEY deja esos datos ilegibles.**
   Guardar el `APP_KEY` en gestor de contraseñas, aparte del backup.
2. **El backup contiene PII en claro** (nombres, DNI, sueldos cifrados pero PII
   sensible). Por eso la carpeta `IT/BACKUP_SISTEMAS` **debe ser de acceso
   restringido** (solo IT/SuperAdmin) en SharePoint. Verificar permisos de la carpeta.

## 3. Restricción técnica del hosting (shared cPanel, sin SSH)

Muchos hosting compartidos **desactivan `exec()`/`proc_open()`**, por lo que
**no podemos depender del binario `mysqldump`**. Por eso el volcado se hará con
un **dumper 100% PHP vía PDO** (sin binarios externos). La compresión usa
`gzencode()`, que es nativo de PHP. Cero dependencias del sistema.

> Verificación previa (tarea 0): confirmar en cPanel si `exec()` está disponible.
> Si lo estuviera, se podría usar `spatie/laravel-backup`; si no (lo más probable),
> vamos con el dumper PHP propio descrito abajo. **Asumimos el camino PHP puro.**

## 4. Diseño

### 4.1 Config — nuevo destino `backups`
`config/services.php` → `graph.destinos`:
```php
'backups' => [
    'drive'  => env('GRAPH_DRIVE_BACKUPS', 'IT'),
    'folder' => env('GRAPH_FOLDER_BACKUPS', 'BACKUP_SISTEMAS/RRHH_Sistemas'),
],
```
`.env` de producción (y `.env.example`):
```
GRAPH_DRIVE_BACKUPS=IT
GRAPH_FOLDER_BACKUPS=BACKUP_SISTEMAS/RRHH_Sistemas
BACKUP_RETENCION_DIAS=30
```
Nada más que configurar: `SharePointDocs` ya resuelve drive+carpeta por destino y
Graph crea las carpetas intermedias al subir por ruta.

### 4.2 Servicio de volcado — `app/Services/Backups/DbDump.php`
- Método `generar(): string` → devuelve el SQL como string (o lo escribe a un
  archivo temporal en `storage/app/backups/`).
- Recorre las tablas vía PDO (`SHOW TABLES`, `SHOW CREATE TABLE`, `SELECT *`),
  arma `DROP TABLE IF EXISTS` + `CREATE TABLE` + `INSERT`s por lotes.
- Se aísla en un servicio para poder **mockearlo en tests** (en test corremos
  sqlite; el dump real es MySQL).
- Alternativa si se prefiere no reinventar: paquete `ifsnop/mysqldump-php`
  (dumper PHP puro por PDO). Evaluar en la implementación; el contrato del
  servicio no cambia.

### 4.3 Método nuevo en `SharePointDocs` — `listar()`
Para poder **purgar** los backups viejos necesitamos listar la carpeta:
```php
/** Lista items de una carpeta del destino: [ ['id','name','createdDateTime'], ... ] */
public function listar(string $carpeta, string $destino = 'documentos'): array
```
GET `/drives/{driveId}/root:/{base}/{carpeta}:/children` → mapear `id`, `name`,
`createdDateTime`. Ya existe `eliminar($itemId, $destino)` para el borrado.

### 4.4 Comando — `app/Console/Commands/BackupCrear.php` (`backup:crear`)
Flujo:
1. `DbDump::generar()` → SQL.
2. `gzencode($sql, 9)` → binario comprimido.
3. `SharePointDocs::subirContenido($gz, 'application/gzip', '', "{$fecha}_rrhh.sql.gz", 'backups')`.
4. **Purga:** `SharePointDocs::listar('', 'backups')`, borrar los que `createdDateTime`
   < hoy − `BACKUP_RETENCION_DIAS`.
5. Limpiar temporales locales. Loguear resultado (líneas + tamaño + item_id).
6. Manejo de error: si Graph falla, dejar el `.sql.gz` en `storage/app/backups/`
   como respaldo local temporal y loguear/avisar (no perder el dump).

### 4.5 Programación (scheduler)
`routes/console.php` (o `bootstrap/app.php` en L12):
```php
Schedule::command('backup:crear')->dailyAt('02:00')->onOneServer();
```
En **cPanel → Cron Jobs**, dado que no hay SSH, la opción más simple y robusta es
un cron **directo al comando** una vez al día (evita el cron-cada-minuto de
`schedule:run`):
```
0 2 * * *  /usr/local/bin/php /home/USUARIO/ruta/artisan backup:crear >> /home/USUARIO/logs/backup.log 2>&1
```
(Confirmar la ruta real de PHP y del proyecto en yachay.)

## 5. Pruebas (tests/Feature/BackupCrearTest.php)
- `Http::fake()` de las llamadas Graph (subir + listar + eliminar) — sin tocar
  SharePoint real (ya está el patrón `preventStrayRequests` en TestCase).
- Mock de `DbDump` (bind falso que devuelve un SQL fijo) para no dumpear en test.
- Asserts:
  - sube un archivo con nombre `*_rrhh.sql.gz` al destino `backups`.
  - purga: con items fake viejos + nuevos, borra solo los > retención.
  - el contenido subido está gzcomprimido (`gzdecode` devuelve el SQL fake).

## 6. Restaurar (documentar el procedimiento)
Para cuando haga falta:
1. Descargar el `.sql.gz` desde `IT/BACKUP_SISTEMAS/RRHH_Sistemas`.
2. Descomprimir → `.sql`.
3. cPanel → phpMyAdmin → base destino → **Importar** el `.sql`.
4. **Usar el mismo `APP_KEY`** en el `.env` o los campos cifrados no se leen.
Añadir esto a `docs/09` (despliegue) como "Restauración".

## 7. Checklist de implementación (mañana)
- [ ] 0. Verificar en cPanel: ¿`exec()` disponible? (define dumper) y permisos de la carpeta IT.
- [ ] 1. Config: destino `backups` + envs (`.env.example` y `.env` prod).
- [ ] 2. `DbDump` (PHP puro por PDO) — o `ifsnop/mysqldump-php`.
- [ ] 3. `SharePointDocs::listar()`.
- [ ] 4. Comando `backup:crear` (dump → gzip → subir → purgar).
- [ ] 5. Programar en scheduler + preparar el cron de cPanel.
- [ ] 6. Tests (Http::fake + DbDump mock).
- [ ] 7. Probar en local end-to-end contra SharePoint real (subir un backup de verdad, verlo en IT/BACKUP_SISTEMAS, borrarlo).
- [ ] 8. Documentar restauración en docs/09.
- [ ] 9. Al desplegar: crear el Cron Job en cPanel + primera corrida manual (`artisan backup:crear`).

## 8. Fuera de alcance (por ahora)
- Backup de archivos locales de `storage/app` (casi todo ya está en SharePoint;
  se puede sumar luego como un `.zip` adjunto).
- Backups cifrados con contraseña propia (la carpeta restringida ya da control de acceso).
- Notificación por correo del resultado (cuando haya SMTP configurado).
