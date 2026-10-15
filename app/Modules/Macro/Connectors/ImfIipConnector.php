<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\SeriesSpike;

/**
 * مركز الاستثمار الدولي (IIP): أصول الدولة في الخارج والتزاماتها تجاه الخارج وصافيها، بمليارات الدولارات، سنويًا لكل الدول.
 * الصافي (أصول − التزامات) يُحسب هنا من القيمتين المنشورتين لنفس الدولة والسنة، ولا يُنشر في المصدر مباشرة.
 */
final class ImfIipConnector extends ImfSdmxConnector
{
    protected function flow(): string
    {
        return 'IIP';
    }

    protected function key(): string
    {
        return '.A_P+L_P.IIP.USD.A';
    }

    /** @return list<string> */
    protected function codeColumns(): array
    {
        return ['BOP_ACCOUNTING_ENTRY'];
    }

    protected function datasetKey(): string
    {
        return 'imf.iip';
    }

    protected function datasetNameAr(): string
    {
        return 'مركز الاستثمار الدولي (IMF IIP)';
    }

    protected function datasetNameEn(): string
    {
        return 'International investment position (IMF IIP)';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function indicators(): array
    {
        $rules = [new Range(0, 1e5, 'reject'), new SeriesSpike(100)];

        return [
            'A_P' => new IndicatorDefinition('imf.iip_assets', 'الأصول الأجنبية', 'External assets (IIP)', 'مليار دولار', 'A', $rules),
            'L_P' => new IndicatorDefinition('imf.iip_liabilities', 'الالتزامات تجاه الخارج', 'External liabilities (IIP)', 'مليار دولار', 'A', $rules),
        ];
    }

    public function definition(): DatasetDefinition
    {
        $def = parent::definition();

        return new DatasetDefinition(
            $def->key, $def->source, $def->nameAr, $def->nameEn, $def->section, $def->intervalMinutes,
            [...$def->indicators, new IndicatorDefinition('imf.iip_net', 'صافي مركز الاستثمار الدولي', 'Net international investment position', 'مليار دولار', 'A', [new Range(-1e5, 1e5, 'reject')])],
            $def->reliability,
        );
    }

    public function parse(RawPayload $payload): iterable
    {
        $assets = [];
        $liabilities = [];
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $code, $period, $value]) {
            if (! preg_match('/^[A-Z]{3}$/', $iso) || ! preg_match('/^\d{4}$/', $period) || ! is_numeric($value)) {
                continue;
            }
            $billions = (float) $value / 1e9;
            if ($code === 'A_P') {
                $assets["{$iso}|{$period}"] = $billions;
                yield new ParsedRecord('imf.iip_assets', $iso, $period, $billions);
            } elseif ($code === 'L_P') {
                $liabilities["{$iso}|{$period}"] = $billions;
                yield new ParsedRecord('imf.iip_liabilities', $iso, $period, $billions);
            }
        }
        foreach ($assets as $key => $a) {
            if (isset($liabilities[$key])) {
                [$iso, $period] = explode('|', $key);
                yield new ParsedRecord('imf.iip_net', $iso, $period, $a - $liabilities[$key]);
            }
        }
    }
}
