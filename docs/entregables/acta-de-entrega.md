# Acta de Entrega — Sistema RRHH

> **Tipo:** Entregable (formal) · **Audiencia:** Gerencia / Cliente · **Actualizado:** 2026-07-23
>
> Documento de entrega del Sistema de Recursos Humanos. Los campos entre `[  ]` se
> completan al firmar. Este documento no contiene contraseñas ni secretos: los
> accesos se entregan por canal seguro aparte.

## 1. Partes

- **Proveedor (desarrollo):** PercyTech – Solutions · RUC 10463288271
- **Cliente:** GDS Infraestructura SAC · RUC 20551555187
- **Producto:** Sistema web de Recursos Humanos — **https://rrhh.gds.pe**
- **Fecha de entrega:** `[dd/mm/aaaa]`

## 2. Alcance entregado

Sistema web en producción para la gestión integral de personal, que incluye:

| Módulo | Función |
|---|---|
| Empleados y expediente | Ficha legal completa, expediente por pestañas, derechohabientes |
| Documentos y vigencias | Control de vencimientos con semáforo y avisos por WhatsApp |
| Documentos compartidos | Un archivo (ej. SCTR) que ampara a varias personas |
| Asistencia GPS | Marcación con ubicación, control, refrigerios y VB, reportes y export |
| Tickets | Órdenes de trabajo con ejecución por geocerca |
| Vacaciones | Solicitud, saldo automático, retorno anticipado |
| Ausencias / Licencias | Solicitud del trabajador con doble aprobación (supervisor → RRHH) |
| Boletas de pago | Publicación de boletas y confirmación de recepción |
| Rendiciones (caja chica) | Depósito a técnico, rendición de gastos, hoja resumen PDF |
| Activos / EPP | Inventario, entregas con firma, hoja de ruta |
| Descuentos | Registro y control |
| Certificado de trabajo | Generación automática en PDF, personalizado |
| Portal del trabajador | Autoservicio "Mi espacio" |
| Administración | Roles y accesos, catálogos, usuarios |
| Backups | Respaldo diario automático de la base de datos |

Integraciones: **Microsoft 365 / SharePoint** (almacenamiento de archivos y backups).

## 3. Estado de entrega

- Sistema **en producción y operativo** en https://rrhh.gds.pe.
- Base de datos con **backups automáticos diarios** a SharePoint (biblioteca IT).
- Datos financieros sensibles **cifrados** en la base.
- Documentación de usuario, operación y administración incluida (carpeta `docs/`).

## 4. Accesos y credenciales entregados

Se entregan por **canal seguro** (gestor de contraseñas / documento privado), **no**
en esta acta:

- [ ] Acceso de administrador (SuperAdmin) del sistema.
- [ ] Credenciales de la base de datos.
- [ ] `APP_KEY` de la aplicación (crítico para los datos cifrados y los backups).
- [ ] Datos de la app de Microsoft (tenant/client id) y secret vigente.
- [ ] Acceso al panel de hosting (cPanel) y al repositorio de código.

## 5. Documentación entregada

- Guías de usuario (Trabajador, RRHH, Supervisor).
- Manual del administrador y referencia de roles/permisos.
- Runbooks de operación (despliegue, backups, restauración, solución de problemas).
- Documentación técnica de arquitectura y decisiones (`docs/`).

## 6. Garantía y soporte

- Periodo de garantía: `[__] días/meses` desde la fecha de entrega, para corrección
  de defectos del alcance entregado.
- Canal de soporte: `[WhatsApp / correo]`.
- Fuera de garantía: nuevas funcionalidades y cambios de alcance se cotizan aparte.

## 7. Recomendaciones al cliente

1. Custodiar el **APP_KEY** y las credenciales en un gestor de contraseñas con 2FA.
2. Renovar el **secret de Microsoft** antes de su vencimiento.
3. Restringir el acceso a la carpeta de **backups** en SharePoint.
4. Mantener actualizada la información de personal (fichas, supervisores) para que los
   avisos y reportes funcionen correctamente.

## 8. Conformidad

| | Proveedor (PercyTech) | Cliente (GDS Infraestructura SAC) |
|---|---|---|
| Nombre | `[__________]` | `[__________]` |
| Cargo | `[__________]` | `[__________]` |
| Firma | | |
| Fecha | `[__/__/____]` | `[__/__/____]` |
