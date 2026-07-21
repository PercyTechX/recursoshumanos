<?php

return [
    // Días que se conservan los backups en SharePoint; los más viejos se purgan.
    'retencion_dias' => (int) env('BACKUP_RETENCION_DIAS', 30),
];
