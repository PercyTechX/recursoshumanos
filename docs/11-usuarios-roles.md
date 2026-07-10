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

## Pendiente / siguiente

- **Permisos finos** (matriz `permission` de spatie: `vacaciones.aprobar`,
  `planilla.ver`, `backups.gestionar`…) para configurar accesos sin tocar código.
- **Portal del trabajador** (autoservicio con el empleado vinculado): mis vacaciones,
  mis ausencias, mis boletas.
- **Backups** (exportar BD .sql + archivos .zip en PHP puro, para SuperAdmin).
- Forzar cambio de contraseña en el primer ingreso.
- "Olvidé mi contraseña" cuando se configure SMTP.
