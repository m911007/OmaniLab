<?php

namespace App\Modules\Markets\Connectors;

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
 * أسعار صرف عملات شركاء عُمان التجاريين مقابل الدولار يوميًا (مراجع البنك المركزي الأوروبي عبر Frankfurter).
 * الريال العُماني مربوط بالدولار (0.3845)، فتغيّر الدولار أمام يوان الصين وروبية الهند ويورو أوروبا يغيّر كلفة واردات عُمان
 * وقيمة صادراتها بالعملات الأخرى.
 */
final class FrankfurterFxConnector extends ApiConnector
{
    private const URL = 'https://api.frankfurter.dev/v1/';

    private const FROM = '2020-01-01';

    /** @var array<string, array{0: string, 1: string, 2: float}> رمز العملة => [عربي، إنجليزي، حد أعلى معقول] */
    private const CURRENCIES = [
        'EUR' => ['اليورو', 'Euro', 5],
        'GBP' => ['الجنيه الإسترليني', 'Pound sterling', 5],
        'CNY' => ['اليوان الصيني', 'Chinese yuan', 50],
        'INR' => ['الروبية الهندية', 'Indian rupee', 500],
        'JPY' => ['الين الياباني', 'Japanese yen', 1000],
        'KRW' => ['الوون الكوري', 'South Korean won', 10000],
        'CHF' => ['الفرنك السويسري', 'Swiss franc', 5],
        'SGD' => ['الدولار السنغافوري', 'Singapore dollar', 10],
        'IDR' => ['الروبية الإندونيسية', 'Indonesian rupiah', 100000],
        'PHP' => ['البيزو الفلبيني', 'Philippine peso', 500],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::CURRENCIES as $code => [$ar, $en, $max]) {
            $indicators[] = new IndicatorDefinition('fx.usd_'.strtolower($code), "{$ar} مقابل الدولار", "{$en} per US dollar", "{$code} لكل دولار", 'D', [new Range(0.0001, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'fx.frankfurter',
            source: new SourceDefinition(
                key: 'frankfurter',
                nameAr: 'أسعار الصرف المرجعية (البنك المركزي الأوروبي)',
                nameEn: 'ECB reference exchange rates (Frankfurter)',
                publisher: 'European Central Bank / Frankfurter',
                url: 'https://frankfurter.dev',
                license: 'مفتوح (ECB reference rates)',
            ),
            nameAr: 'أسعار صرف عملات الشركاء مقابل الدولار (يومي)',
            nameEn: 'Partner currencies per US dollar (daily)',
            section: 'markets',
            intervalMinutes: 6 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $res = $this->httpAccepting('application/json')->timeout(90)->retry(2, 2000)
            ->get(self::URL.self::FROM.'..', ['base' => 'USD', 'symbols' => implode(',', array_keys(self::CURRENCIES))])->throw()->json();
        if (! isset($res['rates']) || ! is_array($res['rates']) || $res['rates'] === []) {
            throw new RuntimeException('Frankfurter: تغيّر شكل الاستجابة.');
        }

        return new RawPayload(json_encode($res['rates'], JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');

        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $day => $rates) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
                continue;
            }
            foreach ($rates as $code => $rate) {
                if (isset(self::CURRENCIES[$code]) && is_numeric($rate)) {
                    yield new ParsedRecord('fx.usd_'.strtolower($code), 'USA', $day, (float) $rate);
                }
            }
        }
    }
}
