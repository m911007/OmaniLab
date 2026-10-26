<?php

namespace App\Modules\MarketIntel;

use App\Modules\Comtrade\HsChapters;

/**
 * مراجع ثابتة لذكاء الأسواق: أقسام النظام المنسّق بالعربية (معرّف القسم في OEC = رقم القسم)، واتفاقيات التجارة الحرة النافذة لعُمان،
 * وربط منتجات HS4 بأسعار السلع الشهرية لصندوق النقد المخزَّنة في النظام (imf.pcps.*).
 */
final class IntelReference
{
    /** @var array<int, string> */
    public const SECTIONS = [
        1 => 'حيوانات حية ومنتجات حيوانية', 2 => 'منتجات نباتية', 3 => 'شحوم وزيوت حيوانية ونباتية', 4 => 'أغذية مصنَّعة ومشروبات وتبغ',
        5 => 'منتجات معدنية (نفط وغاز وخامات)', 6 => 'منتجات كيماوية وأدوية', 7 => 'لدائن (بلاستيك) ومطاط', 8 => 'جلود ومصنوعاتها',
        9 => 'خشب وفلين ومصنوعاتهما', 10 => 'ورق وكرتون ومطبوعات', 11 => 'منسوجات وملابس', 12 => 'أحذية وأغطية رأس',
        13 => 'مصنوعات حجر وإسمنت وخزف وزجاج', 14 => 'لؤلؤ وأحجار كريمة ومعادن ثمينة', 15 => 'معادن عادية (حديد وألمنيوم ونحاس) ومصنوعاتها', 16 => 'آلات وأجهزة كهربائية وإلكترونيات',
        17 => 'معدات نقل (سيارات وطائرات وسفن)', 18 => 'أجهزة بصرية وطبية وساعات', 19 => 'أسلحة وذخائر', 20 => 'مصنوعات متنوعة (أثاث وألعاب)', 21 => 'تحف فنية',
    ];

    /** @var array<string, string> دولة => الاتفاقية النافذة مع عُمان */
    public const FTA = [
        'ARE' => 'السوق الخليجية المشتركة', 'SAU' => 'السوق الخليجية المشتركة', 'QAT' => 'السوق الخليجية المشتركة', 'KWT' => 'السوق الخليجية المشتركة', 'BHR' => 'السوق الخليجية المشتركة',
        'EGY' => 'منطقة التجارة الحرة العربية الكبرى', 'JOR' => 'منطقة التجارة الحرة العربية الكبرى', 'LBN' => 'منطقة التجارة الحرة العربية الكبرى', 'MAR' => 'منطقة التجارة الحرة العربية الكبرى',
        'TUN' => 'منطقة التجارة الحرة العربية الكبرى', 'SYR' => 'منطقة التجارة الحرة العربية الكبرى', 'IRQ' => 'منطقة التجارة الحرة العربية الكبرى', 'SDN' => 'منطقة التجارة الحرة العربية الكبرى',
        'YEM' => 'منطقة التجارة الحرة العربية الكبرى', 'LBY' => 'منطقة التجارة الحرة العربية الكبرى', 'PSE' => 'منطقة التجارة الحرة العربية الكبرى', 'DZA' => 'منطقة التجارة الحرة العربية الكبرى',
        'USA' => 'اتفاقية التجارة الحرة العُمانية الأمريكية (2009)', 'SGP' => 'اتفاقية الخليج–سنغافورة (2013)',
        'CHE' => 'اتفاقية الخليج–إفتا (2014)', 'NOR' => 'اتفاقية الخليج–إفتا (2014)', 'ISL' => 'اتفاقية الخليج–إفتا (2014)', 'LIE' => 'اتفاقية الخليج–إفتا (2014)',
    ];

    /** @var array<string, list<string>> HS4 => مفاتيح أسعار السلع الشهرية المرتبطة */
    public const COMMODITIES = [
        '2709' => ['imf.pcps.poildub', 'imf.pcps.poilbre'], '2711' => ['imf.pcps.pngasjp', 'imf.pcps.pngaseu', 'imf.pcps.ppropane'], '2701' => ['imf.pcps.pcoalau'],
        '7108' => ['imf.pcps.pgold'], '7106' => ['imf.pcps.psilver'], '7403' => ['imf.pcps.pcopp'], '7402' => ['imf.pcps.pcopp'], '2603' => ['imf.pcps.pcopp'],
        '7601' => ['imf.pcps.palum'], '7901' => ['imf.pcps.pzinc'], '2608' => ['imf.pcps.pzinc'], '7502' => ['imf.pcps.pnick'], '2604' => ['imf.pcps.pnick'],
        '2601' => ['imf.pcps.piorecr'], '7201' => ['imf.pcps.piorecr'], '8001' => ['imf.pcps.ptin'], '7801' => ['imf.pcps.plead'], '2612' => ['imf.pcps.puran'],
        '1001' => ['imf.pcps.pwheamt'], '1101' => ['imf.pcps.pwheamt'], '1005' => ['imf.pcps.pmaizmt'], '1006' => ['imf.pcps.pricenpq'], '1003' => ['imf.pcps.pbarl'],
        '1701' => ['imf.pcps.psugaisa'], '1201' => ['imf.pcps.psoyb'], '2304' => ['imf.pcps.psmea'], '1507' => ['imf.pcps.psoil'], '1511' => ['imf.pcps.ppoil'],
        '1512' => ['imf.pcps.psuno'], '1514' => ['imf.pcps.proil'], '1509' => ['imf.pcps.polvoil'], '1202' => ['imf.pcps.pgnuts'],
        '3102' => ['imf.pcps.purea'], '3105' => ['imf.pcps.pdap'], '3104' => ['imf.pcps.ppotash'],
        '1801' => ['imf.pcps.pcoco'], '0901' => ['imf.pcps.pcoffotm', 'imf.pcps.pcoffrob'], '0902' => ['imf.pcps.ptea'],
        '0201' => ['imf.pcps.pbeef'], '0202' => ['imf.pcps.pbeef'], '0204' => ['imf.pcps.plamb'], '0207' => ['imf.pcps.ppoult'],
        '0803' => ['imf.pcps.pbansop'], '0805' => ['imf.pcps.porang'], '5201' => ['imf.pcps.pcottind'], '4001' => ['imf.pcps.prubb'],
    ];

    /**
     * كتالوج المنتجات مع القسم والفصل للقوائم المنسدلة.
     *
     * @param  list<array{id: int, hs4: string, name_en: string, name_ar: string}>  $products
     * @return array{sections: list<array{id: int, name: string, count: int}>, chapters: list<array{hs2: string, section: int, name: string, count: int}>, products: list<array<string, mixed>>}
     */
    public static function catalog(array $products): array
    {
        $sections = [];
        $chapters = [];
        $rows = [];
        foreach ($products as $p) {
            $section = intdiv($p['id'], 10000);
            $hs2 = substr($p['hs4'], 0, 2);
            $sections[$section] = ($sections[$section] ?? 0) + 1;
            $chapters[$hs2] ??= ['hs2' => $hs2, 'section' => $section, 'name' => HsChapters::AR[$hs2] ?? "الفصل {$hs2}", 'count' => 0];
            $chapters[$hs2]['count']++;
            $rows[] = $p + ['section' => $section, 'hs2' => $hs2, 'commodity' => isset(self::COMMODITIES[$p['hs4']])];
        }
        ksort($sections);
        ksort($chapters);

        return [
            'sections' => array_map(fn ($id, $n) => ['id' => $id, 'name' => self::SECTIONS[$id] ?? "القسم {$id}", 'count' => $n], array_keys($sections), $sections),
            'chapters' => array_values($chapters),
            'products' => $rows,
        ];
    }
}
