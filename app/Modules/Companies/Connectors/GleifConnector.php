<?php

namespace App\Modules\Companies\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\SourceDefinition;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * المؤسسة العالمية لمعرّف الكيان القانوني (GLEIF): سجل مفتوح بلا مفتاح لكل كيان قانوني يملك LEI.
 * الفحص: meta.goldenCopy.publishDate (نسخة ذهبية تُنشر يوميًا). الحد المعلن للمصدر 60 طلبًا في الدقيقة.
 */
abstract class GleifConnector extends ApiConnector
{
    protected const BASE = 'https://api.gleif.org/api/v1';

    /** فاصل بين الطلبات احترامًا للحد المعلن (60/دقيقة). */
    protected function throttle(): void
    {
        usleep((int) config('omanilab.ingest.gleif_throttle_microseconds'));
    }

    protected function source(): SourceDefinition
    {
        return new SourceDefinition(
            key: 'gleif',
            nameAr: 'GLEIF — معرّف الكيان القانوني',
            nameEn: 'Global Legal Entity Identifier Foundation',
            publisher: 'GLEIF',
            url: 'https://www.gleif.org',
            license: 'CC0 1.0 (Open data)',
        );
    }

    public function probe(): ProbeResult
    {
        $meta = $this->getJson(self::BASE.'/lei-records?page%5Bsize%5D=1&page%5Bnumber%5D=1&filter%5Bentity.legalAddress.country%5D=OM')['meta'] ?? [];
        $published = $meta['goldenCopy']['publishDate'] ?? null;
        if ($published === null) {
            throw new RuntimeException('استجابة GLEIF بلا goldenCopy.publishDate.');
        }

        return new ProbeResult(
            token: (string) $published,
            version: 'GLEIF Golden Copy — '.substr((string) $published, 0, 10),
            publishedAt: CarbonImmutable::parse($published, 'UTC'),
        );
    }

    /** @return array<string, mixed> */
    protected function page(string $query): array
    {
        $this->throttle();

        return $this->getJson(self::BASE.'/lei-records?'.$query);
    }
}
