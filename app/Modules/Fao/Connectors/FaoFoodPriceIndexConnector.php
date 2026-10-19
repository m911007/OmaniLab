<?php

namespace App\Modules\Fao\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * مؤشر أسعار الغذاء العالمي (FAO Food Price Index) شهريًا منذ 1990 (2014-2016=100): المؤشر العام واللحوم والألبان والحبوب والزيوت والسكر.
 * ملف CSV الرسمي العام الذي تنشره المنظمة مع كل تحديث شهري.
 */
final class FaoFoodPriceIndexConnector extends ApiConnector
{
    private const URL = 'https://www.fao.org/media/docs/worldfoodsituationlibraries/wfs-library/food_price_indices_data.csv';

    /** @var array<string, array{0: string, 1: string, 2: string}> عنوان العمود => [مفتاح، عربي، إنجليزي] */
    private const COLUMNS = [
        'Food Price Index' => ['fao.ffpi', 'مؤشر أسعار الغذاء العالمي (FAO)', 'FAO Food Price Index'],
        'Meat' => ['fao.ffpi_meat', 'مؤشر أسعار اللحوم العالمي', 'FAO meat price index'],
        'Dairy' => ['fao.ffpi_dairy', 'مؤشر أسعار الألبان العالمي', 'FAO dairy price index'],
        'Cereals' => ['fao.ffpi_cereals', 'مؤشر أسعار الحبوب العالمي', 'FAO cereals price index'],
        'Oils' => ['fao.ffpi_oils', 'مؤشر أسعار الزيوت النباتية العالمي', 'FAO vegetable oils price index'],
        'Sugar' => ['fao.ffpi_sugar', 'مؤشر أسعار السكر العالمي', 'FAO sugar price index'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::COLUMNS as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'مؤشر (2014-2016=100)', 'M', [new Range(10, 600, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'fao.food_price_index',
            source: new SourceDefinition(key: 'fao', nameAr: 'منظمة الأغذية والزراعة (FAO)', nameEn: 'Food and Agriculture Organization (FAO)', publisher: 'FAO', url: 'https://www.fao.org/worldfoodsituation/foodpricesindex', license: 'FAO — مع الإشارة إلى المصدر'),
            nameAr: 'مؤشر أسعار الغذاء العالمي (شهري)',
            nameEn: 'FAO Food Price Index (monthly)',
            section: 'markets',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $csv = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 4000)->get(self::URL)->throw()->body();
        $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
        $headerAt = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, 'Date,')) {
                $headerAt = $i;
                break;
            }
        }
        if ($headerAt === null) {
            throw new RuntimeException('FAO FFPI: تغيّر شكل الملف.');
        }
        $header = str_getcsv($lines[$headerAt], ',', '"', '');
        $out = [];
        foreach (array_slice($lines, $headerAt + 1) as $line) {
            $f = str_getcsv($line, ',', '"', '');
            if (! preg_match('/^\d{4}-\d{2}$/', $f[0] ?? '')) {
                continue;
            }
            foreach (self::COLUMNS as $column => [$key]) {
                $i = array_search($column, $header, true);
                if ($i !== false && isset($f[$i]) && is_numeric($f[$i])) {
                    $out[] = [$key, $f[0], (float) $f[$i]];
                }
            }
        }
        if ($out === []) {
            throw new RuntimeException('FAO FFPI: لا بيانات.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'WLD', $period, $value);
        }
    }
}
