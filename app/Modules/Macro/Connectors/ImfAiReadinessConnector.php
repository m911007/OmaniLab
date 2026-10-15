<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** مؤشر الجاهزية للذكاء الاصطناعي (AI Preparedness Index) لصندوق النقد: المؤشر المركّب وأركانه الأربعة. */
final class ImfAiReadinessConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.ai_readiness';
    }

    protected function datasetNameAr(): string
    {
        return 'الجاهزية للذكاء الاصطناعي (IMF AI Preparedness Index)';
    }

    protected function datasetNameEn(): string
    {
        return 'IMF AI Preparedness Index';
    }

    protected function section(): string
    {
        return 'digital';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        $index = fn () => [new Range(0, 1), new Completeness(0.2)];

        return [
            new IndicatorDefinition('imf.AI_PI', 'مؤشر الجاهزية للذكاء الاصطناعي', 'AI Preparedness Index', '0–1', rules: $index()),
            new IndicatorDefinition('imf.DI', 'الجاهزية: البنية التحتية الرقمية', 'AI readiness: digital infrastructure', '0–1', rules: $index()),
            new IndicatorDefinition('imf.IEI', 'الجاهزية: الابتكار والتكامل الاقتصادي', 'AI readiness: innovation and economic integration', '0–1', rules: $index()),
            new IndicatorDefinition('imf.HCLMP', 'الجاهزية: رأس المال البشري وسياسات سوق العمل', 'AI readiness: human capital and labour policies', '0–1', rules: $index()),
            new IndicatorDefinition('imf.RE', 'الجاهزية: التنظيم والأخلاقيات', 'AI readiness: regulation and ethics', '0–1', rules: $index()),
        ];
    }
}
