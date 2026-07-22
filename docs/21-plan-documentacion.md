# 21 — Plan de documentación del Sistema RRHH

> Estado: **PLANIFICADO**. Objetivo: llevar la documentación al nivel que usan
> los equipos serios (Diátaxis + docs-as-code + runbooks + C4), **partiendo de lo
> que ya existe** y cubriendo los huecos hacia usuario final, operación y entrega.

## 1. Marco que seguimos (prácticas de industria)

- **Diátaxis** — 4 tipos de doc, nunca mezclados: *Tutorial*, *How-to (guía)*,
  *Reference*, *Explanation*.
- **Docs-as-code** — todo en Markdown, versionado en el repo, actualizado en el
  mismo commit del cambio. *(Ya lo hacemos.)*
- **Runbooks** (SRE) — "cuando pase X, haz Y" para el área de Sistemas.
- **C4 model** — diagramas de arquitectura por niveles (Contexto/Contenedores),
  en Mermaid (texto versionable).
- **Style guide** — voz activa, imperativo ("Haz clic…"), frases cortas, términos
  consistentes.

## 2. Inventario actual (clasificado por Diátaxis)

La carpeta `docs/` (00–20) es **fuerte en *Explanation*** (decisiones, planes por
módulo) y en parte de *Reference* técnica. Lo que **falta** es lo cara al usuario,
la operación y la entrega formal.

| Categoría | ¿Cubierto hoy? | Docs |
|---|---|---|
| Explanation (por qué) | ✅ Bien | 00-05, 15, 16-19 (planes/decisiones) |
| Reference técnica | 🟡 Parcial | 02, 07, 11, 14 |
| How-to / Tutorial (usuario) | ❌ Falta | — |
| Runbooks (operación) | 🟡 Solo deploy | 09, 20 |
| Reference de negocio (roles/permisos, glosario) | ❌ Falta | — |
| Entregables de proyecto | ❌ Falta | — |
| Índice / README de entrada | ❌ Falta | — |

## 3. Alcance real a documentar (lo que construimos)

**Roles (6):** SuperAdmin, RRHH, Supervisor, Gerencia, Empleado, Contador.
**Módulos (13 permisos):** empleados, documentos, boletas, documentos_compartidos,
activos, vacaciones, ausencias, descuentos, clientes, tickets, asistencia,
rendiciones, usuarios — más: Catálogos, Certificado de trabajo, Portal "Mi espacio",
Backups, integración SharePoint/Graph.

## 4. Estructura propuesta (índice por audiencia)

Se conservan los docs históricos 00–20 como **archivo de decisiones**. Encima se
agrega una capa navegable, agrupada por a quién sirve:

```
docs/
├── README.md                      ← ÍNDICE: "¿eres usuario / técnico / gerencia?"
├── usuario/                       ← How-to con capturas, por rol
│   ├── guia-rrhh.md
│   ├── guia-supervisor.md
│   ├── guia-trabajador.md         (Mi espacio)
│   └── guia-gerencia-contador.md
├── operacion/                     ← Runbooks para Sistemas de GDS
│   ├── despliegue.md              (consolida 09 + 20)
│   ├── restaurar-backup.md
│   ├── crons-y-tareas.md
│   ├── rotar-secret-graph.md
│   └── troubleshooting.md
├── referencia/
│   ├── roles-y-permisos.md        (qué puede cada rol, por módulo)
│   ├── glosario-modulos.md
│   └── arquitectura-c4.md         (diagramas Mermaid; amplía 02)
├── entregables/                   ← Formal, para gerencia/cliente
│   ├── acta-de-entrega.md
│   ├── manual-administrador.md    (SuperAdmin: roles, catálogos, backups)
│   └── soporte-y-garantia.md
└── (00–20 se quedan como histórico de decisiones)
```

## 5. Entregables concretos y prioridad

| # | Documento | Audiencia | Tipo | Prioridad | Esfuerzo |
|---|---|---|---|---|---|
| 1 | `README.md` índice por audiencia | Todos | Ref | 🔴 Alta | Bajo |
| 2 | `referencia/roles-y-permisos.md` | RRHH/TI | Ref | 🔴 Alta | Bajo |
| 3 | `usuario/guia-trabajador.md` (Mi espacio) | Trabajador | How-to | 🔴 Alta | Medio |
| 4 | `usuario/guia-rrhh.md` | RRHH | How-to | 🔴 Alta | Alto |
| 5 | `usuario/guia-supervisor.md` | Supervisor | How-to | 🟠 Media | Medio |
| 6 | `operacion/restaurar-backup.md` | Sistemas GDS | Runbook | 🟠 Media | Bajo |
| 7 | `operacion/troubleshooting.md` | Sistemas GDS | Runbook | 🟠 Media | Medio |
| 8 | `operacion/crons-y-tareas.md` | Sistemas GDS | Runbook | 🟠 Media | Bajo |
| 9 | `referencia/arquitectura-c4.md` (Mermaid) | Técnico | Ref/Expl | 🟢 Baja | Medio |
| 10 | `entregables/manual-administrador.md` | SuperAdmin | How-to | 🟢 Baja | Medio |
| 11 | `entregables/acta-de-entrega.md` | Gerencia | Entregable | 🟢 Baja | Bajo |
| 12 | `usuario/guia-gerencia-contador.md` | Gerencia | How-to | 🟢 Baja | Bajo |

## 6. Roadmap por fases

**Fase A — Esqueleto y referencia (rápido, alto impacto)**
README índice (1) + roles-y-permisos (2) + consolidar operación/despliegue desde 09+20.

**Fase B — Guías de usuario (lo que pide el cliente)**
Trabajador (3) → RRHH (4) → Supervisor (5). Con capturas de la app real.

**Fase C — Operación / runbooks (para Sistemas GDS)**
restaurar-backup (6) + troubleshooting (7) + crons (8) + rotar-secret (—).

**Fase D — Arquitectura y entrega**
C4 (9) + manual-administrador (10) + acta-de-entrega (11).

## 7. Formato y herramienta

- **Base:** Markdown en el repo (docs-as-code). Cero costo, versionado.
- **Capturas:** en `docs/usuario/img/` (referenciadas relativas).
- **Diagramas:** Mermaid (texto).
- **Opcional a futuro:** publicar `docs/` como sitio navegable con **MkDocs**
  (Material) — mismo Markdown, se vuelve web bonita. Solo si GDS lo pide.
- **Entregables formales a gerencia:** exportar el Markdown a **PDF** al cerrar.

## 8. Reglas de mantenimiento

1. **Una fuente de verdad:** todo en `docs/`, nada suelto en correos/Drive.
2. **Fechado y con dueño:** cada doc lleva "Actualizado: fecha".
3. **Un tipo por página** (no mezclar how-to con por-qué).
4. **Se actualiza con el código:** cambio de comportamiento → doc en el mismo commit.
5. **Ni de más ni de menos:** documentar lo justo para usar y mantener sin el autor;
   documentar de más = deuda que se desactualiza.

## 9. Fuera de alcance (por ahora)
- Confluence/Notion/Backstage (sobre-ingeniería para esta escala).
- Documentar función por función del código (los tests + `docs/` de decisiones bastan).
- Versionado de docs por release (no hay releases formales aún).
