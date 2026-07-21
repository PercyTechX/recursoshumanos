<?php

namespace Tests\Feature;

use App\Services\Backups\DbDump;
use App\Services\SharePoint\SharePointDocs;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCrearTest extends TestCase
{
    /** DbDump falso: no toca MySQL (los tests corren en sqlite). */
    private function fakeDump(string $sql = "-- sql fake\nCREATE TABLE x;\n"): DbDump
    {
        return new class($sql) extends DbDump
        {
            public function __construct(private string $sql) {}

            public function generar(): string
            {
                return $this->sql;
            }
        };
    }

    public function test_local_genera_sql_gz_comprimido(): void
    {
        Storage::fake('local');
        $this->app->instance(DbDump::class, $this->fakeDump("-- contenido de prueba\n"));

        $this->artisan('backup:crear', ['--local' => true])->assertSuccessful();

        $archivos = Storage::disk('local')->files('backups');
        $this->assertCount(1, $archivos);
        $this->assertStringEndsWith('_rrhh.sql.gz', $archivos[0]);
        // El contenido debe descomprimir al SQL original
        $this->assertSame("-- contenido de prueba\n", gzdecode(Storage::disk('local')->get($archivos[0])));
    }

    public function test_sube_a_sharepoint_y_purga_los_viejos(): void
    {
        config(['services.graph.tenant_id' => 'fake-tenant', 'backups.retencion_dias' => 30]);
        $this->app->instance(DbDump::class, $this->fakeDump());

        $fakeDocs = new class extends SharePointDocs
        {
            public array $subidos = [];

            public array $borrados = [];

            public function __construct() {}

            public function subirContenido(string $contenido, string $mime, string $carpeta, string $nombre, string $destino = 'documentos'): array
            {
                $this->subidos[] = ['nombre' => $nombre, 'destino' => $destino, 'mime' => $mime];

                return ['item_id' => 'nuevo', 'web_url' => 'https://sp/'.$nombre, 'name' => $nombre];
            }

            public function listar(string $carpeta = '', string $destino = 'documentos'): array
            {
                return [
                    ['id' => 'viejo', 'name' => '2020-01-01_000000_rrhh.sql.gz', 'createdDateTime' => '2020-01-01T02:00:00Z'],
                    ['id' => 'reciente', 'name' => '2026-07-20_020000_rrhh.sql.gz', 'createdDateTime' => now()->toIso8601String()],
                ];
            }

            public function eliminar(string $itemId, string $destino = 'documentos'): void
            {
                $this->borrados[] = $itemId;
            }
        };
        $this->app->instance(SharePointDocs::class, $fakeDocs);

        $this->artisan('backup:crear')->assertSuccessful();

        // Subió un archivo con el patrón correcto al destino backups
        $this->assertCount(1, $fakeDocs->subidos);
        $this->assertStringEndsWith('_rrhh.sql.gz', $fakeDocs->subidos[0]['nombre']);
        $this->assertSame('backups', $fakeDocs->subidos[0]['destino']);
        $this->assertSame('application/gzip', $fakeDocs->subidos[0]['mime']);

        // Purgó solo el viejo (>30 días), no el reciente
        $this->assertSame(['viejo'], $fakeDocs->borrados);
    }
}
