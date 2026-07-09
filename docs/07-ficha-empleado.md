# Tarea pendiente: Ficha completa del empleado + Derechohabientes

> **Estado:** PENDIENTE. Programada **después de cerrar el módulo de activos**
> (paso 5: hoja de ruta) y fusionarlo a `main`.
> **Excepción:** si se van a cargar empleados reales de inmediato, se adelanta.

Objetivo: que la ficha del empleado capture **todos los datos que exige la ley**
(para T-Registro / planilla) y permita registrar **derechohabientes** con sus
documentos.

---

## Estado actual (qué falta)

1. **Campos que ya existen en BD pero NO en el formulario** (hay que exponerlos):
   dirección, tipo de contrato, régimen pensionario (ONP/AFP), CUSPP,
   régimen de salud, banco, número de cuenta.
2. **Campos que no existen todavía (crear en BD + formulario):**
   - **Sueldo / remuneración** (sensible — ver acceso por rol).
   - **CCI** (Código de Cuenta Interbancario, 20 dígitos — distinto del n° de cuenta).
   - **Contacto de emergencia** (nombre, parentesco, teléfono).
   - **Fecha de cese** (ya existe el campo; falta en el formulario).
3. **Derechohabientes**: **no existe** — es un **sub-módulo**, no un campo.

---

## Campos a capturar (agrupados)

**Personales**
- Tipo y número de documento · nombres · apellidos · fecha de nacimiento
- Nacionalidad · sexo · estado civil · foto

**Contacto**
- Teléfono · correo · dirección
- **Contacto de emergencia:** nombre · parentesco · teléfono

**Laboral**
- Área · cargo · sede · supervisor
- Fecha de ingreso · tipo de contrato · fecha de cese · situación
- Tipo de trabajador · régimen laboral

**Planilla / SUNAT (T-Registro)**
- **Sueldo / remuneración básica**
- Sistema pensionario (ONP / AFP) · CUSPP
- Régimen de salud (EsSalud / EPS)

**Bancario**
- Banco · número de cuenta · **CCI**

---

## Derechohabientes (sub-módulo)

Tabla `derechohabientes` (familiares con derecho a EsSalud):

- `empleado_id`, `tipo` (cónyuge/conviviente/hijo/otro), `nombres`, `apellidos`,
  `tipo_documento`, `numero_documento`, `fecha_nacimiento`, `parentesco`,
  `activo`.
- **Documentos de cada derechohabiente**: reutilizar el **motor de archivos**
  (partida de nacimiento, DNI, etc.) → se guardan en el mismo esquema de
  documentos/archivos (local ahora, OneDrive después).

Ubicación en la UI: una **pestaña "Derechohabientes"** en el expediente del
empleado, con su lista y sus documentos.

---

## Consideraciones de diseño (decidir al construir)

- **Sueldo es sensible** → control de acceso por rol: lo ven **RRHH, Gerencia,
  Contador**; **no** los Supervisores. Evaluar ocultar el campo en el formulario
  y en la pestaña Datos según el rol.
- **CCI ≠ número de cuenta** → dos campos separados; validar 20 dígitos.
- **Contacto de emergencia** → definir si es uno o varios (por ahora, uno).
- **Derechohabientes** → reutilizar el motor de archivos/documentos ya existente;
  no duplicar lógica.
- El **sueldo** y los descuentos (hoja de ruta) son la base del futuro módulo de
  **planilla / conceptos** — mantener consistencia con [06-activos.md](06-activos.md)
  y el diseño de PLAME.

---

## Campos adicionales solicitados (registrado 2026-07-09)

Pedidos por el usuario para incorporar a la ficha / derechohabientes:

- **Modalidad de pago (etiqueta): Planilla vs Recibos por Honorarios.**
  Distingue trabajador en planilla (5ta categoría) de prestador de servicios por
  recibos por honorarios (4ta categoría). Afecta tributación/planilla.
- **Estado de seguro** (indicador): si tiene seguro / **falta de seguro** (EsSalud
  vigente o no).
- **Tipo de AFP** (nombre): Integra, Prima, Profuturo, Habitat — complementa el
  campo `sistema_pensionario` (ONP/AFP) y `cuspp`.
- **Cantidad de hijos** (puede derivarse de los derechohabientes tipo "hijo").
- **Datos de cónyuge e hijos**: nombres, **DNI**, fecha de nacimiento (esto es el
  sub-módulo de **derechohabientes**).
- **Documentos de los derechohabientes**: partida de nacimiento, DNI, etc.
  (reusar el motor de archivos).

---

## Alcance de la tarea (cuando se ejecute)
1. Ampliar migración `empleados` (sueldo, cci, contacto de emergencia).
2. Ampliar el formulario de alta/edición (todos los campos, agrupados en secciones).
3. Control de acceso al sueldo por rol.
4. Sub-módulo `derechohabientes` (CRUD + documentos) + pestaña en el expediente.
5. Tests.
