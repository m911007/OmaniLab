<?php

namespace App\Core\Insights;

use App\Http\Resources\DatasetResource;
use App\Models\Indicator;
use Illuminate\Support\Facades\DB;

/**
 * يبني قراءات المؤشرات (config/indicator_meta.php) من core.observations: آخر قيمة فعلية، والسابقة المقارنة،
 * وموقعها في آخر عشر سنوات، وترتيبها بين دول الخليج، ثم الحكم والجملة عبر Reading.
 */
final class ReadingBuilder
{
    /**
     * @param  list<string>  $keys
     * @param  array{max_period?: string|null, polarity?: string|null, omr?: bool}  $options  خيارات العرض من الصفحة: آخر فترة فعلية، والاتجاه المرغوب، والتحويل إلى الريال
     * @return list<array<string, mixed>>
     */
    public function build(array $keys, ?string $entity = null, array $options = []): array
    {
        /** @var array<string, mixed> $config */
        $config = config('indicator_meta');
        $entity ??= (string) $config['entity'];
        $polarities = $this->scorecardPolarities();
        $indicators = Indicator::whereIn('key', $keys)->with('dataset.source')->get()->keyBy('key');

        $out = [];
        foreach ($keys as $key) {
            $indicator = $indicators->get($key);
            if (! $indicator) {
                continue;
            }
            /** @var array<string, mixed> $meta */
            $meta = (array) ($config['indicators'][$key] ?? []);
            $meta['polarity'] = $options['polarity'] ?? $meta['polarity'] ?? $polarities[$key] ?? null;
            // التكبير الآلي (مليون/مليار) إلا إن حدّد الإعداد مقياسًا صريحًا
            $meta['auto_scale'] = ! isset($meta['scale']);
            if (! empty($options['omr']) && ! isset($meta['scale'])) {
                $meta['scale'] = 0.3845;
                $meta['unit'] = (string) preg_replace('/دولار( دولي)?/u', 'ريال عُماني', (string) ($meta['unit'] ?? $indicator->unit ?? ''));
            }
            if (! empty($options['max_period'])) {
                $meta['max_period'] = $options['max_period'];
            }
            $reading = $this->indicator($indicator, $meta, $entity, (array) $config['peers'], (int) $config['history_years']);
            if ($reading !== null) {
                $out[] = $reading;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $peers
     * @return array<string, mixed>|null
     */
    private function indicator(Indicator $indicator, array $meta, string $entity, array $peers, int $historyYears): ?array
    {
        $scale = (float) ($meta['scale'] ?? 1.0);
        $polarity = isset($meta['polarity']) ? (string) $meta['polarity'] : null;
        $maxPeriod = $meta['max_period'] ?? (($meta['forecast'] ?? false) ? (string) ((int) date('Y') - 1) : null);

        $series = $this->series($indicator->id, $entity, $maxPeriod, $scale);
        if ($series === []) {
            return null;
        }
        $unit = (string) ($meta['unit'] ?? $indicator->unit ?? '');
        // الأرقام الضخمة بلا وحدة مكبّرة تُعرض بالمليون أو المليار (21,210,000,000 ريال ← 21.21 مليار ريال)
        $factor = 1.0;
        if (($meta['auto_scale'] ?? ! isset($meta['scale'])) && ! preg_match('/مليون|مليار|ألف|%/u', $unit)) {
            $last = abs((float) end($series));
            [$factor, $prefix] = $last >= 1e9 ? [1e-9, 'مليار '] : ($last >= 1e6 ? [1e-6, 'مليون '] : [1.0, '']);
            if ($factor !== 1.0) {
                $series = array_map(fn (float $v) => $v * $factor, $series);
                $unit = $prefix.$unit;
            }
        }
        // مفاتيح السنوات («2024») تتحول في PHP إلى أعداد صحيحة؛ نعيدها نصوصًا
        $periods = array_map('strval', array_keys($series));
        $period = (string) end($periods);
        $value = $series[$period];
        $frequency = match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) => 'D',
            (bool) preg_match('/^\d{4}-\d{2}$/', $period) => 'M',
            str_contains($period, 'Q') => 'Q',
            default => 'A',
        };
        $rate = str_starts_with($unit, '%');

        // المستويات الشهرية والفصلية تُقارن بالفترة نفسها من العام السابق (تتجنب الموسمية)، والنسب بالفترة السابقة مباشرة
        $previousPeriod = in_array($frequency, ['M', 'Q'], true) && ! $rate ? (string) ((int) substr($period, 0, 4) - 1).substr($period, 4) : ($periods[count($periods) - 2] ?? null);
        $previous = $previousPeriod !== null && isset($series[$previousPeriod]) ? ['period' => $previousPeriod, 'value' => $series[$previousPeriod]] : null;

        $from = (int) substr($period, 0, 4) - $historyYears;
        $past = array_values(array_filter($series, fn (string $p) => (int) substr($p, 0, 4) >= $from && $p !== $period, ARRAY_FILTER_USE_KEY));
        $history = count($past) >= 4 ? [
            'years' => $historyYears,
            'mean' => array_sum($past) / count($past),
            'min' => min($past),
            'max' => max($past),
            'percentile' => $polarity !== null ? Reading::historyPercentile($value, $past, $polarity) : null,
        ] : null;

        // الترتيب الخليجي لعُمان فقط (مؤشرات العالم أو دولة أخرى لا تُقارن بالخليج هنا)
        $gcc = $frequency === 'A' && $entity === (string) config('indicator_meta.entity') ? $this->gcc($indicator->id, $entity, $peers, (int) $period, $value, $scale * $factor, $polarity ?? 'higher') : null;

        $peerJudge = ($meta['peer_judge'] ?? true) && $polarity !== null;
        $verdict = $polarity === null ? null : Reading::verdict($history['percentile'] ?? null, $peerJudge && $gcc !== null ? $gcc['percentile'] : null);

        $digits = (int) ($meta['digits'] ?? (abs($value) >= 1000 ? 0 : (abs($value) >= 10 ? 1 : 2)));
        $label = (string) ($meta['label'] ?? $indicator->name_ar);
        $sentence = Reading::sentence(
            ['label' => $label, 'unit' => $unit, 'digits' => $digits, 'polarity' => $polarity, 'rate' => $rate],
            ['period' => $period, 'value' => $value],
            $previous,
            $gcc === null ? null : ['rank' => $gcc['rank'], 'of' => $gcc['of'], 'mean' => $gcc['mean']],
            $history === null ? null : ['years' => $history['years'], 'mean' => $history['mean'], 'percentile' => $history['percentile']],
        );

        return [
            'key' => $indicator->key,
            'label' => $label,
            'unit' => $unit,
            'digits' => $digits,
            'polarity' => $polarity,
            'frequency' => $frequency,
            'period' => $period,
            'period_label' => Reading::periodLabel($period),
            'value' => $value,
            'previous' => $previous === null ? null : $previous + ['period_label' => Reading::periodLabel($previous['period'])],
            'history' => $history,
            'gcc' => $gcc,
            'reference' => $meta['reference'] ?? null,
            'verdict' => $verdict,
            'sentence' => $sentence,
            'spark' => array_map(fn (string $p) => [$p, $series[$p]], array_slice($periods, match ($frequency) {
                'D' => -90, 'M' => -36, 'Q' => -16, default => -12
            })),
            'dataset' => (new DatasetResource($indicator->dataset))->resolve(),
        ];
    }

    /** @return array<string, float> فترة => قيمة (بعد التحويل)، مرتّبة زمنيًا */
    private function series(int $indicatorId, string $entity, ?string $maxPeriod, float $scale): array
    {
        $rows = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.indicator_id', $indicatorId)->where('e.code', $entity)
            // «2025~» يشمل كل فترات 2025 (الشهرية والفصلية) لأن «~» بعد الأرقام والشرطة في الترتيب
            ->when($maxPeriod !== null, fn ($q) => $q->where('o.period', '<=', $maxPeriod.'~'))
            ->orderBy('o.period')->get(['o.period', 'o.value']);

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->period] = (float) $r->value * $scale;
        }

        return $out;
    }

    /**
     * ترتيب الكيان بين دول الخليج في سنة القراءة (أو التي قبلها لمن تأخر نشره).
     *
     * @param  list<string>  $peers
     * @return array{rank: int, of: int, percentile: float, mean: float, items: list<array{code: string, value: float}>}|null
     */
    private function gcc(int $indicatorId, string $entity, array $peers, int $year, float $value, float $scale, string $polarity): ?array
    {
        $rows = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.indicator_id', $indicatorId)->whereIn('e.code', $peers)
            ->whereIn('o.period', [(string) $year, (string) ($year - 1)])
            ->orderByDesc('o.period')->get(['e.code', 'o.value']);
        $values = [];
        foreach ($rows as $r) {
            $values[$r->code] ??= (float) $r->value * $scale;
        }
        if (count($values) < 3) {
            return null;
        }
        $mean = array_sum($values) / count($values);
        $values[$entity] = $value;
        $position = Standing::position($entity, $values, $polarity);
        if ($position === null) {
            return null;
        }
        $items = array_map(fn (string $code) => ['code' => $code, 'value' => $values[$code]], array_keys($values));
        usort($items, fn (array $a, array $b) => Standing::orient($b['value'], $polarity) <=> Standing::orient($a['value'], $polarity));

        return ['rank' => $position['rank'], 'of' => $position['of'], 'percentile' => $position['percentile'], 'mean' => $mean, 'items' => $items];
    }

    /** @return array<string, string> */
    private function scorecardPolarities(): array
    {
        $out = [];
        foreach ((array) config('scorecard.themes') as $theme) {
            foreach ($theme['indicators'] as $spec) {
                $out[$spec['key']] = (string) $spec['polarity'];
            }
        }

        return $out;
    }
}
