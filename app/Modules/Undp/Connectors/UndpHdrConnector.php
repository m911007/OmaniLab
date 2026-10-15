<?php

namespace App\Modules\Undp\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * تقرير التنمية البشرية (UNDP HDR): ملف CSV رسمي بالسلاسل الزمنية الكاملة للمؤشرات المركّبة (1990→).
 * الرابط يتغير كل سنة (/{سنة}_HDR/HDR{yy}_Composite_indices_complete_time_series.csv)، فيُجرَّب الأحدث أولًا.
 * الفحص: ترويسة Last-Modified للملف. الترميز Windows-1252 (أسماء بحروف مُشكَّلة) فيُحوَّل إلى UTF-8.
 * أعمدة البيانات بصيغة <مؤشر>_<سنة>؛ الصفوف التجميعية (رموز مثل ZZA.VHHD) تُتخطى.
 */
final class UndpHdrConnector extends ApiConnector
{
    private const BASE = 'https://hdr.undp.org/sites/default/files';

    /** @var array<string, array{0: string, 1: string, 2: string|null, 3: int|float, 4: int|float}> رمز العمود => [عربي، إنجليزي، وحدة، حد أدنى، حد أعلى] */
    private const INDICATORS = [
        'hdi' => ['مؤشر التنمية البشرية', 'Human Development Index', '0–1', 0, 1],
        'ihdi' => ['مؤشر التنمية البشرية المعدَّل بعدم المساواة', 'Inequality-adjusted HDI', '0–1', 0, 1],
        'phdi' => ['مؤشر التنمية البشرية المعدَّل بالضغوط الكوكبية', 'Planetary pressures-adjusted HDI', '0–1', 0, 1],
        'le' => ['العمر المتوقع عند الولادة', 'Life expectancy at birth', 'سنة', 20, 100],
        'eys' => ['سنوات الدراسة المتوقعة', 'Expected years of schooling', 'سنة', 0, 30],
        'mys' => ['متوسط سنوات الدراسة', 'Mean years of schooling', 'سنة', 0, 20],
        'gnipc' => ['نصيب الفرد من الدخل القومي (تعادل القوة الشرائية)', 'GNI per capita (PPP)', 'دولار دولي 2021', 0, 400_000],
        'gdi' => ['مؤشر التنمية حسب الجنس', 'Gender Development Index', 'نسبة', 0, 2],
        'gii' => ['مؤشر عدم المساواة بين الجنسين', 'Gender Inequality Index', '0–1', 0, 1],
        'mmr' => ['معدل وفيات الأمهات', 'Maternal mortality ratio', 'لكل 100 ألف مولود', 0, 5_000],
        'abr' => ['معدل الولادة بين المراهقات', 'Adolescent birth rate', 'لكل 1000 امرأة', 0, 400],
        'co2_prod' => ['انبعاثات CO₂ للفرد (إنتاج)', 'CO2 emissions per capita (production)', 'طن', 0, 1_000],
    ];

    public function revision(): int
    {
        return 1;
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::INDICATORS as $code => [$ar, $en, $unit, $min, $max]) {
            $rules = [new Range($min, $max, 'reject'), new MaxRevision(max($max * 0.05, 0.05), 0.25), new Completeness(0.15)];
            if ($code === 'gnipc') {
                $rules = [new Range($min, $max, 'reject'), new RelativeRevision(0.3, 0.25), new Completeness(0.15)];
            }
            $indicators[] = new IndicatorDefinition("undp.{$code}", $ar, $en, $unit, 'A', $rules);
        }

        return new DatasetDefinition(
            key: 'undp.hdr',
            source: new SourceDefinition(
                key: 'undp',
                nameAr: 'برنامج الأمم المتحدة الإنمائي (UNDP)',
                nameEn: 'UNDP Human Development Report Office',
                publisher: 'UNDP',
                url: 'https://hdr.undp.org',
                license: 'CC BY 3.0 IGO',
            ),
            nameAr: 'التنمية البشرية (UNDP HDR)',
            nameEn: 'Human development indices (UNDP HDR)',
            section: 'development',
            intervalMinutes: 14 * 24 * 60,
            indicators: $indicators,
        );
    }

    /** @return array{url: string, year: int} أحدث إصدار منشور. */
    private function latestRelease(): array
    {
        for ($year = (int) date('Y'); $year >= (int) date('Y') - 3; $year--) {
            $url = sprintf('%s/%d_HDR/HDR%02d_Composite_indices_complete_time_series.csv', self::BASE, $year, $year % 100);
            if ($this->http()->head($url)->successful()) {
                return ['url' => $url, 'year' => $year];
            }
        }

        throw new RuntimeException('UNDP HDR: لم يُعثر على ملف السلاسل الزمنية لأي إصدار حديث.');
    }

    public function probe(): ProbeResult
    {
        ['url' => $url, 'year' => $year] = $this->latestRelease();
        $res = $this->http()->head($url);
        $modified = $res->header('Last-Modified') ?: $res->header('ETag');

        return new ProbeResult(
            token: $url.'|'.$modified,
            version: "HDR {$year}",
            publishedAt: $res->header('Last-Modified') ? CarbonImmutable::parse($res->header('Last-Modified'))->utc() : null,
        );
    }

    public function fetch(): RawPayload
    {
        ['url' => $url] = $this->latestRelease();
        $body = $this->http()->timeout(120)->get($url)->throw()->body();

        if (! mb_check_encoding($body, 'UTF-8')) {
            $body = (string) mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        }

        return new RawPayload($body, 'text/csv');
    }

    public function parse(RawPayload $payload): iterable
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $payload->body);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '');
        if (! $header || ($header[0] ?? '') !== "\u{FEFF}iso3" && ($header[0] ?? '') !== 'iso3') {
            throw new RuntimeException('UNDP HDR: تغيّر شكل الملف (العمود الأول المتوقع iso3).');
        }
        $header[0] = 'iso3';

        // أعمدة البيانات المطلوبة: <مؤشر>_<سنة>
        $columns = [];
        foreach ($header as $i => $name) {
            if (preg_match('/^([a-z0-9_]+)_(\d{4})$/', (string) $name, $m) && isset(self::INDICATORS[$m[1]])) {
                $columns[$i] = ["undp.{$m[1]}", $m[2]];
            }
        }
        if ($columns === []) {
            throw new RuntimeException('UNDP HDR: لم تُوجد أعمدة المؤشرات المتوقعة.');
        }

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $iso = trim((string) ($row[0] ?? ''));
            if (! preg_match('/^[A-Z]{3}$/', $iso)) {
                continue; // صفوف المجموعات والأقاليم
            }
            foreach ($columns as $i => [$indicator, $year]) {
                $v = $row[$i] ?? '';
                if ($v !== '' && is_numeric($v)) {
                    yield new ParsedRecord($indicator, $iso, $year, (float) $v);
                }
            }
        }
        fclose($handle);
    }
}
