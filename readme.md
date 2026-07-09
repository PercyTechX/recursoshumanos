# Sistema de Recursos Humanos (RRHH)

Webapp modular para la gestión de Recursos Humanos de la empresa. Diseñada para
crecer por módulos sin rehacer lo existente, y preparada para integraciones
futuras (Planilla Electrónica SUNAT, contabilidad, IA de bajo costo).

> 📍 Empresa en Perú · 20–100 empleados · Roles: RRHH, Supervisor, Gerencia, Empleado.

---

## 🎯 Qué resuelve (MVP)

| Módulo | Descripción |
|---|---|
| 👤 **Empleados** | Ficha del personal (datos personales, cargo, área, contrato). |
| 📄 **Documentos + vigencias** | SCTR, antecedentes, EMO, homologación, etc. con control de vencimiento. |
| 🚦 **Control de vencimientos** | Tablero semáforo (vigente/por vencer/vencido) + reporte + aviso al supervisor. |
| 🏖️ **Vacaciones y permisos** | Solicitudes con flujo de aprobación y saldo de días. |

---

## 🛠️ Stack (resumen)

- **Backend:** PHP 8.2 + **Laravel** (última estable compatible con PHP 8.2)
- **Base de datos:** MySQL 8 / MariaDB
- **Frontend:** Blade + **Livewire** + **Tailwind CSS** + Alpine.js
- **Auth/Roles:** Laravel Breeze + `spatie/laravel-permission`
- **Archivos (documentos):** OneDrive / SharePoint (Microsoft 365, vía Graph API)
- **Hosting:** yachay.lat (cPanel, PHP 8.2, MySQL) — subdominio `rrhh.gds.pe`
- **Despliegue:** GitHub → cPanel Git (Clone + Deploy HEAD Commit con `.cpanel.yml`)

Detalle y justificación en [docs/01-stack-tecnologico.md](docs/01-stack-tecnologico.md).

---

## 📚 Documentación

| Documento | Contenido |
|---|---|
| [docs/00-plan-de-ejecucion.md](docs/00-plan-de-ejecucion.md) | Fases, entregables y checklist de ejecución. |
| [docs/01-stack-tecnologico.md](docs/01-stack-tecnologico.md) | Stack detallado, versiones y por qué se eligió. |
| [docs/02-arquitectura.md](docs/02-arquitectura.md) | Arquitectura modular, motores reutilizables y modelo de datos. |
| [docs/03-buenas-practicas.md](docs/03-buenas-practicas.md) | Convenciones de código, Git, seguridad y flujo de trabajo. |
| [docs/04-ui-diseno.md](docs/04-ui-diseno.md) | Estilo visual (modo claro, inspirado en Docker), paleta/tokens y componentes. |
| [docs/05-despliegue.md](docs/05-despliegue.md) | Estrategia de despliegue **sin SSH** (GitHub → cPanel Git, `vendor`/build incluidos). |
| [docs/06-activos.md](docs/06-activos.md) | Control de **activos/EPP**, hoja de ruta, descuentos y expediente del empleado. |
| [docs/07-ficha-empleado.md](docs/07-ficha-empleado.md) | **Pendiente**: ficha completa del empleado (sueldo, CCI, etc.) + derechohabientes. |

---

## 🚀 Arranque rápido (desarrollo local)

> Requiere [Laragon](https://laragon.org/download/) (PHP 8.2, Composer, MySQL, Node.js).

```bash
# 1. Clonar el repositorio
git clone https://github.com/PercyTechX/recursoshumanos.git
cd recursoshumanos

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Crear la base de datos (en Laragon) y configurar .env (DB_*)

# 5. Migrar y sembrar datos de prueba
php artisan migrate --seed

# 6. Compilar assets y levantar el servidor
npm run dev        # en otra terminal
php artisan serve
```

App disponible en `http://localhost:8000`.

---

## 🔭 Roadmap (futuro, ya contemplado en el diseño)

- ⏱️ Tareo / Asistencia · 🔧 Control de activos
- 🆔 Autocompletado de empleados por DNI (RENIEC)
- 🧾 Exportación de datos para PDT PLAME (T-Registro/PLAME) + integración contable
- 🤖 IA de bajo costo (ej. extracción automática de fechas de vencimiento)

Ver [docs/00-plan-de-ejecucion.md](docs/00-plan-de-ejecucion.md) § Fase 5.
