<?php

namespace App\Core\Ingestion;

use Locale;

/**
 * توحيد الكيانات قبل دخولها staging، كي لا تظهر الدولة الواحدة بسلسلتين:
 *  - رموز مختلفة للكيان نفسه بين المصادر (كوسوفو UVK عند IMF مقابل XKX عند البنك الدولي) تُوحَّد عبر config('omanilab.entity_aliases').
 *  - الأسماء تُنظَّف: مسافات زائدة، NBSP، وحروف كبيرة كلها (CURACAO) تُحوَّل إلى صيغة عنوان.
 *  - الاسم العربي للدول من بيانات ICU (رمز ISO2)، وللمجموعات الشائعة من جدول صغير.
 */
final class EntityNormaliser
{
    /** مجموعات البنك الدولي الشائعة. */
    private const AGGREGATES_AR = [
        'WLD' => 'العالم', 'EUU' => 'الاتحاد الأوروبي', 'EMU' => 'منطقة اليورو', 'ARB' => 'العالم العربي',
        'MEA' => 'الشرق الأوسط وشمال أفريقيا', 'MNA' => 'الشرق الأوسط وشمال أفريقيا (باستثناء الدخل المرتفع)',
        'HIC' => 'الدول مرتفعة الدخل', 'UMC' => 'الدول متوسطة الدخل (الشريحة العليا)', 'LMC' => 'الدول متوسطة الدخل (الشريحة الدنيا)',
        'LIC' => 'الدول منخفضة الدخل', 'OED' => 'دول منظمة التعاون الاقتصادي (OECD)', 'SSF' => 'أفريقيا جنوب الصحراء',
        'EAS' => 'شرق آسيا والمحيط الهادئ', 'ECS' => 'أوروبا وآسيا الوسطى', 'LCN' => 'أمريكا اللاتينية والكاريبي',
        'NAC' => 'أمريكا الشمالية', 'SAS' => 'جنوب آسيا', 'LDC' => 'أقل البلدان نموًا', 'SST' => 'الدول الجزرية الصغيرة',
        'GCC' => 'دول مجلس التعاون', 'ZZZ' => 'غير محدد / غير موزّع', 'OFZ' => 'المناطق الحرة العُمانية',
    ];

    /** @var array<string, string> */
    private array $aliases;

    public function __construct()
    {
        $this->aliases = array_change_key_case((array) config('omanilab.entity_aliases', []), CASE_UPPER);
    }

    public function code(string $code): string
    {
        $code = strtoupper(trim($code));

        return $this->aliases[$code] ?? $code;
    }

    public function name(string $name, string $type = 'country'): string
    {
        $name = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $name));

        // الاسم القانوني للشركة يبقى كما سُجّل رسميًا (حتى لو كله حروف كبيرة)؛ التنظيف للدول والمجموعات فقط.
        if ($type === 'company') {
            return $name;
        }

        // كلها حروف كبيرة (CURACAO، NORTHERN MARIANA ISLANDS) → صيغة عنوان؛ الأقواس والاختصارات القصيرة تبقى كما هي.
        if (mb_strlen($name) > 4 && $name === mb_strtoupper($name) && preg_match('/[A-Z]{3}/', $name)) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
            $name = (string) preg_replace_callback('/\b(Of|And|The|Part|De)\b/u', fn ($m) => mb_strtolower($m[1]), $name);
        }

        return $name;
    }

    /** اسم عربي من رمز ISO2 (دول) أو من جدول المجموعات؛ null إن لم يوجد ما يمكن الوثوق به. */
    public function arabicName(string $code, ?string $iso2 = null): ?string
    {
        $code = $this->code($code);
        if (isset(self::AGGREGATES_AR[$code])) {
            return self::AGGREGATES_AR[$code];
        }
        if ($iso2 === null || strlen($iso2) !== 2 || ! class_exists(Locale::class)) {
            return null;
        }

        $name = Locale::getDisplayRegion('-'.strtoupper($iso2), 'ar');

        // ICU يعيد الرمز نفسه أو الاسم الإنجليزي عند غياب بيانات اللغة، و«منطقة غير معروفة» لرمز غير موجود؛ لا نقبل ذلك.
        $unknown = Locale::getDisplayRegion('-ZZ', 'ar');

        return ($name === '' || $name === $unknown || preg_match('/^[A-Za-z0-9 .\'-]+$/', $name)) ? null : $name;
    }
}
