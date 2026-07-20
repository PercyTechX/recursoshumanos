<?php

/*
 | Identidad del EMPLEADOR (empresa cliente) para documentos oficiales
 | (certificado de trabajo, etc.). Editable por .env.
 */

return [
    'nombre' => env('EMPRESA_NOMBRE', 'GDS INFRAESTRUCTURA SAC'),
    'ruc' => env('EMPRESA_RUC', '20551555187'),
    'ciudad' => env('EMPRESA_CIUDAD', 'Lima'),
    'direccion' => env('EMPRESA_DIRECCION', ''),
];
