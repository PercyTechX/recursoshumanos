# Módulo de Asistencia + Tickets (con geocerca)

> **Estado:** ✅ COMPLETO (2026-07-11, rama `feature/asistencia`). Los 5 pasos
> hechos y probados.

## Avance (rama `feature/asistencia`)

| Paso | Contenido | Estado |
|---|---|---|
| **1. Catálogos** | Clientes + Sucursales (geocerca lat/long/radio) + geocerca en Sedes + **ubigeos** (listas dependientes depto→prov→distrito, 1893 distritos) | ✅ |
| **2. Tickets** | Supervisor crea tickets (IDTICKET + ticket_atención único, cliente + ubicación sede/sucursal, abrir/cerrar) | ✅ |
| **3. Marcación** | Técnico marca ingreso/salida con **GPS** desde "Mi espacio" (varias jornadas/día, metadato del equipo) | ✅ |
| **4. Operación** | Técnico opera el ticket: **Iniciado→En ejecución→Terminado** en orden, con **geocerca** (haversine) y **abortar misión** (cuenta regresiva 10s); 1 ticket activo; varios técnicos refuerzan | ✅ |
| **5. Registro manual** | Menú **Control asistencia** (supervisor): marcación manual + **corregir hora** + **liberar técnico** + **avance manual** de ticket (todo con es_manual + registrado_por + motivo) | ✅ |

Tablas: `clientes`, `sucursales`, `ubigeos`, `tickets`, `marcaciones`,
`ticket_tecnico`, `ticket_avances` (bitácora con GPS). Trait `TieneGeocerca`.

---

## Diseño y reglas (confirmadas)

Conecta tres cosas: **catálogos** (clientes/sucursales/sedes con geocerca) →
**asistencia** (ingreso/salida con GPS) → **producción** (tickets operados por
técnicos, con reglas de geocerca por estado).

## Reglas de negocio (confirmadas)

**Catálogos (los crea el supervisor):**
- **Cliente**: razón social, nombre comercial, RUC.
- **Sucursal del cliente**: nombre, dirección, lat/long, **radio (editable)**,
  departamento, provincia, distrito, centro de costo.
- **Nuestras sedes** (oficina/almacén/otras): + lat/long + radio.

**Ticket (orden de trabajo):**
- **ID interno** correlativo del sistema (IDTICKET) + **Ticket de atención**
  (se ingresa **manualmente**, obligatorio).
- **Siempre tiene cliente** (incluso si el trabajo es en nuestra sede).
- **Ubicación**: una **sede nuestra** o una **sucursal del cliente**.
- **Estado**: abierto / cerrado (ampliable). **Lo cierra solo el supervisor**;
  al cerrarse, **los técnicos ya no lo ven**.
- Lo crea el supervisor.

**Asistencia (técnico, desde el celular):**
- Marca **ingreso** y **salida** en **cualquier lugar**, con **GPS + metadato del
  equipo** (navegador, sistema, IP; modelo exacto opcional).
- **Varias jornadas por día** (si lo llaman por emergencia, marca de nuevo).
- **Jornada nocturna:** al llegar a 23:59:59 el día "cierra" contablemente y
  **sigue contando** en el día siguiente hasta que marque salida (se guardan las
  marcaciones con su fecha/hora real; el corte por día es solo para el reporte).
- Para **operar tickets** el técnico **debe tener ingreso marcado** (jornada abierta).

**Operación de un ticket (por técnico, en ORDEN obligatorio):**
1. **INICIADO** — en cualquier lugar, pero **graba el GPS** de dónde lo tomó.
2. **EN EJECUCIÓN** — **solo dentro** de la geocerca de la ubicación del ticket.
3. **TERMINADO** — **solo dentro** de la geocerca.
- Un ticket **no se bloquea**: **varios técnicos** pueden operar el mismo ticket
  (reforzar); cada técnico lleva **su propio avance**.
- **Fuera de la geocerca** en "En ejecución/Terminado" → **no se permite**. Solo el
  **supervisor** puede corregirlo (registrando la hora/salida correcta).
- **Abortar misión** (si desvían al técnico del ticket): botón con **doble
  validación** ("¿estás seguro?") + **cuenta regresiva** para deshacer el abortaje.
  Al abortar, el técnico queda **libre** para tomar otro ticket.
- El **supervisor** también puede **liberar** a un técnico de un ticket.

**Modo online + registro manual:**
- Solo **online**. Si no hay señal / avería / robo del celular → el **supervisor
  registra manualmente** (asistencia y/o estados de ticket), quedando marcado como
  **"registrado por supervisor" + motivo**.

## POR CONFIRMAR (4)

1. **¿Un técnico puede tener solo UN ticket activo a la vez?** (debe terminar,
   abortar o ser liberado antes de tomar otro). — *asumo que sí.*
2. **Cuenta regresiva del abortaje:** ¿cuántos segundos? — *asumo 10 s, configurable.*
3. **¿"Terminado" también libera** al técnico para tomar otro ticket? — *asumo que sí.*
4. **Ticket de atención:** ¿es **único** en el sistema? — *asumo que sí.*

## Modelo de datos (borrador)

- **`clientes`**: razon_social, nombre_comercial, ruc, activo.
- **`sucursales`**: cliente_id, nombre, direccion, latitud, longitud, radio_metros,
  departamento, provincia, distrito, centro_costo, activo.
- **`sedes`** (existente) → +tipo (oficina/almacen/otro), latitud, longitud, radio_metros.
- **`tickets`**: id (IDTICKET), ticket_atencion (único), cliente_id,
  sede_id **o** sucursal_id (la ubicación), estado (abierto/cerrado), descripcion,
  creado_por, fecha_apertura, cerrado_por, fecha_cierre.
- **`ticket_tecnico`** (avance por técnico): ticket_id, empleado_id,
  estado_trabajo (iniciado/en_ejecucion/terminado/abortado), fecha_inicio,
  fecha_ejecucion, fecha_termino, fecha_abort, liberado_por, motivo.
- **`avances_ticket`** (bitácora de cada transición): ticket_tecnico_id, estado,
  fecha_hora, latitud, longitud, dentro_geocerca, registrado_por, es_manual, motivo.
- **`marcaciones`** (asistencia): empleado_id, tipo (ingreso/salida), fecha_hora,
  latitud, longitud, precision, user_agent, ip, modelo_equipo (opc.), registrado_por,
  es_manual, motivo.

## Dónde vive en la app

- **Técnico** → dentro de **"Mi espacio"**: marcar ingreso/salida + ver tickets
  abiertos + operar su ticket (estados con GPS).
- **Supervisor** → módulo **Tickets** (crear/cerrar, liberar técnicos, registro
  manual) + **Clientes/Sucursales** (catálogos) + **Asistencia** (registro manual,
  correcciones).
- Requiere **HTTPS** (ya disponible) para el GPS del navegador.
