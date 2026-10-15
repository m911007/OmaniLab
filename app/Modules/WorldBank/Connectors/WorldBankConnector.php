<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Ingestion\EntityNormaliser;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * البنك الدولي — مؤشرات التنمية العالمية (WDI) لكل الدول والمجموعات.
 * الفحص: أي استدعاء يحمل meta.lastupdated (تاريخ آخر تحديث للمصدر)، فنستعمل طلبًا صغيرًا واحدًا.
 * كل مؤشر طلب واحد (كل الدول × كل السنوات) مع متابعة الصفحات.
 */
abstract class WorldBankConnector extends ApiConnector
{
    private const BASE = 'https://api.worldbank.org/v2';

    private const PER_PAGE = 20000;

    /** @return list<WbIndicator> */
    abstract protected function indicators(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    abstract protected function section(): string;

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    /** 2: أُضيفت سمة iso2 للدول. */
    public function revision(): int
    {
        return 2;
    }

    /** آخر سنة تُطلب (2030 افتراضيًا؛ التوقعات السكانية تمتد إلى 2050). */
    protected function lastYear(): int
    {
        return 2030;
    }

    /** بادئة مفتاح المؤشر الداخلي؛ مجموعة بمصدر مختلف تحتاج بادئة خاصة كي لا يتصادم مؤشرها مع المؤشر نفسه في WDI. */
    protected function keyPrefix(): string
    {
        return 'wb.';
    }

    /** مصدر البنك الدولي (2 = WDI الافتراضي؛ 3 = WGI للحوكمة). null يترك الافتراضي. */
    protected function sourceId(): ?int
    {
        return null;
    }

    private function sourceQuery(): string
    {
        return $this->sourceId() !== null ? '&source='.$this->sourceId() : '';
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: $this->datasetKey(),
            source: new SourceDefinition(
                key: 'worldbank',
                nameAr: 'البنك الدولي',
                nameEn: 'World Bank',
                publisher: 'The World Bank Group',
                url: 'https://data.worldbank.org',
                license: 'CC BY 4.0',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: $this->intervalMinutes(),
            indicators: array_map(fn (WbIndicator $i) => new IndicatorDefinition(
                key: $this->keyPrefix().$i->code, nameAr: $i->nameAr, nameEn: $i->nameEn, unit: $i->unit, frequency: 'A', rules: $i->rules,
            ), $this->indicators()),
        );
    }

    protected function sourceTitle(): string
    {
        return 'World Development Indicators';
    }

    public function probe(): ProbeResult
    {
        $first = $this->indicators()[0]->code;
        $meta = $this->getJson(self::BASE."/country/OMN/indicator/{$first}?format=json&per_page=1".$this->sourceQuery())[0] ?? [];
        $updated = $meta['lastupdated'] ?? null;
        if ($updated === null) {
            throw new RuntimeException('استجابة البنك الدولي بلا lastupdated: '.json_encode($meta));
        }

        return new ProbeResult(
            token: $updated.'|'.$first,
            version: $this->sourceTitle().' — '.$updated,
            publishedAt: CarbonImmutable::parse($updated, 'UTC'),
        );
    }

    public function fetch(): RawPayload
    {
        $body = ['countries' => [], 'values' => []];

        $page = 1;
        do {
            $res = $this->getJson(self::BASE."/country?format=json&per_page=400&page={$page}");
            $body['countries'] = array_merge($body['countries'], $res[1] ?? []);
            $pages = (int) ($res[0]['pages'] ?? 1);
        } while ($page++ < $pages);

        foreach ($this->indicators() as $indicator) {
            $rows = [];
            $page = 1;
            do {
                $url = self::BASE."/country/all/indicator/{$indicator->code}?format=json&per_page=".self::PER_PAGE."&date=1960:{$this->lastYear()}&page={$page}".$this->sourceQuery();
                $res = $this->getJson($url);
                if (! isset($res[1])) {
                    $msg = $res[0]['message'][0]['value'] ?? json_encode($res[0] ?? $res);
                    throw new RuntimeException("{$indicator->code}: {$msg}");
                }
                foreach ($res[1] as $r) {
                    // بعض الأقاليم لا يمنحها البنك رمزًا (أنغيلا، مايوت…) فلا يمكن تعريفها ككيان؛ تُتخطى.
                    $entity = $r['countryiso3code'] ?: $r['country']['id'];
                    if ($r['value'] !== null && $entity !== '') {
                        $rows[] = [$entity, $r['date'], $r['value']];
                    }
                }
                $pages = (int) ($res[0]['pages'] ?? 1);
            } while ($page++ < $pages);

            $body['values'][$indicator->code] = $rows;
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        $normaliser = app(EntityNormaliser::class);
        foreach ($data['countries'] as $c) {
            $aggregate = ($c['region']['value'] ?? '') === 'Aggregates';
            yield new ParsedEntity(
                (string) $c['id'], (string) $c['name'], $aggregate ? 'group' : 'country',
                $normaliser->arabicName((string) $c['id'], $aggregate ? null : ($c['iso2Code'] ?? null)),
            );
            // ISO2 يربط مصادر ترمّز الدول بحرفين (GLEIF…) بكياناتنا المرمَّزة ISO3.
            if (! $aggregate && ! empty($c['iso2Code'])) {
                yield new ParsedAttribute((string) $c['id'], 'iso2', (string) $c['iso2Code']);
            }
        }

        foreach ($data['values'] as $code => $rows) {
            foreach ($rows as [$entity, $year, $value]) {
                if (is_numeric($value) && preg_match('/^\d{4}$/', (string) $year)) {
                    yield new ParsedRecord($this->keyPrefix().$code, (string) $entity, (string) $year, (float) $value);
                }
            }
        }
    }
}
