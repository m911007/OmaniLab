<?php

namespace App\Core\Insights;

use App\Http\Resources\DatasetResource;
use App\Models\Indicator;
use Illuminate\Support\Facades\DB;

/**
 * مطابقة المصادر: لكل قياس تنشره عدة مصادر (config/crosschecks.php) يقارن قيم الفترات المشتركة الأخيرة بعد توحيد الوحدات،
 * ويحكم: agree (متطابقة ضمن التسامح) أو differ (مختلفة) أو single (مصدر واحد فقط ببيانات). لا يدمج ولا يختار مصدرًا عن القارئ:
 * يعرض الاختلاف ويدع الاختيار له.
 */
final class CrossCheck
{
    /**
     * فهرس المجموعات وحكم كل منها لكيان.
     *
     * @return list<array<string, mixed>>
     */
    public function index(string $entity): array
    {
        $keys = [];
        foreach ((array) config('crosschecks.groups') as $group) {
            array_push($keys, ...array_column($group['members'], 'indicator'));
        }
        $indicators = Indicator::whereIn('key', $keys)->with('dataset.source')->get()->keyBy('key');

        $out = [];
        foreach ((array) config('crosschecks.groups') as $group) {
            $series = $this->series($group, $entity);
            $verdict = $this->compare($group, $series);
            $members = [];
            foreach ($group['members'] as $m) {
                $ind = $indicators->get($m['indicator']);
                $source = $ind?->dataset->source;
                $members[] = ['indicator' => $m['indicator'], 'has_data' => isset($series[$m['indicator']]), 'source' => $source ? ['key' => $source->key, 'name_ar' => $source->name_ar, 'url' => $source->url] : null];
            }
            $out[] = ['key' => $group['key'], 'label' => $group['label'], 'verdict' => $verdict['verdict'], 'indicators' => array_column($group['members'], 'indicator'), 'members' => $members];
        }

        return $out;
    }

    /**
     * تفصيل المجموعة التي تضم المؤشر: سلسلة كل مصدر بوحدة المؤشر المطلوب، ومقارنة الفترات المشتركة.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $indicatorKey, string $entity): ?array
    {
        foreach ((array) config('crosschecks.groups') as $group) {
            $keys = array_column($group['members'], 'indicator');
            if (! in_array($indicatorKey, $keys, true)) {
                continue;
            }
            $baseScale = (float) $group['members'][array_search($indicatorKey, $keys, true)]['scale'];
            $series = $this->series($group, $entity);
            $comparison = $this->compare($group, $series);

            $indicators = Indicator::whereIn('key', $keys)->with('dataset.source')->get()->keyBy('key');
            $sources = [];
            foreach ($group['members'] as $m) {
                $ind = $indicators->get($m['indicator']);
                if (! $ind || empty($series[$m['indicator']])) {
                    continue;
                }
                $sources[] = [
                    'indicator' => $m['indicator'], 'name_ar' => $ind->name_ar, 'unit' => $ind->unit, 'is_base' => $m['indicator'] === $indicatorKey,
                    'dataset' => (new DatasetResource($ind->dataset))->resolve(),
                    'points' => array_map(fn (array $p) => [$p[0], $p[1] / $baseScale], $series[$m['indicator']]),
                ];
            }

            return ['group' => ['key' => $group['key'], 'label' => $group['label'], 'mode' => $group['mode'], 'tolerance' => $group['tolerance']], 'verdict' => $comparison['verdict'], 'periods' => $comparison['periods'], 'sources' => $sources];
        }

        return null;
    }

    /**
     * سلاسل الأعضاء بوحدة المجموعة المعيارية (مضروبة في scale).
     *
     * @param  array<string, mixed>  $group
     * @return array<string, list<array{0: string, 1: float}>>
     */
    private function series(array $group, string $entity): array
    {
        $out = [];
        foreach ($group['members'] as $m) {
            $rows = DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
                ->where('i.key', $m['indicator'])->where('e.code', $entity)->whereRaw("o.period ~ '^[0-9]{4}$'")
                ->orderBy('o.period')->get(['o.period', 'o.value']);
            if ($rows->isNotEmpty()) {
                $out[$m['indicator']] = $rows->map(fn ($r) => [(string) $r->period, (float) $r->value * (float) $m['scale']])->all();
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, list<array{0: string, 1: float}>>  $series  بوحدة المجموعة المعيارية
     * @return array{verdict: string, periods: list<array<string, mixed>>}
     */
    private function compare(array $group, array $series): array
    {
        if (count($series) < 2) {
            return ['verdict' => 'single', 'periods' => []];
        }
        $lastActual = (int) date('Y') - 1;
        $byPeriod = [];
        foreach ($series as $indicator => $points) {
            foreach ($points as [$period, $value]) {
                if ((int) $period <= $lastActual) {
                    $byPeriod[$period][$indicator] = $value;
                }
            }
        }
        $byPeriod = array_filter($byPeriod, fn (array $v) => count($v) >= 2);
        krsort($byPeriod);
        $byPeriod = array_slice($byPeriod, 0, (int) config('crosschecks.compare_periods'), true);
        if ($byPeriod === []) {
            return ['verdict' => 'single', 'periods' => []];
        }

        $periods = [];
        $agree = true;
        foreach ($byPeriod as $period => $values) {
            $min = min($values);
            $max = max($values);
            $sorted = array_values($values);
            sort($sorted);
            $median = $sorted[intdiv(count($sorted), 2)];
            $spread = $group['mode'] === 'relative' ? ($median != 0.0 ? ($max - $min) / abs($median) : 0.0) : $max - $min;
            $ok = $spread <= (float) $group['tolerance'];
            $agree = $agree && $ok;
            $periods[] = ['period' => (string) $period, 'values' => $values, 'spread' => $spread, 'agree' => $ok];
        }

        return ['verdict' => $agree ? 'agree' : 'differ', 'periods' => $periods];
    }
}
