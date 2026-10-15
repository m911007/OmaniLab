<?php

namespace App\Modules\WorldBank\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;

/** مؤشرات الحوكمة العالمية (WGI): ست أبعاد بتقدير (−2.5…+2.5) ودرجة مئوية (0–100). مصدر البنك الدولي رقم 3. */
final class WorldBankGovernanceConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.governance';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: مؤشرات الحوكمة العالمية (WGI)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: Worldwide Governance Indicators';
    }

    protected function section(): string
    {
        return 'governance';
    }

    protected function sourceId(): int
    {
        return 3;
    }

    protected function sourceTitle(): string
    {
        return 'Worldwide Governance Indicators';
    }

    protected function intervalMinutes(): int
    {
        return 7 * 24 * 60;
    }

    protected function indicators(): array
    {
        $dims = [
            'VA' => ['الصوت والمساءلة', 'Voice and accountability'],
            'PV' => ['الاستقرار السياسي وغياب العنف', 'Political stability'],
            'GE' => ['فاعلية الحكومة', 'Government effectiveness'],
            'RQ' => ['جودة التنظيم', 'Regulatory quality'],
            'RL' => ['سيادة القانون', 'Rule of law'],
            'CC' => ['السيطرة على الفساد', 'Control of corruption'],
        ];

        $out = [];
        foreach ($dims as $code => [$ar, $en]) {
            $out[] = new WbIndicator("GOV_WGI_{$code}.EST", "{$ar} (تقدير)", "{$en} — estimate", '−2.5…+2.5',
                [new Range(-4, 4), new MaxRevision(0.5, 0.25), new Completeness(0.2)]);
            $out[] = new WbIndicator("GOV_WGI_{$code}.SC", "{$ar} (درجة)", "{$en} — score", '0–100',
                [new Range(0, 100), new MaxRevision(10.0, 0.25), new Completeness(0.2)]);
        }

        return $out;
    }
}
