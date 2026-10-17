<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartExplanation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartExplanationController extends Controller
{
    /** @return array<string, array<int, string>|string> */
    public static function rules(): array
    {
        return [
            'meaning' => ['required', 'string', 'min:10', 'max:1500'],
            'shows' => ['required', 'string', 'min:10', 'max:1500'],
            'read' => ['required', 'array', 'min:1', 'max:8'],
            'read.*' => ['required', 'string', 'min:3', 'max:600'],
            'why' => ['required', 'string', 'min:5', 'max:1000'],
            'terms' => ['nullable', 'array', 'max:10'],
            'terms.*.term' => ['required_with:terms', 'string', 'max:80'],
            'terms.*.definition' => ['required_with:terms', 'string', 'max:500'],
            'caution' => ['nullable', 'string', 'max:800'],
        ];
    }

    /** كل الشروحات المعدَّلة: عامة (نص الشرح غير حساس) فتحمَّل مرة وتُطبَّق على الرسوم. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => ChartExplanation::query()->pluck('explanation', 'chart_key')->all()])
            ->header('Cache-Control', 'no-cache');
    }

    public function upsert(Request $request): JsonResponse
    {
        $key = (string) $request->validate(['chart_key' => ['required', 'string', 'max:300']])['chart_key'];
        $explanation = $request->validate(self::rules());

        return response()->json(['data' => self::save($key, $explanation, $request->user()?->getAuthIdentifier() ? (int) $request->user()->getAuthIdentifier() : null, 'user')]);
    }

    /**
     * @param  array<string, mixed>  $explanation
     * @return array<string, mixed>
     */
    public static function save(string $key, array $explanation, ?int $userId, string $source): array
    {
        $explanation = array_filter($explanation, fn ($v) => $v !== null && $v !== []);
        DB::connection()->transaction(function () use ($key, $explanation, $userId, $source): void {
            ChartExplanation::updateOrCreate(['chart_key' => $key], ['explanation' => $explanation, 'edited_by' => $userId, 'updated_at' => now()]);
            DB::table('app.chart_explanation_revisions')->insert(['chart_key' => $key, 'explanation' => json_encode($explanation, JSON_UNESCAPED_UNICODE), 'edited_by' => $userId, 'source' => $source, 'created_at' => now()]);
        });

        return $explanation;
    }

    /** إعادة الشرح الافتراضي (حذف التعديل). */
    public function destroy(Request $request): JsonResponse
    {
        $key = (string) $request->validate(['chart_key' => ['required', 'string', 'max:300']])['chart_key'];
        ChartExplanation::where('chart_key', $key)->delete();

        return response()->json(['ok' => true]);
    }
}
