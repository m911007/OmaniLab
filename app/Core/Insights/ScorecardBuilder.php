<?php

namespace App\Core\Insights;

use App\Http\Resources\DatasetResource;
use App\Models\Indicator;
use Illuminate\Support\Facades\DB;

/**
 * يبني بطاقة أداء عُمان: لكل مؤشر في config/scorecard.php يقارن آخر قيمة فعلية بدول العالم والخليج وبقيمتها قبل خمس سنوات،
 * ثم يصنّفها (قوة/تحدٍّ/فرصة/مراقبة). كل الأرقام من core.observations؛ لا تقدير ولا استنتاج خارج البيانات.
 */
final class ScorecardBuilder
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('scorecard');
        $entity = (string) $config['entity'];
        $peers = array_values((array) $config['peers']);
        $lastActual = (int) date('Y') - 1;

        $keys = array_merge(...array_values(array_map(fn (array $t) => array_column($t['indicators'], 'key'), $config['themes'])));
        $indicators = Indicator::whereIn('key', $keys)->with('dataset.source')->get()->keyBy('key');

        $items = [];
        $themes = [];
        foreach ($config['themes'] as $themeKey => $theme) {
            $themes[$themeKey] = ['key' => $themeKey, 'label' => $theme['label'], 'count' => 0, 'percentile' => null];
            $sum = 0.0;
            foreach ($theme['indicators'] as $spec) {
                $indicator = $indicators->get($spec['key']);
                if (! $indicator) {
                    continue;
                }
                $item = $this->indicator($indicator, $spec, $entity, $peers, ($spec['forecast'] ?? false) ? $lastActual : null, (int) $config['baseline_years'], (int) $config['window_years'], (int) $config['max_age_years']);
                if ($item === null) {
                    continue;
                }
                $items[] = ['theme' => $themeKey] + $item;
                $themes[$themeKey]['count']++;
                $sum += $item['world']['percentile'];
            }
            if ($themes[$themeKey]['count'] > 0) {
                $themes[$themeKey]['percentile'] = round($sum / $themes[$themeKey]['count'], 1);
            }
        }

        $counts = array_count_values(array_column($items, 'class'));

        return [
            'entity' => $entity,
            'peers' => $peers,
            'thresholds' => ['strong_from' => Standing::STRONG_FROM, 'weak_until' => Standing::WEAK_UNTIL, 'baseline_years' => (int) $config['baseline_years']],
            'counts' => $counts,
            'themes' => array_values($themes),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<string>  $peers
     * @return array<string, mixed>|null
     */
    private function indicator(Indicator $indicator, array $spec, string $entity, array $peers, ?int $maxYear, int $baselineYears, int $windowYears, int $maxAge): ?array
    {
        $polarity = (string) $spec['polarity'];

        $latest = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.indicator_id', $indicator->id)->where('e.code', $entity)->whereRaw("o.period ~ '^[0-9]{4}$'")
            ->when($maxYear !== null, fn ($q) => $q->where('o.period', '<=', (string) $maxYear))
            ->orderByDesc('o.period')->first(['o.period', 'o.value']);
        if (! $latest) {
            return null;
        }
        $year = (int) $latest->period;
        if ($year < (int) date('Y') - $maxAge) {
            return null;
        }

        $rows = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.indicator_id', $indicator->id)->where('e.type', 'country')
            ->whereBetween('o.period', [(string) ($year - $windowYears), (string) $year])->whereRaw("o.period ~ '^[0-9]{4}$'")
            ->orderBy('o.entity_id')->orderByDesc('o.period')
            ->get(['e.code', 'o.period', 'o.value']);
        /** @var array<string, float> $values */
        $values = [];
        foreach ($rows as $r) {
            $values[$r->code] ??= (float) $r->value;
        }
        $values[$entity] = (float) $latest->value;

        $world = Standing::position($entity, $values, $polarity);
        if ($world === null) {
            return null;
        }

        $base = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.indicator_id', $indicator->id)->where('e.code', $entity)->whereRaw("o.period ~ '^[0-9]{4}$'")
            ->whereBetween('o.period', [(string) ($year - $baselineYears - 1), (string) ($year - $baselineYears + 1)])
            ->orderByRaw('abs(o.period::int - ?)', [$year - $baselineYears])->first(['o.period', 'o.value']);
        $baseline = $base ? ['period' => $base->period, 'value' => (float) $base->value] : null;

        $trend = Standing::trend((float) $latest->value, $baseline['value'] ?? null, array_values($values), $polarity);

        $gccValues = array_filter($values, fn (string $code) => $code === $entity || in_array($code, $peers, true), ARRAY_FILTER_USE_KEY);
        $gccPosition = Standing::position($entity, $gccValues, $polarity);
        $gccItems = [];
        foreach ($gccValues as $code => $v) {
            $pos = Standing::position($code, $values, $polarity);
            $gccItems[] = ['code' => $code, 'value' => $v, 'percentile' => $pos['percentile'] ?? null];
        }
        usort($gccItems, fn (array $a, array $b) => Standing::orient($b['value'], $polarity) <=> Standing::orient($a['value'], $polarity));

        $sorted = array_values($values);
        sort($sorted);
        $n = count($sorted);
        $median = $n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;

        return [
            'key' => $indicator->key,
            'name_ar' => $indicator->name_ar,
            'unit' => $indicator->unit,
            'polarity' => $polarity,
            'period' => $latest->period,
            'value' => (float) $latest->value,
            'baseline' => $baseline,
            'trend' => $trend,
            'class' => Standing::classify($world['percentile'], $trend),
            'world' => $world + ['median' => $median],
            'gcc' => $gccPosition === null ? null : ['rank' => $gccPosition['rank'], 'of' => $gccPosition['of'], 'best' => $gccItems[0] ?? null, 'items' => $gccItems],
            'dataset' => (new DatasetResource($indicator->dataset))->resolve(),
        ];
    }
}
