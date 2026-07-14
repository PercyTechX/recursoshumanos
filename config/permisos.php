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
    'clientes' => [
        'label' => 'Clientes y sedes',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'],
    ],
    'tickets' => [
        'label' => 'Tickets',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'cerrar' => 'Cerrar', 'eliminar' => 'Eliminar'],
    ],
    'asistencia' => [
        'label' => 'Asistencia (control)',
        'acciones' => ['ver' => 'Ver', 'registrar' => 'Registrar manual', 'editar' => 'Editar/corregir'],
    ],
    'rendiciones' => [
        'label' => 'Rendiciones (caja chica)',
        'acciones' => ['ver' => 'Ver', 'registrar' => 'Registrar depósito', 'aprobar' => 'Aprobar / Rechazar', 'ampliar' => 'Ampliar monto', 'anular' => 'Anular'],
    ],
    'usuarios' => [
        'label' => 'Usuarios',
        'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar'],
    ],
];
