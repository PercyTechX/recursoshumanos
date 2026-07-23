# Runbook — Renovar el secret de Microsoft Graph

> **Tipo:** Runbook (operación) · **Audiencia:** Sistemas / TI · **Actualizado:** 2026-07-23
>
> El secret (contraseña) de la app de SharePoint **vence** y hay que renovarlo antes,
> o las subidas de archivos dejarán de funcionar. Este runbook explica cómo.

## Cuándo hacerlo

- **Antes de la fecha de vencimiento** del secret activo (revisar en Entra).
- Si aparece el error **`AADSTS7000222 ... client secret ... expired`** al subir archivos
  o en `graph:ping`.
- Si el secret se filtró (rotación de seguridad).

> El secret activo del proyecto está registrado en el gestor de contraseñas del admin.
> Ver [docs/13 §9](../13-estado-actual.md).

## Contexto

- La app se llama **"RRHH-Docs"** en Entra (Microsoft Entra ID / Azure AD).
- Autenticación **app-only** (client credentials): tenant id + client id + **secret**.
- El permiso de acceso al sitio (**Sites.Selected**) **NO** depende del secret: al
  cambiar el secret, el permiso sigue vigente. Solo cambia la "contraseña".

## Pasos

### 1. Crear un secret nuevo en Entra
1. Entra a **https://entra.microsoft.com** con la cuenta admin de Microsoft 365 de GDS.
2. **Identidad → Aplicaciones → Registros de aplicaciones** → abre **"RRHH-Docs"**.
3. Menú **Certificados y secretos** → pestaña **Secretos de cliente** →
   **"+ Nuevo secreto de cliente"**.
4. Descripción: `rrhh-<año>` · Vencimiento: 24 meses · **Agregar**.
5. **Copia de inmediato el "Valor"** (no el "Id."). Solo se muestra esta vez.

### 2. Actualizar el `.env` de producción
En File Manager, edita el `.env` del proyecto y reemplaza:
```dotenv
GRAPH_CLIENT_SECRET=<pega aquí el nuevo Valor>
```
(Los `GRAPH_TENANT_ID` y `GRAPH_CLIENT_ID` **no cambian**.)

### 3. Limpiar la caché de config y del token
El token de Graph se cachea. Para que tome el secret nuevo:
- Si tienes acceso a `/_setup` (con token puesto), al correrlo se re-valida con
  `graph:ping`. Alternativamente, un Cron Job de un solo uso:
  ```
  /opt/cpanel/ea-php82/root/usr/bin/php /home/oipfutlf/repositories/recursoshumanos/artisan config:clear
  ```
- El token cacheado expira solo; ante un 401, el sistema pide uno nuevo automáticamente.

### 4. Verificar
- Corre **`graph:ping`** (vía `/_setup` o cron). Debe salir en verde:
  token obtenido, sitio OK, biblioteca OK.
- Sube un documento de prueba y confirma que llega a SharePoint.

### 5. Limpieza
- En Entra, puedes **eliminar el secret viejo** una vez confirmado que el nuevo funciona
  (o dejarlo hasta que venza).
- Actualiza el **gestor de contraseñas** con el secret nuevo y su fecha de vencimiento.
- Pon un **recordatorio** para renovarlo antes del próximo vencimiento.

## Relacionado
- [Solución de problemas](troubleshooting.md)
- [Integración SharePoint/Graph](../15-integracion-sharepoint-graph.md)
