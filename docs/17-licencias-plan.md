# 17 · Licencias, permisos y descansos — Plan de diseño

> **Estado:** PLANIFICACIÓN (2026-07-17). Extiende el módulo **Ausencias** existente
> (NO se crea módulo paralelo). Solicitud del trabajador + doble aprobación
> (Supervisor → RRHH) + sustento a SharePoint. Solo registrar/documentar (sin planilla).

## 1. Qué queremos

El trabajador **solicita** desde su portal una licencia/permiso (elige tipo, fechas →
días automáticos, motivo, **adjunta el sustento**). La solicitud pasa por **doble
aprobación**: el **supervisor visa** y luego **RRHH aprueba** (o cualquiera rechaza con
comentario). RRHH puede además **registrar directo** (faltas, etc.). Reusa Ausencias,
SharePoint, portal y el cálculo de días.

## 2. Decisiones tomadas (2026-07-17)

1. **Enfoque:** extender el módulo **Ausencias** (reusar tabla/modelo `ausencias`).
2. **Aprobación:** doble paso → `pendiente_supervisor` → `pendiente_rrhh` → `aprobada`.
3. **Sustento:** obligatorio **según el tipo** (ver §4).
4. **Alcance:** MVP = registrar + documentar. Sin cálculo de planilla ni descuentos.

## 3. Máquina de estados (nueva en ausencias)

```
Trabajador solicita (portal)
        ▼
  ● PENDIENTE_SUPERVISOR ── supervisor rechaza ─► ● RECHAZADA (comentario)
        │ supervisor visa
        ▼
  ● PENDIENTE_RRHH ─────────  RRHH rechaza ─────► ● RECHAZADA (comentario)
        │ RRHH aprueba
        ▼
  ● APROBADA
```
- El trabajador puede **CANCELAR** mientras esté pendiente → ● CANCELADA.
- **RRHH registra directo** (módulo Ausencias) → nace **APROBADA** (sin pasar el flujo).
- Estados: `pendiente_supervisor` | `pendiente_rrhh` | `aprobada` | `rechazada` | `cancelada`.

### Autorización
- **Visar** (paso supervisor): usuario con permiso `ausencias.visar` **y** que sea el
  **supervisor del solicitante** (`empleado.supervisor_id` == empleado del usuario) —
  o RRHH/SuperAdmin (pueden visar en cualquier caso). Actúa sobre `pendiente_supervisor`.
- **Aprobar/Rechazar final**: permiso `ausencias.aprobar` (RRHH). Actúa sobre `pendiente_rrhh`.
  El rechazo también es posible en el paso supervisor.
- **Solicitar/Cancelar**: el propio trabajador (su empleado), desde el portal.

## 4. Tipos (config en el modelo)

`tipo => [label, con_goce_default, requiere_sustento, solicitable_por_trabajador]`

| tipo | Label | Goce | Sustento | Trabajador solicita |
|---|---|---|---|---|
| `cita_medica` | Cita médica | sí | opcional | sí |
| `descanso_medico` | Descanso médico (CITT) | sí | **obligatorio** | sí |
| `enfermedad_familiar` | Enfermedad de familiar | sí | **obligatorio** | sí |
| `fallecimiento_familiar` | Fallecimiento de familiar | sí | **obligatorio** | sí |
| `maternidad` | Maternidad | sí | **obligatorio** | sí |
| `paternidad` | Paternidad | sí | **obligatorio** | sí |
| `licencia_con_goce` | Licencia con goce | sí | opcional | sí |
| `licencia_sin_goce` | Licencia sin goce | no | opcional | sí |
| `permiso` | Permiso | sí | opcional | sí |
| `otros` | Otros | sí | opcional | sí |
| `falta` | Falta | no | — | **no** (solo RRHH) |

> Compatibilidad: se conservan los tipos actuales (`descanso_medico`, `licencia_con_goce`,
> `licencia_sin_goce`, `permiso`, `falta`); se agregan los nuevos. `con_goce` sigue siendo
> editable por RRHH (el default es solo sugerencia).

## 5. Modelo de datos (migración: ALTER a `ausencias`)

Agregar:
- `estado` string(24) default `'aprobada'` (las existentes/registradas directas quedan aprobadas).
- `solicitado_por` FK users nullable (si vino del portal).
- `visado_por` FK users nullable · `fecha_visto` date nullable · `comentario_visto` string nullable.
- `decidida_por` FK users nullable · `fecha_decision` date nullable · `comentario_decision` string nullable.
- SharePoint del sustento: `archivo_item_id`, `archivo_web_url`, `archivo_status` (ya existen
  `archivo_path`, `archivo_nombre`).

## 6. Piezas técnicas

- **Sustento → SharePoint** reusando `RendicionArchivos::subir($ausencia, 'archivo',
  "{DNI - Apellidos}/Licencias", $nombre, 'documentos')` → cae en `RRHH/Doc_Sistemas/{persona}/Licencias`.
- **Servir el sustento:** ruta por **pertenencia** (trabajador ve el suyo) + admin (RRHH/supervisor).
- **Modelo `Ausencia`:** constantes de estado, TIPOS enriquecido, `puede($accion)` /
  `transicionar()`, `requiereSustento()`, helpers de autorización.
- **Días** ya se calculan con `Ausencia::calcularDias()`.

## 7. Plan por fases

- **Fase A — Datos y estados:** migración (§5), modelo (estados/tipos/transiciones/authz),
  permisos `ausencias.visar` + `ausencias.aprobar` en config/permisos + seeder. Tests de estados.
- **Fase B — Admin (módulo Ausencias):** columna Estado + filtro "Pendientes"; acciones
  **Visar / Aprobar / Rechazar** según rol y estado; ver sustento; seguir registrando directo.
- **Fase C — Portal del trabajador:** pestaña "Licencias y permisos": **Solicitar** (tipo,
  fechas, motivo, sustento con obligatoriedad por tipo), listar con estado, **Cancelar** pendiente.
- **Fase D — SharePoint del sustento:** subida (Doc_Sistemas/{persona}/Licencias) con
  guardar-temporal-y-reintentar + ruta de descarga por pertenencia + `rendiciones:subir-pendientes`
  (o comando propio) para reintento.
- **Tests** de cada fase (solicitar, visar, aprobar/rechazar, cancelar, obligatoriedad de
  sustento, autorización, servir archivo por pertenencia).

## 8. Preguntas menores (resolver al construir)
- ¿El supervisor visa solo a **sus** subordinados (`supervisor_id`) o cualquiera con permiso?
  → propuesta: sus subordinados; RRHH/SuperAdmin sin restricción.
- ¿Notificar por WhatsApp/correo al solicitar/aprobar? → fuera del MVP (requiere SMTP); se
  puede reusar el patrón de aviso WhatsApp existente más adelante.

---

*Plan listo para construir por fases (A→D), estilo Rendiciones.*
