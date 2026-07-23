# Runbook — Restaurar un backup de la base de datos

> **Tipo:** Runbook (operación) · **Audiencia:** Sistemas / TI · **Actualizado:** 2026-07-21
>
> Cómo restaurar la base `oipfutlf_rrhh` desde un backup. Úsalo si hubo pérdida de
> datos, una migración salió mal, o para clonar prod en un entorno de prueba.

## Antes de empezar — lee esto

**El `APP_KEY` es obligatorio.** Los campos financieros (número de cuenta, CCI,
sueldo) están **cifrados** con el `APP_KEY` del `.env`. Si restauras la BD en un
servidor con **otro** `APP_KEY`, esos datos quedan **ilegibles para siempre**.
→ Restaura siempre con el **mismo `APP_KEY`** con el que se creó el backup.
(Está en el gestor de contraseñas; ver [docs/13 §9](../13-estado-actual.md).)

## Dónde están los backups

- **Automáticos (diarios):** SharePoint → biblioteca **IT** →
  `BACKUP_SISTEMAS/RRHH_Sistemas/` → archivos `AAAA-MM-DD_HHMMSS_rrhh.sql.gz`.
- **Manuales** (si alguien exportó por phpMyAdmin): donde se haya guardado.
- Retención automática: **30 días** (los más viejos se purgan solos).

## Procedimiento (sin SSH, vía phpMyAdmin)

1. **Descarga** el `.sql.gz` deseado desde SharePoint (`IT/BACKUP_SISTEMAS/RRHH_Sistemas`).
2. **Descomprime** el `.gz` en tu PC → obtienes un `.sql`.
   (Windows: 7-Zip → "Extraer aquí". El archivo interno es texto SQL plano.)
3. cPanel → **phpMyAdmin** → selecciona la base **`oipfutlf_rrhh`** en el panel izquierdo.
4. *(Recomendado)* Antes de importar, **exporta el estado actual** como respaldo
   (por si el backup elegido no era el correcto).
5. Pestaña **Importar** → **Seleccionar archivo** → elige el `.sql` → **Continuar**.
   - El backup trae `DROP TABLE IF EXISTS` + `CREATE TABLE`, así que **reemplaza**
     las tablas existentes por las del backup. No hace falta vaciar la base a mano.
6. Espera a "Importación ejecutada correctamente".

> Si el `.sql` es muy grande y phpMyAdmin corta por tamaño/tiempo: súbelo por
> **File Manager** a una carpeta privada y usa **phpMyAdmin → Importar → desde
> archivo del servidor**, o pártelo. Para el tamaño actual (~30 KB comprimido) no aplica.

## Verificación post-restauración

1. `APP_KEY` en el `.env` = el que corresponde al backup.
2. Entra a la app y abre la **ficha de un empleado con cuenta/sueldo**:
   - Datos **legibles** → cifrado OK.
   - Datos como `eyJpdiI6...` → **APP_KEY equivocado**, no sigas: corrige el `.env`.
3. Revisa que el conteo de empleados/registros sea el esperado del backup.

## Restaurar en un entorno de PRUEBA (clonar prod)

Igual que arriba, pero en otra base y con **el mismo `APP_KEY` de prod** (o los
datos cifrados no se leerán). Nunca uses datos reales de personas en un entorno
sin control de acceso.

## Generar un backup a demanda (no esperar al cron)

Sin SSH no se corre `artisan` a mano, pero puedes:
- **phpMyAdmin → Exportar** (SQL + gzip) — rápido y suficiente.
- O crear un **Cron Job de un solo uso** que ejecute `backup:crear`
  (ver [crons-y-tareas.md](crons-y-tareas.md)) y luego borrarlo.

## Relacionado
- [Crons y tareas programadas](crons-y-tareas.md)
- [Plan de backups](../19-backups-automaticos-plan.md)
- [Despliegue en cPanel](../09-deploy-cpanel.md)
