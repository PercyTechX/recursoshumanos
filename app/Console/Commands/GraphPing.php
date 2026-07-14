<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Test de conectividad con Microsoft Graph / SharePoint (docs/15 §8).
 * Verifica en orden: salida HTTPS + token (client credentials) + resolución
 * del sitio + de la biblioteca (drive). No sube ni borra nada.
 */
class GraphPing extends Command
{
    protected $signature = 'graph:ping';

    protected $description = 'Prueba la conexión con Microsoft Graph/SharePoint (token, sitio y biblioteca)';

    public function handle(): int
    {
        $cfg = config('services.graph');

        foreach (['tenant_id', 'client_id', 'client_secret', 'site_host', 'site_path', 'drive_name'] as $k) {
            if (empty($cfg[$k])) {
                $this->error("Falta la configuración GRAPH_".strtoupper($k)." en el .env");

                return self::FAILURE;
            }
        }

        // 1) Token (app-only / client credentials)
        $this->line('1) Pidiendo token…');
        $tokenResp = Http::asForm()->post(
            "https://login.microsoftonline.com/{$cfg['tenant_id']}/oauth2/v2.0/token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'scope' => 'https://graph.microsoft.com/.default',
            ]
        );

        if ($tokenResp->failed()) {
            $this->error('   ✗ No se pudo obtener token.');
            $this->line('   '.$tokenResp->body());

            return self::FAILURE;
        }
        $token = $tokenResp->json('access_token');
        $this->info('   ✓ Token obtenido.');

        $graph = Http::withToken($token)->baseUrl('https://graph.microsoft.com/v1.0');

        // 2) Resolver el sitio
        $sitePath = ltrim($cfg['site_path'], '/'); // sites/GDSINFRAESTRUCTURASAC
        $this->line("2) Resolviendo sitio {$cfg['site_host']}:/{$sitePath}…");
        $siteResp = $graph->get("/sites/{$cfg['site_host']}:/{$sitePath}");

        if ($siteResp->failed()) {
            $this->error('   ✗ No se pudo resolver el sitio (código '.$siteResp->status().').');
            if ($siteResp->status() === 403) {
                $this->warn('   → 403: falta la CONCESIÓN por-sitio de Sites.Selected (docs/15 §7.4). '
                    .'Un admin debe conceder acceso a la app en este sitio.');
            }
            $this->line('   '.$siteResp->body());

            return self::FAILURE;
        }
        $siteId = $siteResp->json('id');
        $this->info('   ✓ Sitio OK. site-id: '.$siteId);

        // 3) Resolver la biblioteca (drive) por nombre
        $this->line("3) Buscando la biblioteca «{$cfg['drive_name']}»…");
        $drivesResp = $graph->get("/sites/{$siteId}/drives");
        if ($drivesResp->failed()) {
            $this->error('   ✗ No se pudieron listar las bibliotecas.');
            $this->line('   '.$drivesResp->body());

            return self::FAILURE;
        }

        $drives = collect($drivesResp->json('value', []));
        $this->line('   Bibliotecas encontradas: '.$drives->pluck('name')->implode(', '));

        $drive = $drives->firstWhere('name', $cfg['drive_name']);
        if (! $drive) {
            $this->error("   ✗ No existe una biblioteca llamada «{$cfg['drive_name']}» en el sitio.");
            $this->warn('   → Ajusta GRAPH_DRIVE_NAME al nombre exacto de una de las de arriba.');

            return self::FAILURE;
        }
        $this->info('   ✓ Biblioteca OK. drive-id: '.$drive['id']);

        $this->newLine();
        $this->info('✅ graph:ping en verde — token, sitio y biblioteca accesibles.');

        return self::SUCCESS;
    }
}
