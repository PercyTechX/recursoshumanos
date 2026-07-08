# Stack Tecnológico

Cada elección responde a un criterio: **funcionar de forma estable sobre el
hosting contratado, sin costos adicionales y con buenas prácticas**.

---

## Resumen

| Capa | Tecnología | Versión objetivo |
|---|---|---|
| Lenguaje | PHP | 8.2 |
| Framework | Laravel | Última estable compatible con PHP 8.2 |
| Base de datos | MySQL / MariaDB | 8.x |
| Vistas | Blade | (incluido en Laravel) |
| Interactividad | Livewire | 3.x |
| Estilos | Tailwind CSS | 3.x |
| JS ligero | Alpine.js | 3.x |
| Autenticación | Laravel Breeze | última |
| Roles/permisos | spatie/laravel-permission | última |
| Exportes | maatwebsite/excel · dompdf | última |
| Archivos externos | Microsoft Graph (OneDrive/SharePoint) | v1.0 |
| Calidad de código | Laravel Pint · Larastan | última |
| Pruebas | Pest (o PHPUnit) | última |

---

## Por qué este stack

### PHP + Laravel
El hosting es **cPanel compartido con PHP 8.2** y **sin soporte fiable de Node.js
en servidor**. Laravel es el framework PHP moderno más maduro: corre nativo en
cPanel, tiene ecosistema completo (auth, colas, validación, ORM) y excelente
documentación. **Descarta Next.js/Node** por incompatibilidad con el hosting.

### MySQL
Incluido en el plan (15 bases de datos, 1 GB c/u). Suficiente y sobrado para
20–100 empleados. El diseño evita depender de features exclusivas para permitir
portabilidad futura.

### Blade + Livewire + Tailwind + Alpine
- **Blade**: motor de plantillas nativo, simple y seguro.
- **Livewire**: interactividad (formularios dinámicos, tablas, semáforo en vivo)
  **sin escribir una SPA**. Menos complejidad, ideal para un equipo pequeño.
- **Tailwind + Alpine**: UI moderna y liviana.

> ⚠️ **Nota de despliegue:** Tailwind se compila con Vite (Node) en **local**
> (Laragon incluye Node). Los assets ya compilados (`public/build`) se versionan
> y suben — así el servidor **no necesita Node**.

### Almacenamiento de documentos: OneDrive / SharePoint
El plan tiene tope de 200.000 archivos; los escaneos de RRHH se acumulan. Se
guardan en **OneDrive/SharePoint** (Microsoft 365 ya contratado) vía **Graph API**:
respaldo, versiones y seguridad de Microsoft, sin saturar el hosting. En la base
de datos solo se guarda el **enlace/ID** del archivo; **las fechas de vigencia,
el semáforo y las alertas viven en MySQL**.

### spatie/laravel-permission
Estándar de facto para roles y permisos en Laravel. Permite que cada módulo nuevo
solo **sume permisos**, sin tocar la lógica existente.

---

## Dónde corre cada cosa

```
[ Navegador ]  usuarios (RRHH / Supervisor / Gerencia / Empleado)
      │
      ▼
[ Laravel + MySQL ]  →  hosting yachay.lat (cPanel)  ·  rrhh.gds.pe
      │
      ▼
[ OneDrive / SharePoint ]  →  PDFs/imágenes (Microsoft 365)
```

---

## Herramientas de desarrollo (local)

- **Laragon** — entorno todo-en-uno (PHP, Composer, MySQL, Node, Apache).
- **Composer** — dependencias PHP.
- **Git + GitHub** — control de versiones (repo privado `PercyTechX/recursoshumanos`).
- **Laravel Pint** — formateo automático (estándar PSR-12).
- **Larastan (PHPStan)** — análisis estático.
- **Pest** — pruebas legibles.

---

## Restricciones conocidas del hosting

| Restricción | Impacto | Mitigación |
|---|---|---|
| Sin Node.js en servidor | No se compilan assets en prod | Compilar en local y subir `public/build` |
| **Sin SSH** (confirmado por Yachay, 2026-07-08) | No `composer`/`artisan`/`npm` en servidor | Subir `vendor/` + `public/build` ya compilados; migrar vía SQL/phpMyAdmin. Ver [despliegue](05-despliegue.md) |
| Máx. 200.000 archivos | Riesgo al acumular escaneos | Documentos en OneDrive, no en el disco del hosting |
| Sin API de envío a SUNAT | No hay planilla automática | Módulo de **exportación** para PDT PLAME (futuro) |
