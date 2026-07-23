# Manual del Administrador (SuperAdmin)

> **Tipo:** Guía de administración · **Audiencia:** SuperAdmin / TI · **Actualizado:** 2026-07-23
>
> Tareas exclusivas del **SuperAdmin**: configurar accesos, mantener los catálogos
> del negocio, administrar usuarios y velar por la seguridad y los backups.

## Qué puede el SuperAdmin

El SuperAdmin **ve y hace todo** en el sistema (sin restricción de permisos). Además,
tiene pantallas exclusivas que no aparecen para otros roles:

- **Roles y accesos** — quién puede hacer qué.
- **Catálogos** — las listas maestras del negocio.

## 1. Roles y accesos

Menú **Roles y accesos**. Es una **matriz** de casillas: por cada **rol** y cada
**módulo/acción**, marcas o desmarcas el permiso.

- Los cambios son **inmediatos** y **persisten** (un re-deploy no los pisa: el seeder
  solo asigna permisos por defecto a un rol que aún **no tiene ninguno**).
- La referencia completa de qué trae cada rol por defecto está en
  [roles-y-permisos.md](../referencia/roles-y-permisos.md).

**Regla de oro:** da a cada rol **lo mínimo necesario**. Ej.: el "Visto Bueno" de
asistencia conviene dejarlo solo al Supervisor.

## 2. Catálogos del negocio

Menú **Catálogos**. Mantiene las listas maestras que alimentan los formularios:

- **Áreas** y **Cargos**
- **Tipos de documento** (con días de aviso de vencimiento y si es "compartible")
- **Categorías de activos** y **Tipos de EPP**

Por cada catálogo puedes **agregar**, **editar**, **activar/desactivar** y **eliminar**
(el sistema **impide eliminar** un ítem que esté en uso, para no romper datos).

> Ejemplo: para habilitar un nuevo tipo de documento de cese ("Convenio de mutuo
> disenso"), agrégalo en **Tipos de documento** y quedará disponible al instante.

## 3. Usuarios

Menú **Usuarios**. Crea las cuentas de acceso, asigna **roles** y **vincula** cada
usuario a su ficha de empleado (para su portal "Mi espacio"). Detalle en la
[Guía de RRHH §5](../usuario/guia-rrhh.md#5-usuarios-y-accesos).

Acciones por usuario: **restablecer contraseña**, **desactivar** (bloquea el acceso
sin borrar), **editar** y **eliminar**.

## 4. Baja de empleados: archivar vs. purgar

Hay **dos niveles**, a propósito:

| Acción | Quién | Cuándo | Efecto |
|---|---|---|---|
| **Archivar** (soft delete) | RRHH | Cese normal | Sale de la lista activa; se puede **ver en "archivados"** y restaurar. Conserva su historial. |
| **Purgar** (borrado permanente) | **Solo SuperAdmin** | Empleado **mal ingresado** | Borra definitivamente. **Solo se permite si NO tiene historial** (documentos, asistencia, etc.). |

Usa **purgar** solo para corregir un registro creado por error. Para un cese real,
**archiva** (nunca se pierde la información).

## 5. Backups

Los backups de la base son **automáticos** (diario 02:00 a SharePoint IT). Como
administrador debes:

- Verificar de vez en cuando que llegan a `IT/BACKUP_SISTEMAS/RRHH_Sistemas`.
- Saber **restaurar** uno: [restaurar-backup.md](../operacion/restaurar-backup.md).
- Custodiar el **APP_KEY** (sin él, los backups con datos cifrados no se recuperan).

## 6. Seguridad — tu responsabilidad

- **APP_KEY**: guardado en gestor de contraseñas **y** junto a los backups. Nunca se cambia.
- **Secret de Graph**: vence; renovarlo antes ([rotar-secret-graph.md](../operacion/rotar-secret-graph.md)).
- **Accesos**: revisar periódicamente usuarios activos y sus roles.
- **`.env`**: nunca se sube al repositorio (contiene todos los secretos).
- **Carpeta de backups** en SharePoint: acceso restringido (contiene datos personales).

## Referencias
- [Roles y permisos](../referencia/roles-y-permisos.md)
- [Arquitectura](../referencia/arquitectura-c4.md)
- [Despliegue](../09-deploy-cpanel.md) · [Crons](../operacion/crons-y-tareas.md) ·
  [Solución de problemas](../operacion/troubleshooting.md)
