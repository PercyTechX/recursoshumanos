# Plan de Ejecución

Plan por fases. Cada fase es **incremental y desplegable**: al terminarla, el
sistema funciona y aporta valor. No se pasa a la siguiente sin cerrar la anterior
(código revisado, probado y desplegado).

> Principio rector: **entregar valor temprano y seguido**. El MVP son las Fases 1–4.

---

## Fase 0 — Preparación del entorno ⚙️

Prerrequisitos antes de programar.

- [ ] Instalar **Laragon** (PHP 8.2, Composer, MySQL, Node.js) en la PC.
- [ ] Crear repositorio **privado** en GitHub (`PercyTechX/recursoshumanos`).
- [ ] Inicializar proyecto Laravel + primer commit + `.gitignore`.
- [ ] Configurar `.env.example` (sin credenciales reales).
- [ ] Enviar ticket a yachay solicitando **acceso SSH** (opcional pero recomendado).
- [ ] Crear subdominio `rrhh.gds.pe` en cPanel (cuando toque desplegar).

**Entregable:** proyecto Laravel corriendo en local + repo en GitHub.

---

## Fase 1 — Núcleo (Core) 🏛️

La base sobre la que se enchufan todos los módulos.

- [ ] Autenticación (Laravel Breeze).
- [ ] Roles y permisos (`spatie/laravel-permission`): RRHH, Supervisor, Gerencia, Empleado.
- [ ] Catálogos base: `areas` (jerárquicas), `cargos`, `sedes`.
- [ ] Módulo **Empleados**: CRUD + ficha completa (campos "T-Registro ready").
- [ ] Vínculo empleado ↔ usuario ↔ supervisor.
- [ ] Layout base (Tailwind) + navegación por rol.

**Entregable:** login, gestión de empleados y organización interna.

---

## Fase 2 — Motores reutilizables + Documentos 📄🚦

Se construyen los 3 motores una sola vez y se entrega el módulo central.

- [ ] **Motor de archivos** (polimórfico) — integración con OneDrive/SharePoint (Graph API).
- [ ] Catálogo `tipos_documento` (con días de aviso previo y si requiere vigencia).
- [ ] Módulo **Documentos**: subida, historial y fechas de emisión/vencimiento.
- [ ] **Semáforo de vigencia** 🚦 (vigente / por vencer / vencido) calculado.
- [ ] Tablero de vencimientos + filtros por área/empleado.

**Entregable:** control de documentos con alertas visuales de vencimiento.

---

## Fase 3 — Vacaciones y permisos 🏖️

- [ ] **Motor de solicitudes + aprobaciones** (genérico, reutilizable).
- [ ] Catálogo `tipos_solicitud` (vacaciones, permiso, licencia).
- [ ] Flujo: solicitar → aprobar/rechazar (supervisor) → notificar.
- [ ] **Saldo de vacaciones** tipo libro contable (`saldos` + `movimientos`):
      apertura (fecha de corte) + devengado − gozado + ajustes.

**Entregable:** gestión completa de vacaciones con saldo auditable.

---

## Requisitos transversales (aplican a todos los módulos) 🔁

Estos requisitos se implementan en cada módulo que muestre datos, no en una fase
aislada:

- **Exportar a Excel por tabla.** Todo listado (empleados, documentos, vacaciones…)
  incluye un botón **"Exportar a Excel"** que descarga lo mostrado en pantalla
  **respetando los filtros aplicados**. Implementación con `maatwebsite/excel`.
- **Datos de prueba desechables.** Se separan los seeders:
  - `CatalogoSeeder` → datos permanentes reales (roles, tipos de documento, tipos de solicitud).
  - `DemoSeeder` → datos de prueba (empleados/documentos ficticios) **desechables**.
  - Acción de administrador **"Vaciar datos de prueba"** que borra datos transaccionales
    (empleados, documentos, solicitudes) y **conserva catálogos, roles y usuarios**,
    dejando el sistema limpio para producción.

---

## Fase 4 — Reportes, alertas y despliegue 📊🚀

- [ ] Reportes descargables (Excel/PDF) de vencimientos y vacaciones.
- [ ] Botón "avisar al supervisor" (correo/mensaje) por documento próximo a vencer.
- [ ] Notificaciones internas (campanita) y por correo.
- [ ] Compilar assets (`npm run build`) y preparar `.cpanel.yml`.
- [ ] **Despliegue** en `rrhh.gds.pe` vía GitHub → cPanel Git (Deploy HEAD Commit).
- [ ] Pruebas en producción + carga inicial de datos reales.

**Entregable:** 🎉 **MVP en producción** y en uso por la empresa.

---

## Fase 5 — Futuro (ya soportado por el diseño) 🔭

Módulos "enchufables". No se construyen ahora; el diseño reserva los ganchos.

- [ ] ⏱️ **Tareo / Asistencia** (`marcaciones`, `jornadas`).
- [ ] 🔧 **Control de activos** (`activos`, `categorias_activo`, motor de `asignaciones`).
- [ ] 🆔 **Autocompletado por DNI** (RENIEC CEL oficial / API de terceros).
- [ ] 🧾 **Planilla**: catálogo `conceptos`, `derechohabientes`, descansos médicos/CITT,
      licencias, y **exportación** para PDT PLAME (no envío directo — SUNAT no lo permite por API).
- [ ] 🤖 **IA de bajo costo** (Claude Haiku): extracción automática de fechas de vencimiento,
      chatbot para empleados, redacción de avisos, resúmenes para gerencia.

Detalle legal/técnico de estas integraciones en la memoria del proyecto.

---

## Convención de trabajo por fase

Para cada fase se sigue el ciclo:

1. **Rama** por funcionalidad (`feature/...`).
2. **Desarrollo** siguiendo [buenas prácticas](03-buenas-practicas.md).
3. **Migraciones + seeders** de datos de prueba.
4. **Pruebas** (feature tests) de lo crítico.
5. **Revisión** y merge a `main`.
6. **Despliegue** (desde Fase 4 en adelante).
