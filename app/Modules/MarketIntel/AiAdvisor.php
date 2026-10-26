<?php

namespace App\Modules\MarketIntel;

use App\Core\Support\Secrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * طبقة الذكاء الاصطناعي لذكاء الأسواق: تستلم حزمة أدلة مُحتسبة من بيانات النظام (لا تبحث ولا تخمّن أرقامًا من عندها)
 * وتكتب منها توصية للمشتري أو المصدّر بالعربية، وتجيب عن أسئلة المستخدم عن المنتج نفسه.
 * بلا مفتاح (ANTHROPIC_API_KEY) أو عند فشل الخدمة تُرجع تحليلًا آليًا حتميًا بنفس البنية، ويُعلَّم المصدر في الرد فلا يُنسب لذكاء اصطناعي ما لم يكتبه.
 */
final class AiAdvisor
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    public function enabled(): bool
    {
        return Secrets::has('anthropic_api_key');
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{source: 'ai'|'rules', summary: string, recommendations: list<string>, risks: list<string>, next_steps: list<string>, model: ?string}
     */
    public function brief(array $evidence): array
    {
        if ($this->enabled()) {
            $cacheKey = 'mi:brief:v1:'.md5(json_encode($evidence));
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
            try {
                $out = $this->aiBrief($evidence);
                Cache::put($cacheKey, $out, 6 * 3600);

                return $out;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->rulesBrief($evidence);
    }

    /** @param  array<string, mixed>  $evidence */
    public function ask(array $evidence, string $question): string
    {
        $text = $this->call(
            'أنت محلل أسواق وتجارة خارجية يخدم متخذ قرار في سلطنة عُمان. أجب بالعربية بإيجاز ودقة اعتمادًا على الأدلة المرفقة فقط (JSON). لا تذكر رقمًا غير موجود فيها؛ وإن لم تكفِ الأدلة فقل ما الذي ينقص. القيم بالدولار الأمريكي وسعر الوحدة بالدولار للطن.',
            "الأدلة:\n".json_encode($evidence, JSON_UNESCAPED_UNICODE)."\n\nالسؤال: {$question}",
            900,
        );

        return trim($text);
    }

    /**
     * منتج بوصف حر (أي منتج) إلى رموز HS4 مرشَّحة؛ الذكاء الاصطناعي يقترح فقط ويُتحقَّق من الرموز لاحقًا مقابل قائمة BACI.
     *
     * @return list<string>
     */
    public function resolveHs(string $text): array
    {
        $out = $this->call('أنت خبير في النظام المنسّق (HS). أعد JSON فقط: {"hs4":["رمز من 4 أرقام",...]} بأفضل 5 رموز HS4 تطابق وصف المنتج، الأدق أولًا. لا تضف أي نص آخر.', "وصف المنتج: {$text}", 300);
        if (! preg_match('/\{.*\}/s', $out, $m)) {
            return [];
        }
        $j = json_decode($m[0], true);

        return array_values(array_filter(array_map('strval', is_array($j['hs4'] ?? null) ? $j['hs4'] : []), fn ($c) => preg_match('/^\d{4}$/', $c) === 1));
    }

    /** @param  array<string, mixed>  $evidence */
    private function aiBrief(array $evidence): array
    {
        $system = "أنت محلل أسواق وتجارة خارجية يخدم متخذ قرار في سلطنة عُمان (مشتري أو مصدّر). تصلك أدلة محسوبة بصيغة JSON عن منتج واحد: مرشَّحون (موردون أو أسواق) بدرجات 0–100 للحجم والنمو والسعر واللوجستيات والمخاطر، وملخص (تركّز السوق HHI، سعر وحدة عُمان مقابل الوسيط، وفر مقدَّر)، وإشارات محسوبة (فرص ومخاطر)، واتجاه تجارة عُمان والعالم 10 سنوات، والميزة النسبية RCA، وتعقيد المنتج PCI، والرسوم الجمركية واتفاقيات التجارة الحرة، وأسعار السلعة الشهرية إن وُجدت.\n\n"
            .'قواعد صارمة: 1) استخدم الأرقام الموجودة في الأدلة فقط ولا تخترع رقمًا أو دولة. 2) اذكر الدول بأسمائها العربية. 3) ميّز التقدير من الحقيقة، ونبّه إلى أن البيانات سنوية (BACI) وأن أسعار الوحدة تقريبية (قيمة ÷ كمية) وقد تخفي اختلاف الجودة. 4) لا تَعِد بنتيجة. 5) ابدأ من الإشارات الأعلى أثرًا واربطها بالأرقام، وقدّم توصيات قابلة للتنفيذ (من؟ ماذا؟ لماذا؟). '
            .'أعد JSON فقط بلا أي نص خارجه وبهذه المفاتيح: {"summary": "فقرتان قصيرتان", "recommendations": ["3 إلى 5 توصيات عملية مرتبة"], "risks": ["2 إلى 4 مخاطر"], "next_steps": ["2 إلى 4 خطوات تحقق قبل القرار"]}';
        $text = $this->call($system, "الأدلة:\n".json_encode($evidence, JSON_UNESCAPED_UNICODE), 1800);
        if (! preg_match('/\{.*\}/s', $text, $m)) {
            throw new \RuntimeException('رد الذكاء الاصطناعي بلا JSON.');
        }
        $j = json_decode($m[0], true, flags: JSON_THROW_ON_ERROR);
        $list = fn ($v) => array_values(array_filter(array_map('strval', is_array($v) ? $v : []), fn ($s) => trim($s) !== ''));

        return ['source' => 'ai', 'summary' => trim((string) ($j['summary'] ?? '')), 'recommendations' => $list($j['recommendations'] ?? []), 'risks' => $list($j['risks'] ?? []), 'next_steps' => $list($j['next_steps'] ?? []), 'model' => (string) config('omanilab.ai.model')];
    }

    /** @param  list<array<string, mixed>>  $tools أدوات الخادم (مثل web_search) إن لزمت */
    public function call(string $system, string $user, int $maxTokens, array $tools = []): string
    {
        $res = Http::withHeaders(['x-api-key' => Secrets::get('anthropic_api_key'), 'anthropic-version' => '2023-06-01'])
            ->timeout($tools !== [] ? 170 : 90)->retry(2, 2000)
            ->post(self::URL, ['model' => (string) config('omanilab.ai.model'), 'max_tokens' => $maxTokens, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]] + ($tools !== [] ? ['tools' => $tools] : []))
            ->throw()->json();

        return collect($res['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $e
     * @return array{source: 'rules', summary: string, recommendations: list<string>, risks: list<string>, next_steps: list<string>, model: null}
     */
    private function rulesBrief(array $e): array
    {
        $mode = $e['mode'] ?? 'import';
        $top = array_slice($e['candidates'] ?? [], 0, 3);
        $s = $e['summary'] ?? [];
        $names = implode('، ', array_map(fn ($c) => $c['name_ar'].' ('.round((float) ($c['score'] ?? 0)).')', $top));
        $summary = $mode === 'import'
            ? "أعلى الموردين المحتملين لـ«{$e['product']['name_ar']}» بحسب التقييم المركّب: {$names}."
            : "أعلى الأسواق المحتملة لتصدير «{$e['product']['name_ar']}» بحسب التقييم المركّب: {$names}.";
        $rec = [];
        $risks = [];
        if ($top !== []) {
            $rec[] = ($mode === 'import' ? 'ابدأ بمقارنة عروض ' : 'ابدأ بدراسة سوق ').$top[0]['name_ar'].' ثم '.($top[1]['name_ar'] ?? 'التالي في القائمة').'.';
        }
        if (($s['hhi_oman'] ?? 0) > 2500 && $mode === 'import') {
            $risks[] = 'مشتريات عُمان من هذا المنتج متركزة في مورد أو موردين (مؤشر HHI '.round((float) $s['hhi_oman']).')؛ وجود بديل ثانٍ يخفض مخاطر الانقطاع.';
        }
        if (! empty($s['saving_estimate']) && $mode === 'import') {
            $rec[] = 'سعر وحدة عُمان أعلى من الربع الأدنى بين كبار المصدّرين؛ الفرق المقدَّر نحو '.number_format((float) $s['saving_estimate']).' دولار سنويًا (تقدير تقريبي يتجاهل اختلاف الجودة).';
        }
        $risks[] = 'البيانات سنوية وبأسعار وحدة مشتقة (قيمة ÷ كمية)، فلا تغني عن عرض سعر فعلي.';

        return ['source' => 'rules', 'summary' => $summary, 'recommendations' => $rec, 'risks' => $risks, 'next_steps' => ['اطلب عروض أسعار من أعلى مرشَّحَين وقارنها بسعر الوحدة المعروض هنا.', 'تحقق من الرسوم الجمركية والمواصفات والمعايير المطلوبة قبل التعاقد.'], 'model' => null];
    }
}
