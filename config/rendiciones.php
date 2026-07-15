<?php

/*
 | Datos de negocio del módulo Rendiciones (caja chica). Ver docs/16.
 | Se usan en la vista del técnico (instrucciones de devolución) y en la
 | Hoja Resumen PDF (Fase E). Centralizados aquí para editarlos en un solo lugar.
 */

return [
    // Empresa dueña de la caja chica (encabezado de la Hoja Resumen)
    'empresa' => [
        'nombre' => 'GDS INFRAESTRUCTURA SAC',
        'ruc' => '20551555187',
    ],

    // Crédito del desarrollador (pie de la Hoja Resumen)
    'elaborado_por' => [
        'nombre' => 'PercyTech - Solutions',
        'ruc' => '10463288271',
        'soporte' => '966804286', // WhatsApp
    ],

    // Cuentas de la empresa para que el técnico devuelva el vuelto
    'cuentas' => [
        ['banco' => 'Interbank', 'numero' => '169-30010821-43'],
        ['banco' => 'BCP', 'numero' => '191-98435080-71'],
    ],
];
