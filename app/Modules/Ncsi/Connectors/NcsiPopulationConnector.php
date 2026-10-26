<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;

/**
 * سكان عُمان بالعدّاد الرسمي (المركز الوطني للإحصاء): عدد السكان شهريًا حتى الشهر الجاري (إجمالي وعُمانيون ووافدون)، والمواليد والوفيات شهريًا،
 * وأعمار ومعدلات سنوية (العمر المتوقع، الخصوبة، وفيات الرضع). المنطقة «إجمالي السلطنة» (1000010) لا 1000000 التي لا بيانات فيها.
 * أحدث من كل المصادر العالمية؛ يصلح مرجعًا لمطابقة تقديرات السكان.
 */
final class NcsiPopulationConnector extends KnoemaConnector
{
    private const REGION_TOTAL_SULTANATE = 1000010;

    private const OMANI = 1000010;

    private const EXPAT = 1000020;

    protected function knoemaDatasetId(): string
    {
        return 'OMPOP2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.population';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: السكان والمواليد والوفيات (عدّاد رسمي)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman population, births and deaths (official counter)';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function series(): array
    {
        $region = ['region' => self::REGION_TOTAL_SULTANATE];
        $count = fn () => [new Range(0, 1e8, 'reject'), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];
        $rate = fn (float $max) => [new Range(0, $max, 'reject'), new Completeness(0.1)];

        $bySex = [];
        foreach ([['', 'POP', null], ['_OMANI', 'POP', self::OMANI], ['_EXPAT', 'POP', self::EXPAT]] as [$suffix, , $nat]) {
            foreach (['MALE' => [1000010, 'ذكور', 'Male'], 'FEMALE' => [1000020, 'إناث', 'Female']] as $code => [$sex, $ar, $en]) {
                $label = ['' => 'الإجمالي', '_OMANI' => 'العُمانيون', '_EXPAT' => 'الوافدون'][$suffix];
                $labelEn = ['' => 'total', '_OMANI' => 'Omani', '_EXPAT' => 'expatriate'][$suffix];
                $bySex[] = new KnoemaSeries("POP{$suffix}_{$code}", "السكان {$label}: {$ar} (شهري)", "Population {$labelEn}: {$en} (monthly)", 'نسمة', 'M', 1000140, $count(), $region + ($nat ? ['nationality' => $nat] : []) + ['sex' => $sex], 'D');
            }
        }

        return [
            ...$bySex,
            new KnoemaSeries('POP_TOTAL', 'إجمالي السكان (شهري)', 'Total population (monthly)', 'نسمة', 'M', 1000140, $count(), $region),
            new KnoemaSeries('POP_OMANI', 'السكان العُمانيون (شهري)', 'Omani population (monthly)', 'نسمة', 'M', 1000140, $count(), $region + ['nationality' => self::OMANI]),
            new KnoemaSeries('POP_EXPAT', 'السكان الوافدون (شهري)', 'Expatriate population (monthly)', 'نسمة', 'M', 1000140, $count(), $region + ['nationality' => self::EXPAT]),
            new KnoemaSeries('BIRTHS', 'المواليد (شهري)', 'Live births (monthly)', 'مولود', 'M', 1000020, $count(), $region),
            new KnoemaSeries('DEATHS', 'الوفيات (شهري)', 'Deaths (monthly)', 'حالة', 'M', 1000030, $count(), $region),
            new KnoemaSeries('LIFE_EXPECTANCY_NCSI', 'العمر المتوقع عند الولادة', 'Life expectancy at birth', 'سنة', 'A', 1000300, $rate(120), $region),
            new KnoemaSeries('FERTILITY_NCSI', 'معدل الخصوبة الكلي', 'Total fertility rate', 'مولود لكل امرأة', 'A', 1000310, $rate(15), $region),
            new KnoemaSeries('INFANT_MORTALITY_NCSI', 'وفيات الرضع', 'Infant mortality rate', 'لكل 1000 مولود', 'A', 1000330, $rate(500), $region),
        ];
    }
}
