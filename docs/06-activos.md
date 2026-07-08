# Control de Activos, EPP y Hoja de Ruta

Módulo de inventario y asignación de activos a empleados, con constancia
firmada (VB), devoluciones y liquidación de descuentos al cese/pérdida.
Es un módulo **urgente** (repriorizado por encima de vacaciones).

Se apoya en la ficha del empleado (**expediente**) como punto central: desde
cada trabajador se ve su documentación **y** lo que tiene asignado.

---

## Conceptos clave

Dos naturalezas de "activo", con flujos distintos:

| | Retornable | Consumible / EPP |
|---|---|---|
| Ejemplos | Taladro, celular, laptop, vehículo | Polo, botas, chaleco, guantes |
| Identidad | Objeto único (serie/código) | Por cantidad y talla |
| Se crea primero | Sí, en el inventario | Catálogo de tipos de EPP |
| Se asigna / devuelve | Sí (entrega ↔ devolución) | Solo entrega (no se devuelve) |
| Costo | Sí | No (no se descuenta) |
| Firma | Entrega + devolución (opcional) | Entrega (acta SST) |
| Entra en descuentos | Sí (si no se devuelve) | No |

---

## Modelo de datos

### Flujo A — Retornables

- **`categorias_activo`**: catálogo (Herramientas, Equipos, Vehículos…). Campos:
  `nombre`, `activo`.
- **`activos`**: el objeto individual. Campos: `categoria_id`, `nombre`,
  `codigo` (serie, único, opcional), `descripcion`, `costo` (decimal),
  `estado` (`disponible` / `asignado` / `mantenimiento` / `de_baja` / `perdido`).
- **`asignaciones`**: ciclo entrega↔devolución. Campos: `activo_id`,
  `empleado_id`, `fecha_entrega`, `firma_entrega_path`, `entregado_por`,
  `fecha_devolucion` (null = sigue asignado), `estado_devolucion`
  (`bueno`/`dañado`/`perdido`), `firma_devolucion_path` (opcional),
  `recibido_por`, `observacion`, `hoja_ruta_id` (si se cerró vía hoja de ruta).

### Flujo B — Consumibles / EPP

- **`tipos_epp`**: catálogo (Polo, Botas, Chaleco…). Campos: `nombre`,
  `controla_talla` (bool), `activo`.
- **`entregas_epp`**: registro de entrega. Campos: `empleado_id`, `tipo_epp_id`,
  `cantidad`, `talla`, `fecha`, `firma_path`, `entregado_por`, `observacion`.
  (Sin devolución, sin descuento; la firma sirve como acta de entrega SST.)

### Hoja de ruta y descuentos (puente a contabilidad)

- **`hojas_ruta`**: liquidación por un motivo. Campos: `empleado_id`,
  `motivo` (`cese`/`perdida`/`otro`), `fecha`, `firma_path` (**obligatoria**),
  `total_descuento`, `pdf_path`, `generado_por`, `estado`.
- **`hoja_ruta_items`**: cada retornable considerado. Campos: `hoja_ruta_id`,
  `activo_id`, `devuelto` (bool), `estado_devolucion`, `monto_descuento`
  (**editable, puede ser 0**), `observacion`.
- **`descuentos`**: lo que consume el **Contador**. Campos: `empleado_id`,
  `hoja_ruta_id`, `activo_id`, `monto`, `motivo`, `estado`
  (`pendiente`/`aplicado`), `created_by`. Es el gancho hacia planilla
  (concepto de descuento — ver [futuro Perú](../memory)).

### Rol nuevo

- **`Contador`**: ve la lista de descuentos autorizados (empleado, activo, monto,
  fecha, firma). Notificación **dentro del sistema** (sin correo por ahora).

---

## Los tres flujos

1. **Entrega**: crear activo → `disponible`; asignar a empleado → firma con el dedo
   → constancia PDF (local ahora, OneDrive después) → activo `asignado`.
2. **Devolución suelta** (cualquier momento): registrar estado + quién recibió
   (firma opcional) → activo vuelve a `disponible` / `mantenimiento` / `perdido`.
   La asignación queda como historial (trazabilidad).
3. **Hoja de ruta** (cese / pérdida / otro): lista los activos que el empleado
   tiene sin devolver → marcar devuelto / no devuelto, ajustar `monto_descuento`
   (0 si no se cobra) → el trabajador **firma** → PDF resumen + se crean los
   **descuentos** para el Contador.

### Regla de firmas
- **Entrega** (retornable y EPP): firma obligatoria.
- **Devolución suelta**: firma opcional (basta registrar quién recibió y estado).
- **Hoja de ruta**: firma obligatoria (autoriza el descuento).

---

## Ficha del empleado (expediente)

Página **`/empleados/{id}`** con pestañas:

- **Datos** — ficha personal/laboral.
- **Documentos** — con su semáforo y trazabilidad (historial).
- **Activos** — retornables asignados + historial de asignaciones.
- **EPP** — entregas (cantidad, talla, fecha).
- **Hoja de ruta / Descuentos** — liquidaciones y montos.

Esto reorganiza la navegación hacia la **persona** (no por módulo).

---

## Firma en pantalla (con el dedo)

Se usa la librería JS `signature_pad` sobre un `<canvas>`. El trabajador firma con
el dedo (pensado para **tablet**); la firma se convierte a imagen (PNG) y se
guarda con el motor de archivos. Es una **constancia interna** (evidencia
probatoria), **no** una firma digital certificada (Reniec/firma electrónica
avanzada) — suficiente para autorizaciones internas empresa–trabajador.

---

## Almacenamiento de PDF/firmas
- Ahora: **disco local** (motor de archivos).
- Futuro: **OneDrive** (requiere registro de la app en Azure/Entra ID). El cambio
  será transparente para el resto del sistema. Ver [despliegue](05-despliegue.md)
  y el diseño de OneDrive.

---

## Orden de construcción

1. Rol **Contador** + catálogos (`categorias_activo`, `tipos_epp`).
2. Inventario de **activos** (crear/listar, con costo y estado).
3. **Asignaciones**: entrega (con firma) + devolución + entregas de EPP (con firma).
4. **Expediente del empleado** (unifica Datos / Documentos / Activos / EPP).
5. **Hoja de ruta** + descuentos + vista del Contador + PDFs.
