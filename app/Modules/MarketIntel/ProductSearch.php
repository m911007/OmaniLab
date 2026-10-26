<?php

namespace App\Modules\MarketIntel;

/**
 * بحث المنتجات (HS4، نحو 1200) بالعربية والإنجليزية والرمز: تطبيع عربي (همزات، تاء مربوطة، «ال»)، وقاموس يترجم كلمات عربية شائعة إلى مصطلحات HS الإنجليزية،
 * ويجمع الكلمات بـ«و» (كل كلمة يجب أن تطابق) ويرتّب بالأقرب. ما لا يطابق شيئًا هنا يُحال للتحديد الذكي بالذكاء الاصطناعي.
 */
final class ProductSearch
{
    /** @var array<string, list<string>> كلمة عربية مطبَّعة => مرادفات إنجليزية قد تظهر في اسم المنتج */
    private const GLOSSARY = [
        'قمح' => ['wheat'], 'ارز' => ['rice'], 'سكر' => ['sugar'], 'حليب' => ['milk'], 'البان' => ['dairy', 'milk'], 'جبن' => ['cheese'], 'زبد' => ['butter'], 'بيض' => ['eggs'],
        'لحم' => ['meat'], 'لحوم' => ['meat'], 'دجاج' => ['poultry', 'chicken'], 'غنم' => ['sheep'], 'ماعز' => ['goat'], 'ابقار' => ['bovine'], 'سمك' => ['fish'], 'اسماك' => ['fish'], 'روبيان' => ['crustaceans'], 'جمبري' => ['crustaceans'],
        'تمر' => ['dates', 'fruit'], 'تمور' => ['dates', 'fruit'], 'فواكه' => ['fruit'], 'فاكهه' => ['fruit'], 'خضار' => ['vegetables'], 'خضروات' => ['vegetables'], 'بطاطس' => ['potatoes'], 'طماطم' => ['tomatoes'], 'بصل' => ['onions'], 'موز' => ['bananas'],
        'قهوه' => ['coffee'], 'شاي' => ['tea'], 'توابل' => ['spices'], 'عسل' => ['honey'], 'شوكولاته' => ['chocolate', 'cocoa'], 'كاكاو' => ['cocoa'], 'حلويات' => ['sugar confectionery'], 'خبز' => ['bread'], 'مكرونه' => ['pasta'], 'طحين' => ['flour', 'wheat'], 'زيت' => ['oil'], 'زيوت' => ['oil'], 'زيتون' => ['olive'], 'مشروبات' => ['beverages'], 'عصير' => ['juice'], 'ماء' => ['water'], 'مياه' => ['water'], 'بيره' => ['beer'], 'تبغ' => ['tobacco'], 'سجائر' => ['cigarettes'],
        'نفط' => ['petroleum', 'crude'], 'خام' => ['crude'], 'بترول' => ['petroleum'], 'بنزين' => ['petroleum', 'refined'], 'ديزل' => ['refined petroleum'], 'غاز' => ['gas'], 'مسال' => ['liquefied'], 'فحم' => ['coal'], 'كهرباء' => ['electrical energy', 'electric'], 'وقود' => ['petroleum', 'fuel'],
        'ذهب' => ['gold'], 'فضه' => ['silver'], 'نحاس' => ['copper'], 'حديد' => ['iron'], 'صلب' => ['steel', 'iron'], 'المنيوم' => ['aluminium', 'aluminum'], 'زنك' => ['zinc'], 'رصاص' => ['lead'], 'نيكل' => ['nickel'], 'قصدير' => ['tin'], 'معادن' => ['metal', 'ores'], 'خامات' => ['ores'], 'الماس' => ['diamonds'], 'لؤلؤ' => ['pearls'], 'مجوهرات' => ['jewellery'],
        'سياره' => ['cars'], 'سيارات' => ['cars'], 'مركبات' => ['vehicles', 'cars'], 'شاحنات' => ['trucks', 'delivery'], 'دراجات' => ['motorcycles', 'bicycles'], 'اطارات' => ['tires', 'rubber tires'], 'قطع' => ['parts'], 'غيار' => ['parts'], 'طائرات' => ['aircraft', 'planes'], 'سفن' => ['ships', 'boats'], 'قطارات' => ['railway', 'locomotives'],
        'هاتف' => ['telephones', 'phones'], 'هواتف' => ['telephones'], 'جوال' => ['telephones'], 'حاسوب' => ['computers'], 'حواسيب' => ['computers'], 'لابتوب' => ['computers'], 'شاشات' => ['monitors', 'displays'], 'تلفزيون' => ['television'], 'كاميرات' => ['cameras'], 'بطاريات' => ['batteries'], 'اجهزه' => ['machines', 'appliances'], 'الات' => ['machinery'], 'معدات' => ['machinery'], 'مضخات' => ['pumps'], 'مكيفات' => ['air conditioning'], 'ثلاجات' => ['refrigerators'], 'غسالات' => ['washing machines'], 'محركات' => ['engines'], 'اسلاك' => ['wire', 'cables'], 'كابلات' => ['cables'], 'مولدات' => ['generators'], 'شرائح' => ['integrated circuits'], 'اشباه' => ['semiconductors'], 'طابعات' => ['printers'], 'الواح' => ['solar', 'panels'], 'شمسيه' => ['solar', 'photosensitive'],
        'ادويه' => ['medicaments', 'medicines'], 'دواء' => ['medicaments'], 'لقاحات' => ['vaccines'], 'مستلزمات' => ['medical instruments', 'supplies'], 'طبيه' => ['medical'], 'مستحضرات' => ['preparations'], 'تجميل' => ['beauty', 'cosmetics'], 'عطور' => ['perfumes'], 'صابون' => ['soap'], 'منظفات' => ['detergents', 'soap'],
        'اسمده' => ['fertilizers'], 'سماد' => ['fertilizers'], 'يوريا' => ['urea', 'fertilizers'], 'كيماويات' => ['chemicals'], 'كيماويه' => ['chemicals'], 'بلاستيك' => ['plastics'], 'بوليمرات' => ['polymers', 'plastics'], 'مطاط' => ['rubber'], 'دهانات' => ['paints'], 'اصباغ' => ['dyes', 'paints'], 'كبريت' => ['sulfur'], 'امونيا' => ['ammonia'], 'ميثانول' => ['methanol', 'alcohols'],
        'ملابس' => ['apparel', 'garments'], 'قطن' => ['cotton'], 'احذيه' => ['footwear'], 'حذاء' => ['footwear'], 'جلود' => ['leather', 'hides'], 'اقمشه' => ['fabrics', 'textile'], 'نسيج' => ['textile'], 'سجاد' => ['carpets'], 'حقائب' => ['handbags', 'bags'],
        'اثاث' => ['furniture'], 'خشب' => ['wood'], 'ورق' => ['paper'], 'كرتون' => ['paperboard', 'cartons'], 'كتب' => ['books'], 'زجاج' => ['glass'], 'اسمنت' => ['cement'], 'خزف' => ['ceramic'], 'سيراميك' => ['ceramic'], 'رخام' => ['marble'], 'جرانيت' => ['granite'], 'حجر' => ['stone'], 'رمل' => ['sand'], 'جبس' => ['gypsum'], 'طوب' => ['bricks'], 'العاب' => ['toys', 'games'], 'رياضه' => ['sports'], 'اثاث' => ['furniture'], 'ساعات' => ['watches'], 'نظارات' => ['spectacles', 'optical'], 'اسلحه' => ['arms'], 'متفجرات' => ['explosives'],
        'حيوانات' => ['animals', 'live'], 'خيول' => ['horses'], 'ابل' => ['camels', 'live animals'], 'نباتات' => ['plants'], 'ورود' => ['flowers'], 'بذور' => ['seeds'], 'اعلاف' => ['animal feed', 'fodder'], 'شعير' => ['barley'], 'ذره' => ['maize', 'corn'], 'فول' => ['soya', 'beans'], 'صويا' => ['soya'], 'عدس' => ['legumes', 'lentils'], 'حمص' => ['chickpeas', 'legumes'], 'مكسرات' => ['nuts'], 'لوز' => ['almonds'], 'فستق' => ['pistachios'], 'ملح' => ['salt'], 'يود' => ['iodine'],
    ];

    /**
     * @param  list<array{id: int, hs4: string, name_en: string, name_ar: string}>  $products
     * @return list<array{id: int, hs4: string, name_en: string, name_ar: string}>
     */
    public function search(array $products, string $query, int $limit = 20): array
    {
        $tokens = array_values(array_filter(explode(' ', $this->normalise($query)), fn ($t) => $t !== ''));
        if ($tokens === []) {
            return [];
        }
        $variants = array_map(fn ($t) => [$t, ...(self::GLOSSARY[$t] ?? self::GLOSSARY[$this->stem($t)] ?? [])], $tokens);

        $scored = [];
        foreach ($products as $p) {
            $en = mb_strtolower($p['name_en']);
            $ar = $this->normalise($p['name_ar']);
            $score = 0;
            foreach ($variants as $alts) {
                $best = 0;
                foreach ($alts as $v) {
                    $best = max($best, $this->tokenScore($v, $p['hs4'], $en, $ar));
                }
                if ($best === 0) {
                    continue 2;
                }
                $score += $best;
            }
            $scored[] = [$score - strlen($en) / 100, $p];
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn ($s) => $s[1], array_slice($scored, 0, $limit));
    }

    private function tokenScore(string $t, string $hs4, string $en, string $ar): int
    {
        if (preg_match('/^\d+$/', $t)) {
            return $hs4 === $t ? 100 : (str_starts_with($hs4, $t) ? 40 : 0);
        }
        if (preg_match('/(?:^|[^a-z])'.preg_quote($t, '/').'/u', $en)) {
            return str_starts_with($en, $t) ? 30 : 20;
        }
        if (str_contains($en, $t)) {
            return 8;
        }

        return str_contains($ar, $t) ? 6 : 0;
    }

    private function stem(string $t): string
    {
        return preg_replace('/^ال/u', '', $t) ?? $t;
    }

    private function normalise(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $s) ?? $s;
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $words = array_map(fn ($w) => preg_match('/^[\x{0600}-\x{06FF}]+$/u', $w) ? $this->stem($w) : $w, explode(' ', $s));

        return implode(' ', $words);
    }
}
