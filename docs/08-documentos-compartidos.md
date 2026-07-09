# Documentos compartidos (SCTR colectivo, pólizas)

> **Estado:** ✅ IMPLEMENTADO (2026-07-09, rama `feature/documentos-compartidos`).

## Problema que resuelve

Un mismo documento (una **constancia de SCTR**, una **póliza**, una **homologación
de sede**) ampara a **muchos trabajadores a la vez**. Con el modelo "1 documento =
1 persona" habría que **subir el mismo archivo N veces** y **editar N vencimientos**
al renovar (el SCTR suele ser **mensual**). Eso es inviable.

## Modelo

Un **documento compartido** guarda el archivo y la vigencia **una sola vez** y se
vincula a **varias personas por selección**:

- `documentos_compartidos`: fecha_emisión, fecha_vencimiento, archivo, observación.
- `documento_compartido_cobertura`: las coberturas que ampara (ej. **SCTR Salud** +
  **SCTR Pensión** en la misma constancia), cada una con **aseguradora** y
  **N° de póliza** opcionales.
- `documento_compartido_empleado`: el **grupo** de personas amparadas.

Un `tipo_documento` marca `compartible = true` (SCTR Salud, SCTR Pensión,
Certificado de Homologación) para aparecer como cobertura seleccionable.

## Cómo se usa (menú "Doc. compartidos")

1. **+ Nuevo** → marca las coberturas (una constancia puede cubrir Salud y Pensión
   juntas), pon aseguradora/N° de póliza (opcional), vigencia, **sube el archivo una
   vez**.
2. Marca a las **personas amparadas** (buscador por nombre/DNI + checkboxes).
3. **Guardar.** El archivo se guarda una sola vez.

- **Renovar:** clona coberturas y grupo del periodo anterior; solo pones la nueva
  vigencia y el nuevo archivo → todos quedan al día de una vez.
- **Varios SCTR = varios registros**, cada uno con su propio grupo.

## Dónde se ve

- **Expediente del empleado → pestaña Documentos:** las pólizas que lo amparan
  aparecen marcadas como **"Compartido"**, junto a sus documentos individuales.
- **Tablero (semáforo):** cuenta cada requisito como **persona × cobertura** (un SCTR
  de 26 personas con 2 coberturas = 52 requisitos) y suma a los individuales. Si la
  póliza vence, **todos** pasan a rojo a la vez.

## Pendiente / futuro

- Exportar a Excel el listado de amparados (como la constancia).
- Cuando exista el motor de archivos polimórfico + OneDrive, migrar el archivo allí.
- Alerta al supervisor cuando una póliza colectiva esté por vencer.
