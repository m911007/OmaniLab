<?php

namespace Tests\Unit;

use App\Core\Insights\Reading;
use PHPUnit\Framework\TestCase;

class ReadingTest extends TestCase
{
    public function test_history_percentile_follows_the_polarity(): void
    {
        $past = [1.0, 2.0, 3.0, 4.0];

        $this->assertSame(100.0, Reading::historyPercentile(5.0, $past, 'higher'));
        $this->assertSame(0.0, Reading::historyPercentile(5.0, $past, 'lower'));
        $this->assertSame(50.0, Reading::historyPercentile(2.5, $past, 'higher'));
        $this->assertNull(Reading::historyPercentile(5.0, [1.0, 2.0], 'higher'));
    }

    public function test_verdict_needs_at_least_one_reference_and_averages_them(): void
    {
        $this->assertNull(Reading::verdict(null, null));
        $this->assertSame('good', Reading::verdict(90.0, null)['level']);
        $this->assertSame(['history', 'gcc'], Reading::verdict(90.0, 10.0)['basis']);
        $this->assertSame('normal', Reading::verdict(90.0, 10.0)['level']);
        $this->assertSame('attention', Reading::verdict(20.0, null)['level']);
        $this->assertSame('critical', Reading::verdict(5.0, 10.0)['level']);
    }

    public function test_period_labels_are_arabic(): void
    {
        $this->assertSame('أغسطس 2025', Reading::periodLabel('2025-08'));
        $this->assertSame('الربع الثالث 2025', Reading::periodLabel('2025-Q3'));
        $this->assertSame('2024', Reading::periodLabel('2024'));
    }

    public function test_sentence_includes_only_the_parts_that_have_numbers(): void
    {
        $meta = ['label' => 'الدين الحكومي', 'unit' => '% من الناتج', 'digits' => 1, 'polarity' => 'lower', 'rate' => true];

        $full = Reading::sentence($meta, ['period' => '2025', 'value' => 34.7], ['period' => '2024', 'value' => 35.5], ['rank' => 4, 'of' => 6, 'mean' => 53.4], ['years' => 10, 'mean' => 42.5, 'percentile' => 80.0]);
        $this->assertSame('بلغ الدين الحكومي 34.7% من الناتج في 2025، انخفاضًا عن 35.5% من الناتج في 2024. ترتيب عُمان 4 من 6 خليجيًا (متوسط بقية دول الخليج 53.4% من الناتج). القراءة أدنى من متوسط آخر 10 سنوات (42.5% من الناتج)، وأفضل من 80% من قراءات تلك الفترة.', $full);

        $bare = Reading::sentence(['label' => 'عدد السكان', 'unit' => 'مليون نسمة', 'digits' => 2, 'polarity' => null, 'rate' => false], ['period' => '2026-08', 'value' => 5.43], ['period' => '2025-08', 'value' => 5.33], null, null);
        $this->assertSame('بلغ عدد السكان 5.43 مليون نسمة في أغسطس 2026، ارتفاعًا عن 5.33 مليون نسمة في أغسطس 2025 (+1.9%).', $bare);
    }

    public function test_a_weak_reading_says_worse_than_instead_of_better_than_zero(): void
    {
        $meta = ['label' => 'معدل البطالة', 'unit' => '%', 'digits' => 1, 'polarity' => 'lower', 'rate' => true];

        $this->assertStringContainsString('وأسوأ من 100% من قراءات', Reading::sentence($meta, ['period' => '2024', 'value' => 3.3], null, null, ['years' => 10, 'mean' => 2.5, 'percentile' => 0.0]));
    }
}
