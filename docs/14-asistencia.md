# Módulo de Asistencia (marcación con geocerca)

> **Estado:** 🕓 EN DEFINICIÓN — el usuario aportará el contexto particular de cómo
> funciona la asistencia en su empresa. Este documento se completará con esas
> reglas antes de construir. Abajo queda lo ya conversado como punto de partida.

## Idea general (lo conversado)

El trabajador **marca su asistencia desde el navegador de su celular** (dentro de
"Mi espacio"). El sistema toma su **ubicación** (API de Geolocalización del
navegador, requiere **HTTPS** — ya disponible en `rrhh.gds.pe`) y valida que esté
dentro de la **geocerca** de su sede/obra.

- **Geocerca circular** (lo simple y robusto): centro (lat/long) + radio; se valida
  con la fórmula de **haversine** (distancia ≤ radio). Polígonos exactos = posible
  pero más adelante.
- **Registra** entrada/salida con fecha/hora, coordenadas y si estuvo dentro/fuera
  de la zona. Base para el futuro **tareo** (horas, tardanzas).

## Es viable con nuestro stack

Laravel + Livewire + el celular del trabajador. **No hace falta app nativa.**

## Límites a tener claros (honestos)

- **Precisión del GPS** del celular: 5–50 m → el radio se pone generoso o por sede.
- La ubicación del navegador **se puede falsear** (apps de "fake GPS"). Para control
  estricto anti-trampa haría falta app nativa o extras (selfie, atar dispositivo).
- Requiere que el trabajador tenga **usuario** (ya lo habilita el portal) y
  smartphone con datos. Sin señal en obra = no marca (v1 solo-online).

## Preguntas de contexto a resolver con el usuario (pendiente)

- ¿Cómo definen las **zonas/obras** y sus coordenadas? ¿Una o varias por trabajador?
- ¿Marcan **entrada/salida** simples, o también refrigerio / múltiples turnos?
- ¿Qué pasa si marca **fuera de la geocerca**: se bloquea, se registra como
  "fuera de zona", requiere aprobación?
- ¿**Horarios/turnos** definidos? ¿Cómo se calculan tardanzas / horas?
- ¿Se necesita **foto/selfie** al marcar?
- ¿Trabajo en **campo sin señal**? ¿marcación offline?
- ¿Quién ve los **reportes** y en qué formato (Excel)?

## Modelo de datos (borrador, se ajusta con el contexto)

- `sedes` (o `zonas`): + `latitud`, `longitud`, `radio_metros`.
- `marcaciones`: `empleado_id`, `tipo` (entrada/salida/…), `fecha_hora`, `latitud`,
  `longitud`, `precision`, `dentro_geocerca` (bool), `zona_id`, `dispositivo`.
