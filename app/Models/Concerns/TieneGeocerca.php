<?php

namespace App\Models\Concerns;

/**
 * Geocerca circular: el modelo tiene latitud, longitud y radio_metros.
 * Valida si una coordenada cae dentro del círculo (fórmula de haversine).
 */
trait TieneGeocerca
{
    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /** Distancia en metros entre la geocerca y una coordenada. */
    public function distanciaMetros(float $lat, float $lng): ?float
    {
        if (! $this->tieneUbicacion()) {
            return null;
        }

        $radioTierra = 6371000; // metros
        $lat1 = deg2rad((float) $this->latitud);
        $lat2 = deg2rad($lat);
        $dLat = deg2rad($lat - (float) $this->latitud);
        $dLng = deg2rad($lng - (float) $this->longitud);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** ¿La coordenada está dentro de la geocerca? */
    public function contiene(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null || ! $this->tieneUbicacion()) {
            return false;
        }

        return $this->distanciaMetros($lat, $lng) <= (int) ($this->radio_metros ?? 100);
    }
}
