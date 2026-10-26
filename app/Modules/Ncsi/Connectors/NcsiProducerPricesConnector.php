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

    protected function skipEmptySeries(): bool
    {
        return true;
    }

    /** @var array<string, array{0: int, 1: string, 2: string}> مجموعات PPI التي تخدم سلاسل القيمة: رمز => [عضو السلعة، عربي، إنجليزي] */
    private const GROUPS = [
        'STONE_SAND' => [1000040, 'الحجر والرمل والطين', 'Stone, sand and clay'],
        'GLASS_CEMENT' => [1000200, 'الزجاج والإسمنت والرخام', 'Glass, cement and marble products'],
        'IRON_STEEL_ALU' => [1000230, 'الحديد والصلب والألمنيوم الخام ومنتجاتها', 'Raw and products of iron, steel or aluminium'],
        'FAB_METAL' => [1000240, 'المنتجات المصنعة من الحديد والصلب والألمنيوم', 'Fabricated products of iron, steel or aluminium'],
        'POWER_EQUIP' => [1000270, 'محولات القدرة ومعدات توزيع الكهرباء', 'Power transformers and electricity distribution equipment'],
        'RUBBER_PLASTICS' => [1000190, 'منتجات المطاط والبلاستيك', 'Rubber and plastics products'],
        'REFINED_PETROLEUM' => [1000160, 'المنتجات النفطية المكررة', 'Refined petroleum products'],
        'CHEMICALS' => [1000170, 'المنتجات الكيماوية', 'Chemical products'],
        'ELECTRICITY' => [1000280, 'الطاقة الكهربائية', 'Electrical energy'],
    ];

    protected function series(): array
    {
        $groups = [];
        foreach (self::GROUPS as $code => [$member, $ar, $en]) {
            $groups[] = new KnoemaSeries("PPI_{$code}", "الرقم القياسي لأسعار المنتج: {$ar} (2018=100)", "PPI: {$en} (2018=100)", 'نقطة', 'Q', 1000010, [new Range(0, 5000, 'reject')], ['commodities' => $member, 'regions' => 1000000]);
        }

        return [
            ...$groups,
            new KnoemaSeries('PPI_INDEX', 'الرقم القياسي لأسعار المنتج (2018=100)', 'Producer price index (2018=100)', 'نقطة', 'Q', 1000010, [new Range(0, 5000, 'reject'), new Completeness(0.1)]),
            new KnoemaSeries('PPI_INFLATION', 'تضخم أسعار المنتج السنوي', 'Producer price inflation (y/y)', '%', 'Q', 1000030, [new Range(-90, 500, 'reject'), new Completeness(0.1)]),
        ];
    }
}
