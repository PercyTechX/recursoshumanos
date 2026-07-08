# UI y Diseño Visual

Estándar visual del proyecto. La interfaz es **fija en modo claro**, con un
estilo **inspirado en Docker** (azul brillante, aire, esquinas redondeadas) sobre
una base profesional. Todo se construye con **componentes reutilizables** y
**colores centralizados** (design tokens): cambiar un color o el estilo de un
botón se hace en un solo lugar y se refleja en toda la app.

> 🎨 Referencia visual: ver el artifact de paleta compartido con el equipo.

---

## Principios de UI

- **Modo claro** fijo (decisión de diseño, no depende del sistema operativo).
- **Componentes reutilizables** (Blade): un botón, una pastilla, una tarjeta se
  definen una vez y se usan en todas partes.
- **Colores centralizados** en `tailwind.config.js` (design tokens).
- **Colores semánticos separados de la marca**: el semáforo (🟢🟡🔴) no cambia
  aunque se cambie el color de la empresa.
- **Información primero**: en tableros, el resumen antes que el detalle; el estado
  se lee de un vistazo (pastillas, franjas de color).

---

## Paleta (tokens)

### Marca (inspirada en Docker)

| Token | Hex | Uso |
|---|---|---|
| `primary` | `#2496ED` | Botones, enlaces, acentos |
| `primary-dark` | `#1B7FD1` | Hover / estado activo |
| `primary-tint` | `#E5F2FD` | Fondos suaves, badges |
| `navy` | `#0D3B66` | Encabezados, profundidad, hero |

### Neutrales (sesgo azul)

| Token | Hex | Uso |
|---|---|---|
| `ink` | `#10233A` | Texto principal |
| `muted` | `#46607C` | Texto de apoyo |
| `faint` | `#8AA0B8` | Placeholders, notas |
| `border` | `#DCE7F1` | Bordes y divisores |
| `canvas` | `#F2F8FD` | Fondo de página |
| `surface` | `#FFFFFF` | Tarjetas y paneles |

### Semánticos (semáforo — NO cambian con la marca)

| Estado | Acento | Fondo tenue |
|---|---|---|
| 🟢 Vigente / Activo | `#167C4A` | `#E4F4EB` |
| 🟡 Por vencer | `#B26A0B` | `#FAF0DA` |
| 🔴 Vencido / Cesado | `#C62828` | `#FBE9E7` |
| ⬇️ Exportar Excel | `#217346` | — |

---

## Configuración en Tailwind (v4)

Laravel 12 usa **Tailwind CSS v4**, que se configura **por CSS** (no con
`tailwind.config.js`). Los colores se definen una sola vez en
`resources/css/app.css` dentro de `@theme`, y quedan disponibles como utilidades
(`bg-primary`, `text-danger`, `border-line`, etc.):

```css
/* resources/css/app.css */
@import 'tailwindcss';

@theme {
    --font-sans: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;

    /* Marca (Docker) */
    --color-primary: #2496ED;
    --color-primary-dark: #1B7FD1;
    --color-primary-tint: #E5F2FD;
    --color-navy: #0D3B66;

    /* Neutrales */
    --color-ink: #10233A;
    --color-muted: #46607C;
    --color-faint: #8AA0B8;
    --color-line: #DCE7F1;
    --color-canvas: #F2F8FD;
    --color-surface: #FFFFFF;

    /* Semánticos (semáforo) */
    --color-success: #167C4A;  --color-success-tint: #E4F4EB;
    --color-warning: #B26A0B;  --color-warning-tint: #FAF0DA;
    --color-danger:  #C62828;  --color-danger-tint:  #FBE9E7;
    --color-excel:   #217346;
}
```

> ¿Gerencia pide otro azul o GDS tiene su color de marca? Se cambia
> `--color-primary` en este archivo y **toda la interfaz se actualiza**.

---

## Componentes base

Se crean como componentes Blade (`resources/views/components/`) y se reutilizan.

**Botón** (`components/boton.blade.php`):
```blade
@props(['variant' => 'primary'])
@php
  $styles = [
    'primary' => 'bg-primary hover:bg-primary-dark text-white',
    'outline' => 'border border-primary text-primary hover:bg-primary-tint',
    'excel'   => 'bg-excel hover:brightness-90 text-white',
    'danger'  => 'bg-danger text-white',
  ][$variant];
@endphp
<button {{ $attributes->merge(['class' => "px-4 py-2 rounded-lg font-semibold $styles"]) }}>
  {{ $slot }}
</button>
```

Uso:
```blade
<x-boton>Guardar</x-boton>
<x-boton variant="excel">⬇ Exportar a Excel</x-boton>
```

**Pastilla de estado / semáforo** (`components/estado.blade.php`):
```blade
@props(['estado'])   {{-- vigente | por-vencer | vencido --}}
@php
  $map = [
    'vigente'    => ['bg-success/10 text-success', 'Vigente'],
    'por-vencer' => ['bg-warning/10 text-warning', 'Por vencer'],
    'vencido'    => ['bg-danger/10 text-danger',   'Vencido'],
  ][$estado];
@endphp
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $map[0] }}">
  <span class="w-2 h-2 rounded-full bg-current"></span>{{ $map[1] }}
</span>
```

---

## Patrones de pantalla

### Barra de acciones de una tabla
Cada módulo con datos muestra: **buscador · + Nuevo · ⬇ Exportar a Excel**.
El export descarga lo mostrado **con los filtros aplicados** (`maatwebsite/excel`).

### Tablero (dashboard)
Tarjetas-resumen arriba (vigentes / por vencer / vencidos) con franja de color;
detalle debajo. El estado se codifica en forma **y** color.

### Datos de prueba
Mientras se valida el sistema, un aviso indica "datos de prueba" con acción
**"Vaciar datos de prueba"** (ver [plan](00-plan-de-ejecucion.md) § Requisitos
transversales).

---

## Accesibilidad y calidad

- Contraste suficiente de texto sobre fondos (revisar acentos sobre tenues).
- Estado visible de foco en botones/enlaces (navegación por teclado).
- No depender solo del color: acompañar con ícono/etiqueta (ej. el semáforo lleva texto).
- Respetar `prefers-reduced-motion` en animaciones.
