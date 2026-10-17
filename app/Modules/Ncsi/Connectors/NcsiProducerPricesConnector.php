<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * الرقم القياسي لأسعار المنتج (PPI، سنة الأساس 2018) لعُمان — فصلي: يلتقط تكلفة الإنتاج عند المصنع والمنجم قبل أن تصل إلى المستهلك،
 * وهو شديد الحساسية لأسعار النفط (يتضمن التعدين واستخراج النفط والغاز)، فيسبق التضخم الاستهلاكي ويفسّر جزءًا من تقلباته.
 */
final class NcsiProducerPricesConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'jhmydsg';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.producer_prices';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الرقم القياسي لأسعار المنتج (فصلي)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman producer price index (quarterly)';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function series(): array
    {
        return [
            new KnoemaSeries('PPI_INDEX', 'الرقم القياسي لأسعار المنتج (2018=100)', 'Producer price index (2018=100)', 'نقطة', 'Q', 1000010, [new Range(0, 5000, 'reject'), new Completeness(0.1)]),
            new KnoemaSeries('PPI_INFLATION', 'تضخم أسعار المنتج السنوي', 'Producer price inflation (y/y)', '%', 'Q', 1000030, [new Range(-90, 500, 'reject'), new Completeness(0.1)]),
        ];
    }
}
