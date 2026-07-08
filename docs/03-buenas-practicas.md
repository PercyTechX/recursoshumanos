# Buenas Prácticas y Convenciones

Reglas de trabajo del proyecto. El objetivo: código **legible, mantenible y
seguro**, que cualquier persona del equipo pueda entender y extender.

---

## Principios

- **Simple antes que ingenioso.** Código claro > código "listo".
- **Convención sobre configuración.** Seguir las convenciones de Laravel.
- **Una responsabilidad por clase/método.** Controladores delgados.
- **No repetir (DRY)**, pero sin abstraer de más antes de tiempo.
- **Seguridad por defecto.** Validar, autorizar y escapar siempre.

---

## Convenciones de código

### Idioma
- **Código en inglés** (clases, métodos, variables, tablas): `Employee`, `expiry_date`.
- **Interfaz y textos al usuario en español**: "Fecha de vencimiento".
- **Comentarios**: español, solo cuando aclaran el *por qué* (no el *qué*).

> Nota: en la documentación usamos nombres en español (ej. `empleados`) para
> facilitar la lectura del negocio; en el código real se recomienda inglés
> (`employees`). Definir uno y **ser consistente** en todo el proyecto.

### Estilo
- **PSR-12**, aplicado automáticamente con **Laravel Pint** (`./vendor/bin/pint`).
- **Análisis estático** con **Larastan** antes de cada merge.
- Nombres descriptivos; evitar abreviaturas oscuras.

### Estructura por capas (ver arquitectura)
- **Controller** → recibe la petición y delega. Nada de lógica de negocio.
- **Form Request** → validación de entrada.
- **Policy** → ¿este usuario puede hacer esto?
- **Service / Action** → lógica de negocio.
- **Model** → datos y relaciones (accessors, scopes, casts).

### Base de datos
- Una **migración por cambio**; nunca editar una migración ya desplegada.
- Nombres de tabla en **plural** (`documentos`), claves foráneas `*_id`.
- Usar **relaciones Eloquent**, no consultas manuales.
- **Índices** en columnas de búsqueda/filtro (ej. `fecha_vencimiento`, `empleado_id`).
- Datos base en **seeders**; datos de prueba en **factories**.

---

## Git

### Ramas
- `main` — siempre desplegable.
- `feature/<nombre>` — una funcionalidad (ej. `feature/documentos-semaforo`).
- `fix/<nombre>` — corrección puntual.

> Nunca se trabaja directo sobre `main`.

### Commits (Conventional Commits)
```
feat: agrega semáforo de vigencia de documentos
fix: corrige cálculo de saldo de vacaciones
docs: actualiza plan de ejecución
refactor: extrae SaldoVacacionesService
test: cubre aprobación de solicitudes
chore: configura Laravel Pint
```

### Flujo
1. Crear rama desde `main`.
2. Commits pequeños y descriptivos.
3. Abrir Pull Request; revisar antes de mergear.
4. Merge a `main` → desplegar (desde Fase 4).

---

## Seguridad 🔒

- **`.env` NUNCA se sube** al repositorio (está en `.gitignore`). Usar `.env.example`.
- **Nunca** credenciales, tokens ni claves en el código.
- Validar **toda** entrada del usuario (Form Requests).
- Autorizar con **Policies/permisos** (no confiar solo en ocultar botones).
- Eloquent y Blade escapan por defecto → prevenir SQL Injection y XSS.
- Mantener CSRF activo en formularios (Laravel lo trae por defecto).
- **Datos sensibles de RRHH**: acceso por rol; registrar auditoría (quién creó/modificó).
- Documentos en OneDrive con enlaces temporales/seguros, no públicos.

---

## Pruebas

- **Feature tests** (Pest) de lo crítico: login/roles, alta de empleado, semáforo,
  aprobación de solicitud, cálculo de saldo.
- Ejecutar la suite antes de cada merge.
- No se busca 100% de cobertura; sí cubrir la **lógica de negocio y los flujos clave**.

---

## Auditoría y trazabilidad

- Campos `created_by` / `updated_by` (además de `timestamps`) en tablas sensibles.
- Considerar `spatie/laravel-activitylog` para historial de cambios importantes.
- Los `movimientos_vacaciones` ya son, por diseño, un registro auditable.

---

## Despliegue (checklist)

Antes de desplegar a `rrhh.gds.pe`:

- [ ] `./vendor/bin/pint` (formato) y Larastan (estático) sin errores.
- [ ] Pruebas en verde.
- [ ] `npm run build` (assets compilados en `public/build`).
- [ ] Variables de entorno de producción configuradas (no las de local).
- [ ] `php artisan config:cache` / `route:cache` / `view:cache` en servidor.
- [ ] Migraciones aplicadas (vía SSH si está disponible, o import SQL).
- [ ] `APP_ENV=production`, `APP_DEBUG=false`.

---

## Comandos útiles

```bash
# Calidad
./vendor/bin/pint            # formatear código
./vendor/bin/phpstan analyse # análisis estático (Larastan)
php artisan test             # correr pruebas

# Base de datos
php artisan migrate          # aplicar migraciones
php artisan migrate:fresh --seed  # reconstruir BD + datos de prueba (¡solo local!)

# Assets
npm run dev                  # desarrollo (watch)
npm run build                # producción
```
