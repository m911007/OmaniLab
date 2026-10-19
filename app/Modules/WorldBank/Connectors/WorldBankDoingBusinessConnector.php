<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Validation\Rules\Range;

/**
 * مؤشرات سهولة ممارسة الأعمال (Doing Business) للبنك الدولي: الدرجة والترتيب الإجماليان و10 مجالات تنظيمية (بدء النشاط، الكهرباء، الائتمان، الضرائب،
 * التجارة عبر الحدود، إنفاذ العقود…) لنحو 190 دولة. أُوقف التقرير عام 2021 بعد مراجعة بيانات 2018–2020، فآخر بيانات متاحة تخص 2019–2020 وتُعرض
 * مرجعًا تاريخيًا لبيئة الأعمال التنظيمية لا قياسًا حاليًا؛ وحلّ محله تقرير B-READY الذي لم تدخله عُمان بعد. مصدر البنك الدولي رقم 1.
 */
final class WorldBankDoingBusinessConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.doing_business';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: سهولة ممارسة الأعمال (Doing Business، متوقف 2020)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: Doing Business (discontinued, to 2020)';
    }

    protected function section(): string
    {
        return 'macro';
    }

    protected function sourceId(): int
    {
        return 1;
    }

    protected function sourceTitle(): string
    {
        return 'Doing Business';
    }

    protected function lastYear(): int
    {
        return 2021;
    }

    protected function intervalMinutes(): int
    {
        return 30 * 24 * 60;
    }

    protected function indicators(): array
    {
        $score = fn () => [new Range(0, 100, 'reject')];
        $rank = fn () => [new Range(1, 250, 'reject')];

        return [
            new WbIndicator('IC.BUS.EASE.DFRN.XQ.DB1719', 'درجة سهولة ممارسة الأعمال (0–100)', 'Ease of doing business score (0-100)', 'درجة 0–100', $score()),
            new WbIndicator('IC.BUS.EASE.XQ', 'ترتيب سهولة ممارسة الأعمال (1 = الأفضل)', 'Ease of doing business rank (1 = best)', 'مرتبة', $rank()),
            new WbIndicator('IC.REG.STRT.BUS.DFRN', 'درجة بدء النشاط التجاري (0–100)', 'Starting a business — score', 'درجة 0–100', $score()),
            new WbIndicator('IC.REG.STRT.BUS.RK.DB19', 'ترتيب بدء النشاط التجاري', 'Starting a business — rank', 'مرتبة', $rank()),
            new WbIndicator('IC.CNST.PRMT.DFRN.DB1619', 'درجة تراخيص البناء (0–100)', 'Dealing with construction permits — score', 'درجة 0–100', $score()),
            new WbIndicator('IC.CNST.PRMT.RK', 'ترتيب تراخيص البناء', 'Dealing with construction permits — rank', 'مرتبة', $rank()),
            new WbIndicator('IC.ELC.ACES.DFRN.DB1619', 'درجة الحصول على الكهرباء (0–100)', 'Getting electricity — score', 'درجة 0–100', $score()),
            new WbIndicator('IC.ELC.ACES.RK.DB19', 'ترتيب الحصول على الكهرباء', 'Getting electricity — rank', 'مرتبة', $rank()),
            new WbIndicator('IC.REG.PRRT.DFRN.DB1719', 'درجة تسجيل الملكية (0–100)', 'Registering property — score', 'درجة 0–100', $score()),
            new WbIndicator('IC.REG.PRRT.REG.RK.DB19', 'ترتيب تسجيل الملكية', 'Registering property — rank', 'مرتبة', $rank()),
            new WbIndicator('IC.CRED.ACC.CRD.DB1519.DFRN', 'درجة الحصول على الائتمان (0–100)', 'Getting credit — score', 'درجة 0–100', $score()),
            new WbIndicator('IC.CRED.ACC.CRD.RK', 'ترتيب الحصول على الائتمان', 'Getting credit — rank', 'مرتبة', $rank()),
            new WbIndicator('PROT.MINOR.INV.DFRN.DB1519', 'درجة حماية صغار المستثمرين (0–100)', 'Protecting minority investors — score', 'درجة 0–100', $score()),
            new WbIndicator('PROT.MINOR.INV.IC.PRIN.MINOR.RK', 'ترتيب حماية صغار المستثمرين', 'Protecting minority investors — rank', 'مرتبة', $rank()),
            new WbIndicator('PAY.TAX.DB1719.DRFN', 'درجة دفع الضرائب (0–100)', 'Paying taxes — score', 'درجة 0–100', $score()),
            new WbIndicator('PAY.TAX.RK.DB19', 'ترتيب دفع الضرائب', 'Paying taxes — rank', 'مرتبة', $rank()),
            new WbIndicator('TRD.ACRS.BRDR.DB1619.DFRN', 'درجة التجارة عبر الحدود (0–100)', 'Trading across borders — score', 'درجة 0–100', $score()),
            new WbIndicator('TRD.ACRS.BRDR.RK.DB19', 'ترتيب التجارة عبر الحدود', 'Trading across borders — rank', 'مرتبة', $rank()),
            new WbIndicator('ENF.CONT.COEN.DB1719.DFRN', 'درجة إنفاذ العقود (0–100)', 'Enforcing contracts — score', 'درجة 0–100', $score()),
            new WbIndicator('ENF.CONT.COEN.RK.DB19', 'ترتيب إنفاذ العقود', 'Enforcing contracts — rank', 'مرتبة', $rank()),
            new WbIndicator('RESLV.ISV.DB1519.DFRN', 'درجة تسوية حالات الإعسار (0–100)', 'Resolving insolvency — score', 'درجة 0–100', $score()),
            new WbIndicator('RESLV.ISV.RK.DB19', 'ترتيب تسوية حالات الإعسار', 'Resolving insolvency — rank', 'مرتبة', $rank()),
        ];
    }
}
