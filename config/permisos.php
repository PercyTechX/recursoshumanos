<?php

/*
 * Catálogo de módulos y acciones para el control de accesos por rol.
 * Cada permiso se nombra "<modulo>.<accion>" (ej. empleados.crear).
 * Se usa para: generar los permisos (seeder), la matriz de Roles y las
 * verificaciones @can en pantallas y acciones.
 */

return [
    'empleados' => [
        'label' => 'Empleados',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'exportar' => 'Exportar'],
    ],
    'documentos' => [
        'label' => 'Documentos',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'avisar' => 'Avisar al supervisor'],
    ],
    'documentos_compartidos' => [
        'label' => 'Documentos compartidos',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'],
    ],
    'activos' => [
        'label' => 'Activos',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'asignar' => 'Asignar / Devolver'],
    ],
    'vacaciones' => [
        'label' => 'Vacaciones',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'aprobar' => 'Aprobar / Rechazar', 'eliminar' => 'Cancelar'],
    ],
    'ausencias' => [
        'label' => 'Ausencias',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'],
    ],
    'descuentos' => [
        'label' => 'Descuentos',
        'acciones' => ['ver' => 'Ver', 'aplicar' => 'Marcar aplicado'],
    ],
    'usuarios' => [
        'label' => 'Usuarios',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'],
    ],
];
