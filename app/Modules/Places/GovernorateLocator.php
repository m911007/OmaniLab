<?php

namespace App\Modules\Places;

/** يحدد محافظة نقطة (خط عرض/طول) من حدود المحافظات المبسّطة (database/oman-governorates.geojson) بخوارزمية الشعاع. */
final class GovernorateLocator
{
    /** @var list<array{name: string, polygons: list<list<list<array{0: float, 1: float}>>>}> */
    private array $features = [];

    public function __construct(?string $file = null)
    {
        $file ??= database_path('oman-governorates.geojson');
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        foreach ($json['features'] ?? [] as $f) {
            $geometry = $f['geometry'] ?? [];
            $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
            $this->features[] = ['name' => trim(preg_replace('/^محافظة\s+/u', '', (string) ($f['properties']['name'] ?? ''))), 'polygons' => $polygons];
        }
    }

    public function find(float $lat, float $lon): ?string
    {
        foreach ($this->features as $feature) {
            foreach ($feature['polygons'] as $rings) {
                if ($this->inside($lon, $lat, $rings[0] ?? []) && ! $this->inHole($lon, $lat, array_slice($rings, 1))) {
                    return $feature['name'];
                }
            }
        }

        return null;
    }

    /** @param  list<list<array{0: float, 1: float}>>  $holes */
    private function inHole(float $x, float $y, array $holes): bool
    {
        foreach ($holes as $ring) {
            if ($this->inside($x, $y, $ring)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array{0: float, 1: float}>  $ring */
    private function inside(float $x, float $y, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
