<?php

namespace App\Modules\ValueChain;

use App\Core\Support\XlsxReader;
use RuntimeException;

/**
 * يحوّل ملف Excel/CSV يرفعه المحلل إلى سلاسل: العمود الأول التاريخ/الفترة، وكل عمود تالٍ سلسلة اسمها رأس العمود.
 * الفترات المقبولة: 2025-07 أو 2025-07-15 أو 2025/07 أو 2025-Q3 أو 2025 أو تاريخ Excel الرقمي؛ وتُطبَّع حسب وتيرة السلسلة.
 * الصفوف غير الصالحة لا تُرفض بصمت: تعود في قائمة التحذيرات.
 */
final class UploadParser
{
    /**
     * @return array{series: array<string, array<string, float>>, warnings: list<string>}
     */
    public function parse(string $path, string $extension, string $frequency): array
    {
        $rows = $this->rows($path, $extension);
        if (count($rows) < 2) {
            throw new RuntimeException('الملف فارغ أو بلا صف عناوين.');
        }
        $header = array_map(fn ($h) => trim((string) $h), array_shift($rows));
        $series = [];
        $warnings = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $period = $this->period($row[0] ?? null, $frequency);
            if ($period === null) {
                if (trim((string) ($row[0] ?? '')) !== '') {
                    $warnings[] = "السطر {$line}: فترة غير مفهومة «{$row[0]}».";
                }

                continue;
            }
            for ($c = 1; $c < count($header); $c++) {
                $name = $header[$c];
                $raw = trim(str_replace([',', ' '], '', (string) ($row[$c] ?? '')));
                if ($name === '' || $raw === '' || $raw === '-') {
                    continue;
                }
                if (! is_numeric($raw)) {
                    $warnings[] = "السطر {$line}، «{$name}»: قيمة غير رقمية «{$row[$c]}».";

                    continue;
                }
                $series[$name][$period] = (float) $raw;
            }
        }
        if ($series === []) {
            throw new RuntimeException('لا قيم رقمية صالحة في الملف.');
        }

        return ['series' => $series, 'warnings' => array_slice($warnings, 0, 50)];
    }

    public function period(mixed $raw, string $frequency): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $s)) { // تاريخ Excel الرقمي
            $s = (new \DateTimeImmutable('1899-12-30'))->modify('+'.(int) $s.' day')->format('Y-m-d');
        }
        $s = str_replace('/', '-', $s);
        if (preg_match('/^(\d{4})-Q([1-4])$/i', $s, $m)) {
            return $frequency === 'A' ? $m[1] : ($frequency === 'Q' ? "{$m[1]}-Q{$m[2]}" : null);
        }
        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $s, $m)) {
            $y = $m[1];
            $mo = isset($m[2]) && $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : null;
            $d = isset($m[3]) && $m[3] !== '' ? str_pad($m[3], 2, '0', STR_PAD_LEFT) : null;
            if ($mo !== null && ((int) $mo < 1 || (int) $mo > 12)) {
                return null;
            }

            return match ($frequency) {
                'A' => $y,
                'Q' => $mo ? $y.'-Q'.(intdiv((int) $mo - 1, 3) + 1) : null,
                'M' => $mo ? "{$y}-{$mo}" : null,
                default => $mo && $d ? "{$y}-{$mo}-{$d}" : null,
            };
        }

        return null;
    }

    /** @return list<list<string|null>> صفوف الملف كما هي (الأول العناوين) */
    public function rows(string $path, string $extension): array
    {
        return strtolower($extension) === 'xlsx' ? $this->xlsxRows($path) : $this->csvRows($path);
    }

    /** @return list<list<string|null>> */
    private function csvRows(string $path): array
    {
        $h = fopen($path, 'r');
        if (! $h) {
            throw new RuntimeException('تعذّر قراءة الملف.');
        }
        $first = (string) fgets($h);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delimiter, '"', '')) !== false) {
            $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $r[0]);
            $rows[] = $r;
        }
        fclose($h);

        return $rows;
    }

    /** @return list<list<string|null>> */
    private function xlsxRows(string $path): array
    {
        $x = new XlsxReader($path);
        $sheet = $x->sheet($x->sheetNames()[0] ?? throw new RuntimeException('ملف xlsx بلا أوراق.'));
        $out = [];
        foreach ($sheet as $cells) {
            $row = [];
            foreach ($cells as $col => $v) {
                $row[$this->colIndex($col)] = $v;
            }
            if ($row !== []) {
                $max = max(array_keys($row));
                $out[] = array_map(fn ($i) => $row[$i] ?? null, range(0, $max));
            }
        }

        return $out;
    }

    private function colIndex(string $col): int
    {
        $n = 0;
        foreach (str_split($col) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
