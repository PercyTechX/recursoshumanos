<?php

namespace App\Services\SharePoint;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de Microsoft Graph (app-only / client credentials).
 * Solo pide token (cacheado) y expone un PendingRequest ya autenticado.
 * Sin SDK externo — HTTP puro (ver docs/15).
 */
class GraphClient
{
    private const CACHE_TOKEN = 'graph.token';

    /** Token de acceso (client credentials), cacheado ~50 min. */
    public function token(): string
    {
        return Cache::remember(self::CACHE_TOKEN, now()->addMinutes(50), function () {
            $cfg = config('services.graph');

            $resp = Http::asForm()->post(
                "https://login.microsoftonline.com/{$cfg['tenant_id']}/oauth2/v2.0/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $cfg['client_id'],
                    'client_secret' => $cfg['client_secret'],
                    'scope' => 'https://graph.microsoft.com/.default',
                ]
            );

            if ($resp->failed()) {
                throw new RuntimeException('No se pudo obtener token de Graph: '.$resp->body());
            }

            return (string) $resp->json('access_token');
        });
    }

    /** Olvida el token cacheado (p.ej. tras un 401 para reintentar). */
    public function olvidarToken(): void
    {
        Cache::forget(self::CACHE_TOKEN);
    }

    /** Petición HTTP ya autenticada contra la API de Graph v1.0. */
    public function http(): PendingRequest
    {
        return Http::withToken($this->token())->baseUrl('https://graph.microsoft.com/v1.0');
    }
}
