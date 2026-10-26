<?php

namespace App\Modules\MarketIntel;

/**
 * إشارات استخباراتية حتمية من تقرير المنتج: فرص ومخاطر وتنبيهات مع رقمها الداعم، مرتبة بالأهمية.
 * كل قاعدة شفافة (عتبة واضحة) كي يفهم القارئ لماذا ظهرت الإشارة؛ والذكاء الاصطناعي يستلمها دليلًا لا بديلًا عنها.
 */
final class SignalEngine
{
    /**
     * @param  array<string, mixed>  $a  ناتج MarketIntelService::analysis قبل الإشارات
     * @return list<array{level: 'risk'|'opportunity'|'info', title: string, detail: string, weight: int}>
     */
    public function build(array $a): array
    {
        $mode = $a['summary']['mode'];
        $s = $a['summary'];
        $c = $a['candidates'];
        $out = [];
        $pct = fn (float $v) => number_format($v * 100, 0).'%';
        $usd = fn (float $v) => $v >= 1e9 ? number_format($v / 1e9, 1).' مليار دولار' : number_format($v / 1e6, 1).' مليون دولار';

        if ($mode === 'import' && $s['oman_value'] > 0) {
            $top = collect($c)->sortByDesc('oman_value')->first();
            $share = $top ? $top['oman_value'] / $s['oman_value'] : 0;
            if ($share >= 0.5) {
                $out[] = ['level' => 'risk', 'title' => 'اعتماد مرتفع على مورد واحد', 'detail' => "{$top['name_ar']} يورّد ".$pct($share).' من واردات عُمان من هذا المنتج؛ أي انقطاع أو رفع سعر منه يصيب السوق مباشرة.', 'weight' => 90];
            }
            if (! empty($s['oman_unit_value']) && ! empty($s['median_unit_value']) && $s['oman_unit_value'] > $s['median_unit_value'] * 1.15) {
                $gap = $s['oman_unit_value'] / $s['median_unit_value'] - 1;
                $out[] = ['level' => 'opportunity', 'title' => 'عُمان تدفع أعلى من وسيط السوق', 'detail' => 'سعر وحدة واردات عُمان أعلى بـ'.$pct($gap).' من وسيط كبار المصدّرين'.(! empty($s['saving_estimate']) ? '؛ الوفر النظري إن اشترت بسعر الربع الأدنى نحو '.$usd($s['saving_estimate']).' سنويًا' : '').' (قد يعكس فرق الجودة أو المواصفات).', 'weight' => 80];
            }
            foreach (array_slice($c, 0, 8) as $x) {
                if ($x['oman_value'] <= 0 && ($x['score'] ?? 0) >= 70 && ($x['cagr'] ?? 0) > 5) {
                    $out[] = ['level' => 'opportunity', 'title' => "مورد بديل غير مستغل: {$x['name_ar']}", 'detail' => 'تقييمه '.round($x['score']).'/100 وصادراته تنمو '.number_format($x['cagr'], 1).'% سنويًا، ولا تشتري منه عُمان حاليًا.', 'weight' => 70];
                    break;
                }
            }
            $fta = array_values(array_filter(array_slice($c, 0, 10), fn ($x) => ! empty($x['fta'])));
            if ($fta !== []) {
                $out[] = ['level' => 'info', 'title' => 'موردون بإعفاء جمركي باتفاقية', 'detail' => implode('، ', array_map(fn ($x) => $x['name_ar'], $fta)).' ضمن أعلى عشرة، ويدخل منتجهم عُمان بتعرفة تفضيلية ('.$fta[0]['fta'].').', 'weight' => 40];
            }
        }

        if ($mode === 'export') {
            foreach (array_slice($c, 0, 10) as $x) {
                if ($x['oman_value'] <= 0 && ($x['cagr'] ?? 0) > 5 && ($x['share'] ?? 0) > 0.02) {
                    $out[] = ['level' => 'opportunity', 'title' => "سوق نامٍ لا تصل إليه عُمان: {$x['name_ar']}", 'detail' => 'يستورد '.$pct($x['share']).' من التجارة العالمية للمنتج وينمو '.number_format($x['cagr'], 1).'% سنويًا'.(! empty($x['fta']) ? '، ولعُمان معه اتفاقية تجارة حرة' : '').'.', 'weight' => 80];
                    break;
                }
            }
            $high = array_values(array_filter(array_slice($c, 0, 10), fn ($x) => ($x['tariff']['tariff'] ?? 0) >= 10 && empty($x['fta'])));
            if ($high !== []) {
                $out[] = ['level' => 'risk', 'title' => 'حواجز جمركية في أسواق كبيرة', 'detail' => implode('، ', array_map(fn ($x) => $x['name_ar'].' '.number_format($x['tariff']['tariff'], 1).'%', $high)).' (الرسم العام MFN، آخر سنة '.$high[0]['tariff']['year'].').', 'weight' => 60];
            }
            if (($a['rca'] ?? null) !== null) {
                $out[] = $a['rca'] >= 1
                    ? ['level' => 'opportunity', 'title' => 'لعُمان ميزة نسبية ظاهرة', 'detail' => 'مؤشر الميزة النسبية (RCA) '.number_format($a['rca'], 2).' > 1: حصة المنتج من صادرات عُمان أعلى من حصته في التجارة العالمية.', 'weight' => 65]
                    : ['level' => 'info', 'title' => 'لا ميزة نسبية ظاهرة بعد', 'detail' => 'مؤشر الميزة النسبية (RCA) '.number_format($a['rca'], 2).' < 1؛ دخول السوق يحتاج قدرة إنتاجية أو سعرًا منافسًا.', 'weight' => 30];
            }
        }

        $u = $a['unit_prices'] ?? null;
        $omanPer = $u['oman_total']['unit_price'] ?? null;
        if ($u && $omanPer && ! empty($u['world_median_unit_price']) && $u['unit']) {
            $gap = $omanPer / $u['world_median_unit_price'] - 1;
            if ($mode === 'import' && $gap >= 0.2) {
                $out[] = ['level' => 'opportunity', 'title' => 'سعر الوحدة الواحدة ('.$u['unit'].') لعُمان أعلى من السوق', 'detail' => 'متوسط ما تدفعه عُمان '.number_format($omanPer, 2).' دولار/'.$u['unit'].' مقابل وسيط عالمي '.number_format($u['world_median_unit_price'], 2).' ('.($gap > 0 ? '+' : '').$pct($gap).'، Comtrade '.$u['year'].').', 'weight' => 78];
            } elseif ($mode === 'export' && $gap <= -0.2) {
                $out[] = ['level' => 'risk', 'title' => 'عُمان تبيع بسعر وحدة أدنى من السوق', 'detail' => 'متوسط سعر تصدير عُمان '.number_format($omanPer, 2).' دولار/'.$u['unit'].' مقابل وسيط عالمي '.number_format($u['world_median_unit_price'], 2).' ('.$pct($gap).')؛ مجال لرفع القيمة المضافة أو تحسين التسعير.', 'weight' => 70];
            }
        }

        $com = $a['commodity'][0] ?? null;
        if ($com && $com['change_12m'] !== null && abs($com['change_12m']) >= 0.15) {
            $up = $com['change_12m'] > 0;
            $good = $mode === 'export' ? $up : ! $up;
            $out[] = ['level' => $good ? 'opportunity' : 'risk', 'title' => ($up ? 'ارتفاع' : 'هبوط').' سعر السلعة عالميًا', 'detail' => "{$com['name_ar']} ".($up ? '+' : '').$pct($com['change_12m']).' خلال 12 شهرًا (آخر قراءة '.$com['period'].').', 'weight' => 75];
        }

        $wt = $a['world_trend'] ?? [];
        if (count($wt) >= 4) {
            $ys = array_keys($wt);
            $first = $wt[$ys[count($ys) - 4]]['value'];
            $last = $wt[end($ys)]['value'];
            if ($first > 0) {
                $g = ($last / $first) ** (1 / 3) - 1;
                if (abs($g) >= 0.05) {
                    $out[] = ['level' => 'info', 'title' => 'السوق العالمي '.($g > 0 ? 'يتوسع' : 'ينكمش'), 'detail' => 'نمو سنوي مركّب '.($g > 0 ? '+' : '').$pct($g).' في آخر ثلاث سنوات (قيمة التجارة العالمية للمنتج).', 'weight' => 35];
                }
            }
        }

        $ot = $a['oman_trend'] ?? [];
        if (count($ot) >= 2) {
            $ys = array_keys($ot);
            [$p, $l] = [$ot[$ys[count($ys) - 2]]['value'], $ot[end($ys)]['value']];
            if ($p > 0 && abs($l / $p - 1) >= 0.2) {
                $out[] = ['level' => 'info', 'title' => ($mode === 'import' ? 'واردات' : 'صادرات').' عُمان '.($l > $p ? 'قفزت' : 'تراجعت'), 'detail' => ($l > $p ? '+' : '').$pct($l / $p - 1).' في '.end($ys).' مقارنة بالسنة السابقة.', 'weight' => 45];
            }
        }

        if (($a['pci'] ?? null) !== null && $a['pci'] >= 1) {
            $out[] = ['level' => 'info', 'title' => 'منتج عالي التعقيد', 'detail' => 'مؤشر تعقيد المنتج (PCI) '.number_format($a['pci'], 2).': تصنيعه يحتاج قدرات متقدمة، وتوطينه يرفع تعقيد الاقتصاد.', 'weight' => 25];
        }

        if (($s['hhi_market'] ?? 0) >= 2500) {
            $out[] = ['level' => 'risk', 'title' => 'سوق عالمي شديد التركّز', 'detail' => 'مؤشر HHI للسوق العالمي '.round($s['hhi_market']).': قلة من الدول تتحكم بالعرض، فالبدائل محدودة وقت الأزمات.', 'weight' => 55];
        }

        usort($out, fn ($x, $y) => $y['weight'] <=> $x['weight']);

        return $out;
    }
}
