# Usuarios, roles y Super Admin

> **Estado:** ✅ IMPLEMENTADO (2026-07-10, rama `feature/usuarios`).

## Roles

`SuperAdmin`, `RRHH`, `Supervisor`, `Gerencia`, `Empleado`, `Contador`
(spatie/laravel-permission).

- **SuperAdmin ve y hace todo.** Se implementa con un `Gate::before` en
  `AppServiceProvider` (cualquier `@can`/Gate le devuelve `true`) y está incluido en
  todos los grupos de rutas y en los enlaces del menú.

## Módulo Usuarios (menú "Usuarios", acceso SuperAdmin/RRHH)

- **CRUD de usuarios**: nombre, correo, contraseña, estado (activo).
- **Asignar roles** (checkboxes, múltiples).
- **Vincular a un empleado** (1‑1, `empleados.user_id`) → base para el autoservicio.
- **Restablecer contraseña** (el admin pone una nueva).
- **Activar / desactivar**: un usuario inactivo **no puede iniciar sesión** (control
  en `LoginForm`).
- **Eliminar** (con protecciones).

## Reglas de seguridad

- Solo un **SuperAdmin** puede **otorgar** el rol SuperAdmin (a un RRHH se le filtra).
- No se puede **tocar** a un SuperAdmin si tú no lo eres.
- No puedes **desactivarte ni eliminarte** a ti mismo.
- No se puede eliminar al **último** SuperAdmin.

## Roles y accesos (matriz módulo × acción) ✅

Pantalla **"Roles y accesos"** (menú, solo SuperAdmin) para configurar accesos sin
tocar código:

- **Permisos** = `<modulo>.<accion>` definidos en `config/permisos.php`
  (empleados, documentos, doc. compartidos, activos, vacaciones, ausencias,
  descuentos, usuarios × ver/crear/editar/eliminar/… + acciones propias como
  `documentos.avisar`, `vacaciones.aprobar`, `activos.asignar`, `descuentos.aplicar`).
- **Crear/renombrar roles** y marcar con checkboxes qué **módulos y acciones** puede
  cada rol (los roles del sistema no se eliminan).
- **Se respeta en todos lados:** el **menú** y las **rutas** usan `@can`/`role_or_permission`
  (solo ves los módulos permitidos) y cada **acción** en las pantallas está protegida
  (`@can` en el botón + `abort_unless` en el método). SuperAdmin pasa siempre.
- **Defaults** en `CatalogoSeeder` (solo si el rol no tiene permisos aún, para no
  pisar lo que se configure desde la pantalla).

## Pendiente / siguiente

- **Portal del trabajador** (autoservicio con el empleado vinculado): mis vacaciones,
  mis ausencias, mis boletas.
- **Backups** (exportar BD .sql + archivos .zip en PHP puro, para SuperAdmin).
- Forzar cambio de contraseña en el primer ingreso.
- "Olvidé mi contraseña" cuando se configure SMTP.
