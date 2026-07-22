# Referencia — Roles y permisos

> **Tipo:** Referencia · **Audiencia:** RRHH, TI/Sistemas · **Actualizado:** 2026-07-21
>
> Qué puede hacer cada rol en el sistema. Los permisos se controlan por
> `<módulo>.<acción>` y son **configurables** desde **Roles y accesos** (SuperAdmin).
> Esta página describe la **asignación por defecto** (fuente: `config/permisos.php`
> y `database/seeders/CatalogoSeeder.php`).

## Los 6 roles

| Rol | Para quién | Idea general |
|---|---|---|
| **SuperAdmin** | Dueño técnico / TI | Ve y hace **todo** (sin restricción). |
| **RRHH** | Recursos Humanos | Gestión completa de personal, documentos, boletas, usuarios. |
| **Gerencia** | Dirección | Como RRHH pero **sin gestión de usuarios**. |
| **Supervisor** | Jefes de campo | Su equipo: asistencia (**con Visto Bueno**), tickets, licencias (visa). |
| **Contador** | Contabilidad | Solo **descuentos**. |
| **Empleado** | Trabajador | Sin acceso administrativo; usa su **portal "Mi espacio"** (ver abajo). |

## Matriz por defecto (módulo × rol)

✅ todas las acciones · 🟡 parcial (ver nota) · — sin acceso

| Módulo | SuperAdmin | RRHH | Gerencia | Supervisor | Contador | Empleado |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| Empleados | ✅ | ✅ | ✅ | ✅ | — | — |
| Documentos | ✅ | ✅ | ✅ | ✅ | — | — |
| Documentos compartidos | ✅ | ✅ | ✅ | ✅ | — | — |
| Boletas de pago | ✅ | ✅ | ✅ | — | — | — |
| Activos / EPP | ✅ | ✅ | ✅ | ✅ | — | — |
| Vacaciones | ✅ | ✅ | ✅ | ✅ | — | — |
| Ausencias / Licencias | ✅ | 🟡¹ | 🟡¹ | 🟡¹ | — | — |
| Descuentos | ✅ | ✅ | ✅ | — | ✅ | — |
| Clientes y sedes | ✅ | ✅ | ✅ | ✅ | — | — |
| Tickets | ✅ | ✅ | ✅ | ✅ | — | — |
| Asistencia (control) | ✅ | 🟡² | 🟡² | ✅ | — | — |
| Rendiciones | ✅ | ✅ | ✅ | ✅ | — | — |
| Usuarios | ✅ | ✅ | — | — | — | — |

**Notas**
- ¹ **Ausencias/Licencias:** la acción **"Visar (supervisor)"** por defecto la ejerce
  el supervisor directo del trabajador; RRHH/Gerencia **aprueban**. El flujo de licencias
  es de doble paso: *Supervisor visa → RRHH aprueba* (ver [docs/17](../17-licencias-plan.md)).
- ² **Asistencia:** el **"Visto Bueno" (`asistencia.vb`)** es exclusivo del **Supervisor**
  por defecto; RRHH/Gerencia ven y corrigen asistencia pero no dan el VB
  (ver [docs/18](../18-asistencia-refrigerios-plan.md)).

## Catálogo completo de módulos y acciones

Fuente única: `config/permisos.php`. Cada permiso es `módulo.acción`.

| Módulo | Acciones |
|---|---|
| **empleados** | ver, crear, editar, eliminar, exportar |
| **documentos** | ver, crear, editar, eliminar, avisar (al supervisor) |
| **boletas** | ver, subir, eliminar |
| **documentos_compartidos** | ver, crear, editar, eliminar |
| **activos** | ver, crear, editar, eliminar, asignar (/devolver) |
| **vacaciones** | ver, crear, aprobar (/rechazar), eliminar (cancelar) |
| **ausencias** | ver, crear, editar, eliminar, visar (supervisor), aprobar (/rechazar) |
| **descuentos** | ver, aplicar (marcar aplicado) |
| **clientes** | ver, crear, editar, eliminar |
| **tickets** | ver, crear, editar, cerrar, eliminar |
| **asistencia** | ver, registrar (manual), editar (/corregir), vb (visto bueno supervisor) |
| **rendiciones** | ver, registrar (depósito), aprobar (/rechazar), ampliar (monto), anular |
| **usuarios** | ver, crear, editar, eliminar |

## Reglas especiales (importantes)

1. **SuperAdmin pasa siempre.** Vía `Gate::before` — no depende de permisos asignados;
   ve y hace todo.
2. **Los defaults NO pisan lo configurado.** El seeder asigna permisos a un rol **solo
   si ese rol aún no tiene ninguno**. Si ya ajustaste la matriz en "Roles y accesos",
   re-sembrar (deploy) **no la sobrescribe**.
3. **El "Empleado" no usa permisos.** Su acceso es su **portal "Mi espacio"**, que
   funciona **por pertenencia**: ve solo los datos de la ficha de empleado vinculada a
   su usuario (documentos, boletas, vacaciones, ausencias, rendiciones, asistencia).
   Cualquier usuario **con ficha vinculada** tiene "Mi espacio" — incluidos supervisores
   y RRHH (ver [docs/12](../12-portal-trabajador.md)).
4. **Un permiso = ver el módulo.** Sin `<módulo>.ver`, el módulo no aparece en el menú
   ni es accesible por URL (middleware `role_or_permission`).

## Cómo cambiar accesos

**SuperAdmin → Roles y accesos:** matriz de casillas por rol y módulo/acción.
Los cambios son inmediatos y persisten (no los pisa el deploy). Ver [docs/11](../11-usuarios-roles.md).
