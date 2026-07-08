# Arquitectura

Diseño **modular**: un núcleo estable + módulos que se "enchufan" + tres motores
genéricos reutilizables. El objetivo es que agregar módulos futuros sea *sumar*,
no *rehacer*.

---

## Visión general

```
                    ┌─────────────────────────┐
                    │        NÚCLEO           │  (se construye una vez)
                    │  usuarios · roles       │
                    │  empleados (hub)        │
                    │  areas · cargos · sedes │
                    └───────────┬─────────────┘
                                │  todo apunta al empleado
   ┌──────────────┬─────────────┼──────────────┬───────────────┐
   ▼              ▼             ▼              ▼               ▼
Documentos    Vacaciones     Tareo/        Control de     (futuros)
 (MVP)         (MVP)       Asistencia       Activos
                            (futuro)        (futuro)

        Motores genéricos (transversales, se usan en todos):
        ① Archivos   ② Solicitudes+Aprobaciones   ③ Asignaciones
```

---

## Los 3 motores reutilizables

### ① Motor de Archivos (polimórfico)
Una sola tabla `archivos` que puede colgar de **cualquier entidad** (documento,
activo, foto de empleado…). Guarda el enlace/ID de OneDrive.
> Relación polimórfica: `archivable_type` + `archivable_id`.

### ② Motor de Solicitudes + Aprobaciones
Flujo genérico "alguien pide → un aprobador acepta/rechaza". Hoy lo usan las
**vacaciones**; mañana, préstamo de activos, permisos, etc.

### ③ Motor de Asignaciones
Registro genérico "a **quién** se asigna **qué**, desde/hasta cuándo". Base del
futuro **Control de Activos**.

---

## Modelo de datos

### Capa NÚCLEO

| Tabla | Descripción |
|---|---|
| `users` | Login. Vinculado a un empleado. |
| `roles`, `permissions` | RBAC vía spatie. |
| `empleados` | **Hub central**. Datos personales + laborales + campos "T-Registro ready". |
| `areas` | Departamentos (auto-referencia para sub-áreas: `parent_id`). |
| `cargos` | Puestos (catálogo). |
| `sedes` | Locales/ubicaciones. |

**Campos clave de `empleados` (incluye ganchos futuros):**
`nombres`, `apellidos`, `tipo_documento`, `numero_documento`, `nacionalidad`,
`fecha_nacimiento`, `telefono`, `correo`, `direccion`, `foto` (via archivos),
`cargo_id`, `area_id`, `sede_id`, `supervisor_id`, `fecha_ingreso`,
`tipo_contrato`, `tipo_trabajador`, `regimen_laboral`, `sistema_pensionario`,
`cuspp`, `regimen_salud`, `banco`, `numero_cuenta`, `situacion` (alta/baja),
`fecha_cese`.

### Capa MÓDULOS (MVP)

| Tabla | Módulo |
|---|---|
| `tipos_documento` | Catálogo: nombre, `dias_aviso_previo`, `requiere_vigencia`. |
| `documentos` | `empleado_id`, `tipo_documento_id`, `fecha_emision`, `fecha_vencimiento`, archivo, estado. Guarda **historial** (varios por tipo). |
| `archivos` | Motor ① (polimórfico). |
| `tipos_solicitud` | Catálogo: vacaciones, permiso, licencia. |
| `solicitudes` | Motor ②: `empleado_id`, `tipo_solicitud_id`, fechas, estado. |
| `aprobaciones` | Motor ②: `solicitud_id`, `aprobado_por`, resultado, comentario. |
| `saldos_vacaciones` | Saldo por empleado + **fecha de corte** + saldo inicial. |
| `movimientos_vacaciones` | Libro contable: `apertura` \| `devengado` \| `gozado` \| `ajuste`. |

### Capa FUTURA (ganchos reservados)

| Tabla | Módulo futuro |
|---|---|
| `marcaciones`, `jornadas` | Tareo / Asistencia. |
| `activos`, `categorias_activo`, `asignaciones` | Control de activos (motor ③). |
| `derechohabientes` | Familiares con EsSalud (T-Registro). |
| `conceptos` | Catálogo remunerativo/no remunerativo/aporte/descuento/tributo → planilla. |

---

## Semáforo de vigencia 🚦

Se calcula comparando `fecha_vencimiento` con hoy y los `dias_aviso_previo` del
tipo de documento:

- 🟢 **Vigente** — vence en más de N días.
- 🟡 **Por vencer** — vence dentro de los N días de aviso.
- 🔴 **Vencido** — `fecha_vencimiento` ya pasó.

> Implementado como *accessor* en el modelo `Documento` (no se guarda; se calcula).

---

## Saldo de vacaciones (libro contable)

```
saldos_vacaciones (por empleado)
 └─ fecha_corte + saldo_inicial

movimientos_vacaciones
 ├─ 🟢 APERTURA    → + días acumulados al día de corte (carga inicial)
 ├─ 🟢 DEVENGADO   → + días ganados mes a mes
 ├─ 🔴 GOZADO      → − días tomados (vacaciones aprobadas)
 └─ ⚙️ AJUSTE      → correcciones manuales (con motivo)

Saldo actual = Σ movimientos
```

Ventaja: **auditable**. Siempre se puede reconstruir por qué el saldo es X.

---

## Organización del código (Laravel)

```
app/
├── Models/            # Eloquent (Empleado, Documento, Solicitud, ...)
├── Http/
│   ├── Controllers/   # Delgados: reciben, delegan, responden
│   └── Requests/      # Form Requests (validación)
├── Livewire/          # Componentes interactivos (tablas, formularios, semáforo)
├── Services/          # Lógica de negocio reutilizable (ej. SaldoVacacionesService)
├── Actions/           # Acciones puntuales (ej. RegistrarMovimientoVacaciones)
├── Policies/          # Autorización por modelo
└── Support/           # Integraciones (ej. cliente OneDrive/Graph)
database/
├── migrations/        # Una migración por tabla/cambio
├── seeders/           # Datos base (roles, tipos_documento, tipos_solicitud)
└── factories/         # Datos de prueba
```

**Regla:** los controladores son delgados. La lógica va en **Services/Actions**,
la validación en **Form Requests**, la autorización en **Policies**.
