<?php

namespace App\Modules\Manafeth\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\SourceDefinition;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * منافذ (manafeth.ncsi.gov.om): الإحصاءات الجمركية الرسمية للتجارة الخارجية لسلطنة عُمان (المركز الوطني للإحصاء والمعلومات).
 * واجهتها العامة تقبل POST بجسم {"obj_json": "<السنة>"} وتُرجع حقولًا قيمتها نصوص JSON متداخلة.
 * الفحص: GetStatisticsStartYear يعيد آخر سنة وشهر منشورين. السنة الجارية غير مكتملة دائمًا ما لم يكن الشهر 12.
 */
abstract class ManafethConnector extends ApiConnector
{
    protected const BASE = 'https://manafeth.ncsi.gov.om/api/Main';

    protected function source(): SourceDefinition
    {
        return new SourceDefinition(
            key: 'manafeth',
            nameAr: 'منافذ — الإحصاءات الجمركية',
            nameEn: 'Manafeth customs statistics (NCSI)',
            publisher: 'NCSI',
            url: 'https://manafeth.ncsi.gov.om',
            license: 'Oman Open Data — attribution required',
        );
    }

    /** @return array{year: int, month: int} آخر فترة منشورة */
    protected function latestPeriod(): array
    {
        $doc = $this->http()->post(self::BASE.'/GetStatisticsStartYear', (object) [])->throw()->json();
        $row = $doc['DataObject'][0] ?? null;
        if (! $row || ! isset($row['YEAR'], $row['MONTH'])) {
            throw new RuntimeException('استجابة منافذ لا تحوي آخر سنة وشهر: '.json_encode($doc));
        }

        return ['year' => (int) $row['YEAR'], 'month' => (int) $row['MONTH']];
    }

    public function probe(): ProbeResult
    {
        ['year' => $y, 'month' => $m] = $this->latestPeriod();

        return new ProbeResult(
            token: "{$y}-{$m}",
            version: sprintf('منافذ — حتى %04d-%02d', $y, $m),
            publishedAt: CarbonImmutable::create($y, $m, 1, 0, 0, 0, 'UTC')->endOfMonth(),
        );
    }

    /** السنة الأخيرة كاملة فقط إن كان آخر شهر منشور هو 12. */
    protected function lastCompleteYear(): int
    {
        ['year' => $y, 'month' => $m] = $this->latestPeriod();

        return $m >= 12 ? $y : $y - 1;
    }

    /**
     * طلب إحصاءات سنة (أو كل السنوات إن كانت فارغة). الاستجابات الكبيرة تحتاج مهلة أطول.
     *
     * @return list<array<string, mixed>>
     */
    protected function statistics(string $year): array
    {
        $doc = $this->http()->timeout(240)->post(self::BASE.'/GetStatistics', ['obj_json' => $year])->throw()->json();
        if (empty($doc['DataObject'])) {
            throw new RuntimeException("منافذ لم تُرجع بيانات للسنة '{$year}': ".json_encode($doc['Message'] ?? null));
        }

        return $doc['DataObject'];
    }
}
