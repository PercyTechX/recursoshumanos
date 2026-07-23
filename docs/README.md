# Documentación — Sistema RRHH (GDS Infraestructura SAC)

> Aplicación web de Recursos Humanos: empleados, documentos con vencimientos,
> vacaciones, ausencias/licencias, asistencia GPS, tickets, rendiciones de caja
> chica, boletas y backups automáticos. Laravel 12 + Livewire + MySQL + SharePoint.
>
> **Actualizado:** 2026-07-21 · **Producción:** https://rrhh.gds.pe

---

## Empieza aquí — elige tu perfil

### Usuario del sistema (RRHH, supervisor, trabajador)
Para **usar** el sistema en el día a día.
- [Guía de RRHH](usuario/guia-rrhh.md)
- [Guía del Supervisor](usuario/guia-supervisor.md)
- [Guía del Trabajador — "Mi espacio"](usuario/guia-trabajador.md)
- [Guía de Gerencia y Contador](usuario/guia-gerencia-contador.md)
- Referencia: [¿Qué puede hacer cada rol?](referencia/roles-y-permisos.md)

### Área de Sistemas / TI
Para **desplegar, mantener o resolver un incidente**.
- [Despliegue en cPanel](09-deploy-cpanel.md) · [Runbook del último re-deploy](20-redeploy-2026-07-21.md)
- [Restaurar un backup](operacion/restaurar-backup.md)
- [Crons y tareas programadas](operacion/crons-y-tareas.md)
- [Solución de problemas](operacion/troubleshooting.md)
- [Renovar el secret de Graph](operacion/rotar-secret-graph.md)
- [Arquitectura (C4)](referencia/arquitectura-c4.md) · [Integración SharePoint/Graph](15-integracion-sharepoint-graph.md)

### Gerencia / cliente
Para saber **qué se construyó y qué se recibe**.
- [Estado actual del proyecto](13-estado-actual.md)
- [Acta de entrega](entregables/acta-de-entrega.md)
- [Manual del administrador](entregables/manual-administrador.md)

### Mantenimiento del código
- [Stack tecnológico](01-stack-tecnologico.md) · [Buenas prácticas](03-buenas-practicas.md) · [Diseño UI](04-ui-diseno.md)
- [Plan de documentación](21-plan-documentacion.md) (cómo está organizado esto)

---

## Índice completo

### Fundamentos y arquitectura
| Doc | Contenido |
|---|---|
| [00](00-plan-de-ejecucion.md) | Plan de ejecución del proyecto |
| [01](01-stack-tecnologico.md) | Stack tecnológico |
| [02](02-arquitectura.md) | Arquitectura |
| [03](03-buenas-practicas.md) | Buenas prácticas de código |
| [04](04-ui-diseno.md) | Diseño de interfaz |
| [05](05-despliegue.md) / [09](09-deploy-cpanel.md) | Despliegue (general / cPanel) |

### Módulos de negocio
| Doc | Módulo |
|---|---|
| [06](06-activos.md) | Activos / EPP / Hoja de ruta |
| [07](07-ficha-empleado.md) | Ficha del empleado / expediente |
| [08](08-documentos-compartidos.md) | Documentos compartidos (SCTR colectivo) |
| [10](10-vacaciones.md) | Vacaciones |
| [11](11-usuarios-roles.md) | Usuarios y roles |
| [12](12-portal-trabajador.md) | Portal del trabajador ("Mi espacio") |
| [14](14-asistencia.md) / [18](18-asistencia-refrigerios-plan.md) | Asistencia GPS / refrigerios + VB |
| [16](16-rendiciones-plan.md) | Rendiciones (caja chica) |
| [17](17-licencias-plan.md) | Licencias |
| [19](19-backups-automaticos-plan.md) | Backups automáticos |

### Integración y operación
| Doc | Contenido |
|---|---|
| [15](15-integracion-sharepoint-graph.md) | SharePoint / Microsoft Graph |
| [13](13-estado-actual.md) | Estado actual y pendientes |
| [20](20-redeploy-2026-07-21.md) | Runbook del re-deploy 2026-07-21 |
| [21](21-plan-documentacion.md) | Plan de documentación |

---

## Cómo se mantiene esta documentación

- **Docs-as-code:** todo es Markdown versionado en este repo. Se actualiza en el
  mismo commit que el cambio de código.
- **Un tipo por página** (guía / referencia / explicación / runbook — no mezclar).
- **Fechado:** cada doc lleva su "Actualizado".
- Marco: [Diátaxis](https://diataxis.fr) + runbooks (SRE) + [C4](https://c4model.com).
  Detalle del plan en [docs/21](21-plan-documentacion.md).
