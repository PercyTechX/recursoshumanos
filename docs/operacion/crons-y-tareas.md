# Runbook — Crons y tareas programadas

> **Tipo:** Runbook (operación) · **Audiencia:** Sistemas / TI · **Actualizado:** 2026-07-21
>
> Tareas automáticas del sistema, cómo se programan en cPanel (sin SSH) y cómo
> verificarlas.

## Binario de PHP en producción

Todas las tareas usan el PHP 8.2 de este servidor (CloudLinux/EasyApache):

```
/opt/cpanel/ea-php82/root/usr/bin/php
```

Ruta del proyecto (usuario cPanel `oipfutlf`):

```
/home/oipfutlf/repositories/recursoshumanos
```

## Tareas programadas

### 1. Backup diario de la base de datos ✅ ACTIVO

- **Comando (`artisan`):** `backup:crear`
- **Qué hace:** vuelca la BD (dumper PHP, sin `mysqldump`), la comprime `.sql.gz`
  y la sube a SharePoint `IT/BACKUP_SISTEMAS/RRHH_Sistemas`. Purga los > 30 días.
- **Horario:** diario 02:00.
- **Cron Job en cPanel:**

  | Minute | Hour | Day | Month | Weekday |
  |:--:|:--:|:--:|:--:|:--:|
  | 0 | 2 | * | * | * |

  **Command:**
  ```
  /opt/cpanel/ea-php82/root/usr/bin/php /home/oipfutlf/repositories/recursoshumanos/artisan backup:crear >> /home/oipfutlf/backup-cron.log 2>&1
  ```
- **Verificar:** revisa `/home/oipfutlf/backup-cron.log` (debe decir
  "Subido a SharePoint: …_rrhh.sql.gz") o mira la carpeta en SharePoint.
- Detalle: [docs/19](../19-backups-automaticos-plan.md).

### 2. Reintento de subidas a SharePoint 🟡 OPCIONAL (recomendado)

- **Comando:** `rendiciones:subir-pendientes`
- **Qué hace:** reintenta subir a SharePoint los archivos que quedaron **locales**
  porque Microsoft falló en el momento (estado "pendiente").
- **Horario sugerido:** cada hora, o cada 15 min.
- **Command:**
  ```
  /opt/cpanel/ea-php82/root/usr/bin/php /home/oipfutlf/repositories/recursoshumanos/artisan rendiciones:subir-pendientes >> /home/oipfutlf/pendientes-cron.log 2>&1
  ```

## Cómo crear/editar un Cron Job (yachay)

1. Panel → **Cron Jobs** → **+ Add Cron Job**.
2. Llena Minute/Hour/Day/Month/Weekday + Command → **Confirm**.
3. Para probar de inmediato: pon `*/5` en Minute, espera y revisa el `.log`;
   al confirmar que funciona, cámbialo al horario real.

## Comandos artisan disponibles (referencia)

| Comando | Uso |
|---|---|
| `backup:crear` | Backup de la BD → SharePoint (flag `--local` = solo local) |
| `graph:ping` | Prueba token + sitio + biblioteca de SharePoint |
| `rendiciones:subir-pendientes` | Reintenta archivos locales pendientes → SharePoint |

## Nota sobre el scheduler de Laravel

El proyecto tiene `Schedule::command('backup:crear')->dailyAt('02:00')` en
`routes/console.php`. En un servidor con SSH se activaría con un solo cron
`schedule:run` cada minuto; **como yachay no tiene SSH, se llama al comando
directo** (como arriba). Ambas formas son válidas; aquí usamos la directa.

## Relacionado
- [Restaurar un backup](restaurar-backup.md)
- [Despliegue en cPanel](../09-deploy-cpanel.md)
