<?php

namespace App\Modules\Adp;

/** منطق مشترك لتحويل صفوف بوابة التنمية العربية إلى سلاسل (indicator + dims) وقيم سنوية. */
final class AdpCatalog
{
    /** @param  array<string, mixed>  $spec */
    public function __construct(private readonly array $spec) {}

    public static function snapshotPath(string $id): string
    {
        return database_path("data/adp/{$id}.json");
    }

    /** @return array<string, array{name: string, unit: string|null}> */
    public static function snapshot(string $id): array
    {
        $file = self::snapshotPath($id);

        return is_file($file) ? (json_decode((string) file_get_contents($file), true)['series'] ?? []) : [];
    }

    /** قيم «غير متاح/فارغة/ NA» لا تدخل هوية السلسلة. */
    private function meaningful(mixed $v): bool
    {
        $s = trim((string) $v);

        return $s !== '' && ! preg_match('/(^|\s)NA$|not available$|^n\/a$/i', $s);
    }

    /** @param  array<string, mixed>  $row */
    public function seriesName(array $row): string
    {
        $parts = [trim((string) ($row[$this->spec['indicator']] ?? ''))];
        foreach ($this->spec['dims'] as $d) {
            if ($this->meaningful($row[$d] ?? null)) {
                $parts[] = trim((string) $row[$d]);
            }
        }

        return implode(' — ', array_filter($parts));
    }

    public function seriesId(string $dataset, string $name, ?string $unit): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name.($unit ? " {$unit}" : ''))), '-');

        return 'adp.'.$dataset.'.'.substr($slug, 0, 70).'-'.substr(md5($name.'|'.$unit), 0, 6);
    }

    public function iso(array $row): ?string
    {
        $v = trim((string) ($row[$this->spec['country']] ?? ''));
        if ($this->spec['country_kind'] === 'iso3') {
            return preg_match('/^[A-Z]{3}$/', $v) ? $v : null;
        }

        return config('adp.arab_countries')[$v] ?? null;
    }

    /**
     * كل (دولة، سلسلة، سنة، قيمة) من صفوف المصدر.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return iterable<array{0: string, 1: string, 2: ?string, 3: string, 4: float, 5: string}> [دولة، اسم السلسلة، وحدة، سنة، قيمة]
     */
    public function observations(array $rows): iterable
    {
        foreach ($rows as $row) {
            $iso = $this->iso($row);
            if ($iso === null) {
                continue;
            }
            $name = $this->seriesName($row);
            $unit = $this->spec['unit'] ? trim((string) ($row[$this->spec['unit']] ?? '')) ?: null : null;
            if ($this->spec['shape'] === 'long') {
                $year = (string) ($row[$this->spec['time']] ?? '');
                $value = $row[$this->spec['value']] ?? null;
                if (preg_match('/^\d{4}$/', $year) && is_numeric($value)) {
                    yield [$iso, $name, $unit, $year, (float) $value];
                }
                continue;
            }
            foreach ($row as $col => $value) {
                if (preg_match('/^\d{4}$/', (string) $col) && is_numeric($value)) {
                    yield [$iso, $name, $unit, (string) $col, (float) $value];
                }
            }
        }
    }
}
