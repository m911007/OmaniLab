<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\SeriesSpike;

/**
 * القوة الشرائية (البنك الدولي): دخل الفرد بتعادل القوة الشرائية، ومعاملات التحويل (PPP)، والاستهلاك الأسري، وأسعار المستهلك،
 * ونمو دخل الفرد الحقيقي. ويُشتق منها «مستوى الأسعار» = معامل تعادل الاستهلاك الخاص ÷ سعر الصرف × 100 (أمريكا = 100):
 * كم يكلّف سلة الأسرة في البلد قياسًا بأمريكا؟ أقل من 100 = أرخص.
 */
final class WorldBankPurchasingPowerConnector extends WorldBankConnector
{
    public const PRICE_LEVEL = 'PRICE_LEVEL_PRIVATE';

    protected function datasetKey(): string
    {
        return 'worldbank.purchasing_power';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: القوة الشرائية ومستوى الأسعار';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: purchasing power and price levels';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function fetchedIndicators(): array
    {
        // سعر الصرف مُعرَّف في مجموعة الاقتصاد الكلي (wb.PA.NUS.FCRF)؛ يُجلب هنا للاشتقاق فقط دون تعريف مؤشر مكرر.
        return [
            ...array_values(array_filter($this->indicators(), fn (WbIndicator $i) => $i->code !== self::PRICE_LEVEL)),
            WbIndicator::value('PA.NUS.FCRF', 'سعر الصرف الرسمي', 'Official exchange rate', 'عملة محلية/دولار', 1e7),
        ];
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('NY.GDP.PCAP.PP.CD', 'نصيب الفرد من الناتج بتعادل القوة الشرائية', 'GDP per capita, PPP (current international $)', 'دولار دولي'),
            WbIndicator::level('NY.GNP.PCAP.PP.CD', 'نصيب الفرد من الدخل القومي بتعادل القوة الشرائية', 'GNI per capita, PPP (current international $)', 'دولار دولي'),
            WbIndicator::level('NE.CON.PRVT.PP.CD', 'الاستهلاك الأسري بتعادل القوة الشرائية', 'Household consumption, PPP', 'دولار دولي'),
            WbIndicator::value('PA.NUS.PPP', 'معامل تحويل تعادل القوة الشرائية (الناتج)', 'PPP conversion factor, GDP (LCU per international $)', 'عملة محلية/دولار دولي', 1e7),
            WbIndicator::value('PA.NUS.PRVT.PP', 'معامل تحويل تعادل القوة الشرائية (الاستهلاك الخاص)', 'PPP conversion factor, private consumption', 'عملة محلية/دولار دولي', 1e7),
            WbIndicator::ratio('NY.GDP.PCAP.KD.ZG', 'نمو نصيب الفرد من الناتج الحقيقي', 'GDP per capita growth (constant prices)', '% سنويًا'),
            WbIndicator::ratio('NE.CON.PRVT.PC.KD.ZG', 'نمو الاستهلاك الأسري للفرد (حقيقي)', 'Household consumption per capita growth', '% سنويًا'),
            WbIndicator::value('FP.CPI.TOTL', 'الرقم القياسي لأسعار المستهلك (2010=100)', 'Consumer price index (2010 = 100)', 'نقطة', 1e9),
            new WbIndicator(self::PRICE_LEVEL, 'مستوى الأسعار (أمريكا = 100)', 'Price level index (US = 100)', 'أمريكا = 100', [new Range(5, 1000, 'reject'), new SeriesSpike(60), new Completeness(0.2)]),
        ];
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach (parent::parse($payload) as $item) {
            if (! ($item instanceof ParsedRecord && $item->indicatorKey === 'wb.PA.NUS.FCRF')) {
                yield $item;
            }
        }

        $fx = [];
        foreach ($data['values']['PA.NUS.FCRF'] ?? [] as [$entity, $year, $value]) {
            $fx[$entity][(string) $year] = (float) $value;
        }
        foreach ($data['values']['PA.NUS.PRVT.PP'] ?? [] as [$entity, $year, $ppp]) {
            $rate = $fx[$entity][(string) $year] ?? null;
            if ($rate !== null && $rate > 0 && is_numeric($ppp) && preg_match('/^\d{4}$/', (string) $year)) {
                yield new ParsedRecord($this->keyPrefix().self::PRICE_LEVEL, (string) $entity, (string) $year, round((float) $ppp / $rate * 100, 2));
            }
        }
    }
}
