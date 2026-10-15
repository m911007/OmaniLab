<?php

namespace App\Modules\Companies\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * عدد الكيانات القانونية الحاملة لـ LEI في كل دولة (مقياس عالمي لرسمنة قطاع الشركات وانفتاحه المالي).
 * لقطة شهرية: الفترة YYYY-MM من تاريخ النسخة الذهبية، فيتراكم التاريخ شهرًا بعد شهر.
 * GLEIF يرمّز الدول ISO2؛ تُحوَّل إلى ISO3 بسمة iso2 التي يجلبها البنك الدولي (يتأخر الجلب إن لم تصل بعد).
 */
final class GleifLeiCountsConnector extends GleifConnector
{
    /** مئات الاستدعاءات أو ملفات ثقيلة: طابورها المستقل كي لا تحجب بقية المصادر. */
    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'gleif.lei_counts',
            source: $this->source(),
            nameAr: 'عدد الكيانات القانونية (LEI) لكل دولة',
            nameEn: 'LEI-registered legal entities per country',
            section: 'companies',
            intervalMinutes: 7 * 24 * 60,
            indicators: [
                new IndicatorDefinition('gleif.LEI_TOTAL', 'عدد الكيانات القانونية الحاملة لـ LEI', 'Legal entities with an LEI', 'كيان', 'M',
                    [new Range(0, 50_000_000), new Completeness(0.2)]),
            ],
        );
    }

    public function fetch(): RawPayload
    {
        $published = (string) ($this->getJson(self::BASE.'/lei-records?page%5Bsize%5D=1&filter%5Bentity.legalAddress.country%5D=OM')['meta']['goldenCopy']['publishDate'] ?? '');
        $countries = [];
        $page = 1;
        do {
            $res = $this->getJson(self::BASE.'/countries?page%5Bsize%5D=100&page%5Bnumber%5D='.$page);
            foreach ($res['data'] ?? [] as $c) {
                $countries[] = (string) ($c['attributes']['code'] ?? '');
            }
            $last = (int) ($res['meta']['pagination']['lastPage'] ?? 1);
        } while ($page++ < $last);

        $counts = [];
        foreach (array_filter($countries) as $iso2) {
            $res = $this->page("page%5Bsize%5D=1&filter%5Bentity.legalAddress.country%5D={$iso2}");
            $counts[$iso2] = (int) ($res['meta']['pagination']['total'] ?? 0);
        }

        return new RawPayload(json_encode(['published' => $published, 'counts' => $counts], JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $period = substr((string) $data['published'], 0, 7);

        $iso3 = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')
            ->where('a.key', 'iso2')->selectRaw("a.value #>> '{}' as iso2, e.code as code")->pluck('code', 'iso2')->all();
        if (count($iso3) < 150) {
            throw new RuntimeException('جدول ISO2→ISO3 غير جاهز بعد (ينتظر بيانات الدول من البنك الدولي): '.count($iso3));
        }

        foreach ($data['counts'] as $iso2 => $total) {
            if ($total > 0 && isset($iso3[$iso2])) {
                yield new ParsedRecord('gleif.LEI_TOTAL', $iso3[$iso2], $period, (float) $total);
            }
        }
    }
}
