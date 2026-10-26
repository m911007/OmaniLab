<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Validation\Rules\Range;

/**
 * مؤشر الأداء اللوجستي (LPI) للبنك الدولي: ستة مكوّنات (الدرجة الكلية في مجموعة المالية) (الجمارك، البنية التحتية، الشحن الدولي، كفاءة الخدمات اللوجستية، التتبع، الالتزام بالمواعيد)
 * من 1 إلى 5، ومدة التصدير والاستيراد بالأيام. مسوح كل سنتين تقريبًا (آخر إصدار 2023)، وتهم موانئ عُمان ودورها اللوجستي.
 */
final class WorldBankLogisticsConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.logistics';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: مؤشر الأداء اللوجستي';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: Logistics Performance Index';
    }

    protected function section(): string
    {
        return 'trade';
    }

    protected function intervalMinutes(): int
    {
        return 30 * 24 * 60;
    }

    protected function indicators(): array
    {
        $lpi = fn () => [new Range(1, 5, 'reject')];
        $days = fn () => [new Range(0, 120, 'reject')];

        return [
            new WbIndicator('LP.LPI.CUST.XQ', 'اللوجستي: كفاءة التخليص الجمركي (1–5)', 'LPI: customs clearance efficiency', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.LPI.INFR.XQ', 'اللوجستي: جودة البنية التحتية للتجارة والنقل (1–5)', 'LPI: trade and transport infrastructure', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.LPI.ITRN.XQ', 'اللوجستي: سهولة ترتيب الشحن الدولي (1–5)', 'LPI: international shipments', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.LPI.LOGS.XQ', 'اللوجستي: كفاءة الخدمات اللوجستية (1–5)', 'LPI: logistics competence and quality', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.LPI.TRAC.XQ', 'اللوجستي: التتبع (1–5)', 'LPI: tracking and tracing', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.LPI.TIME.XQ', 'اللوجستي: الالتزام بمواعيد الشحن (1–5)', 'LPI: timeliness', 'درجة 1–5', $lpi()),
            new WbIndicator('LP.EXP.DURS.MD', 'مدة التصدير (الوسيط)', 'Lead time to export, median', 'يوم', $days()),
            new WbIndicator('LP.IMP.DURS.MD', 'مدة الاستيراد (الوسيط)', 'Lead time to import, median', 'يوم', $days()),
        ];
    }
}
