# 18 · Asistencia — Refrigerios (D/A/C) y Visto Bueno del supervisor

> **Estado:** PLANIFICACIÓN (2026-07-17). Agrega a Control de asistencia una vista
> "Resumen diario" (por empleado × día) con descuento de refrigerios y VB del
> supervisor, y ajusta las horas de los reportes a **horas netas**.

## 1. Concepto (pedido por RRHH)

Por cada **día trabajado** de un empleado:
- **D / A / C** (Desayuno / Almuerzo / Cena): cada uno marcado **resta 1 hora** a las
  horas del día (es su refrigerio, no cuenta como trabajo).
- **VB**: el **supervisor** valida la asistencia del día (sello de conformidad).

Son **por día (empleado + fecha)**, no por marcación (un día tiene ingreso + salida, o más
si es multiturno). El descuento debe reflejarse en las **horas trabajadas** de los reportes.

## 2. Decisiones tomadas (2026-07-17)

1. **Ubicación:** nueva **3ª pestaña "Resumen diario"** en Control de asistencia
   (junto a Marcaciones / Operación de tickets).
2. **Refrigerios:** se marcan **manualmente**.
3. **VB:** **solo el Supervisor** (permiso propio `asistencia.vb`); D/A/C los marca
   RRHH o Supervisor (permiso `asistencia.registrar`/`editar`). El VB **no** afecta horas.

## 3. Modelo de datos (tabla nueva `asistencia_dias`)

Una fila por empleado × día (solo se crea cuando se marca algo):
- `empleado_id` FK · `fecha` date · **unique(empleado_id, fecha)**
- `desayuno` bool · `almuerzo` bool · `cena` bool (default false)
- `vb_supervisor` bool default false · `vb_por` FK users nullable · `vb_at` timestamp nullable
- `marcado_por` FK users nullable · timestamps

> No se toca `marcaciones`. Este registro es un "overlay" del día.

## 4. Cálculo de horas

- **Brutas (día)** = suma de jornadas del día (pares ingreso→salida, ya existe en `jornadas()`).
- **Refrigerios (día)** = (desayuno + almuerzo + cena) · 60 min.
- **Netas (día)** = max(0, brutas − refrigerios).
- En el **reporte por empleado** (rango): netas = Σ brutas − Σ refrigerios del rango.

## 5. Piezas a construir

### Fase A — Datos y cálculo
- Migración `asistencia_dias` + modelo `AsistenciaDia` (casts bool, relaciones).
- Permiso **`asistencia.vb`** en config/permisos (+ seeder: solo **Supervisor** lo recibe por
  defecto; RRHH/Gerencia reciben asistencia SIN vb).
- Helper de refrigerios/netas (en el modelo o un pequeño service).

### Fase B — Vista "Resumen diario" (3ª pestaña)
- Componente hijo `asistencia.resumen` (Livewire) embebido en la pestaña.
- Filtros Desde/Hasta (default mes actual) + empleado; una fila por empleado × día con
  marcaciones en el rango.
- Columnas: Empleado · Día · Ingreso · Salida · **Horas brutas** · **[D][A][C]** · **Horas
  netas** · **[VB]**.
- Al togglear D/A/C → `updateOrCreate` en `asistencia_dias` (permiso registrar/editar).
- Al togglear VB → solo con `asistencia.vb` (Supervisor); guarda vb_por + vb_at.
- Recalcula netas en vivo.

### Fase C — Integrar en los reportes de asistencia
- `asistencia.reporte` (General y Detallado) y su **Excel** usan **horas netas**:
  General → columnas "Refrigerios (h)" y "Horas netas"; Detallado → mostrar el descuento del día.
- El export general por jornada: agregar el refrigerio del día (nota/columna) sin doble conteo
  en multiturno (el descuento es por día, no por jornada).

### Tests
- Cálculo netas (con/sin refrigerios, multiturno), toggle D/A/C, VB solo Supervisor
  (RRHH no puede), autorización, netas en el reporte.

## 6. Permisos / roles
- `asistencia.registrar` o `asistencia.editar` → marcar D/A/C (RRHH/Supervisor).
- `asistencia.vb` (NUEVO) → dar/quitar VB (solo Supervisor por defecto; configurable en
  Roles y accesos).

## 7. Preguntas menores (al construir)
- ¿El VB lo puede dar **cualquier** supervisor o solo el **supervisor del empleado**?
  → propuesta: quien tenga `asistencia.vb` (Supervisor). Si se quiere restringir al supervisor
  directo, se agrega la validación (como en licencias).
- ¿Máximo de refrigerios por día según duración? → MVP: libre (los 3 disponibles siempre).

---

*Plan listo para construir por fases (A→C), estilo Rendiciones/Licencias.*
