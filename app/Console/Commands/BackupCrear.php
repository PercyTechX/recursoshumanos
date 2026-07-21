<?php

namespace App\Console\Commands;

use App\Services\Backups\DbDump;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Genera un backup de la BD (.sql.gz) y lo sube a SharePoint (destino "backups":
 * biblioteca IT / BACKUP_SISTEMAS/RRHH_Sistemas). Purga los más viejos que la
 * retención. Si Graph falla o no hay credenciales, guarda local para no perderlo.
 * Ver docs/19.
 */
class BackupCrear extends Command
{
    protected $signature = 'backup:crear {--local : Solo genera el .sql.gz local, sin subir a SharePoint}';

    protected $description = 'Backup de la base de datos (.sql.gz) a SharePoint, con purga por retención';

    public function handle(DbDump $dump, SharePointDocs $docs): int
    {
        $this->info('Generando volcado de la base de datos…');
        $gz = gzencode($dump->generar(), 9);
        $nombre = now()->format('Y-m-d_His').'_rrhh.sql.gz';
        $this->line("Volcado listo: {$nombre} ({$this->tam($gz)})");

        // Sin credenciales de Graph o --local → guarda local y termina.
        if ($this->option('local') || ! config('services.graph.tenant_id')) {
            $this->guardarLocal($gz, $nombre);
            $this->info("Backup guardado en storage/app/private/backups/{$nombre} (sin SharePoint).");

            return self::SUCCESS;
        }

        try {
            $res = $docs->subirContenido($gz, 'application/gzip', '', $nombre, 'backups');
            $this->info("Subido a SharePoint: {$res['name']}");
        } catch (\Throwable $e) {
            // No perder el backup: cae a local y reporta.
            $this->guardarLocal($gz, $nombre);
            $this->error('No se pudo subir a SharePoint: '.$e->getMessage());
            $this->warn("Se guardó una copia local en storage/app/private/backups/{$nombre}.");

            return self::FAILURE;
        }

        $this->purgar($docs);

        return self::SUCCESS;
    }

    /** Borra de SharePoint los backups más viejos que la retención configurada. */
    private function purgar(SharePointDocs $docs): void
    {
        $dias = (int) config('backups.retencion_dias', 30);
        $limite = now()->subDays($dias);
        $borrados = 0;

        try {
            foreach ($docs->listar('', 'backups') as $item) {
                $creado = $item['createdDateTime'] ? Carbon::parse($item['createdDateTime']) : null;
                if ($creado && $creado->lt($limite)) {
                    $docs->eliminar($item['id'], 'backups');
                    $borrados++;
                }
            }
            if ($borrados > 0) {
                $this->line("Purga: {$borrados} backup(s) con más de {$dias} días eliminados.");
            }
        } catch (\Throwable $e) {
            $this->warn('No se pudo purgar backups viejos: '.$e->getMessage());
        }
    }

    private function guardarLocal(string $gz, string $nombre): void
    {
        Storage::disk('local')->put("backups/{$nombre}", $gz);
    }

    private function tam(string $s): string
    {
        $kb = strlen($s) / 1024;

        return $kb < 1024 ? round($kb, 1).' KB' : round($kb / 1024, 2).' MB';
    }
}
