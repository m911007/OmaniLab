<?php

namespace App\Modules\Manafeth\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\EntityNormaliser;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use RuntimeException;

/**
 * شركاء عُمان التجاريون من منافذ: الاستيراد من الدولة، والتصدير إليها، وإعادة التصدير إليها (بالريال العُماني).
 * الكيان = الدولة الشريكة (ISO3). منافذ ترمّز الدول برقم UN M49، فيُحوَّل إلى ISO3 بمرجع الأمم المتحدة (Comtrade Reporters).
 *
 * السنوات الكاملة (آخر 4) تُنشر في مؤشرات سنوية عادية. السنة الجارية غير المكتملة تُنشر في مؤشرات *_YTD منفصلة
 * (منذ بداية العام حتى آخر شهر منشور، ويظهر الشهر في إصدار المجموعة)، فلا تُقرأ كسنة كاملة ولا تُقارَن بها خطأً.
 *
 * الاستجابة ثقيلة (~30 ميغابايت للسنة) فتُستخلص منها الدول فقط، والحمولة الخام المخزّنة هي الخلاصة المضغوطة.
 * تفاصيل المنتجات (61 ألف بند HS) خارج نموذج البيانات الحالي ولها مرحلة لاحقة (تحتاج بُعد منتج).
 */
final class ManafethPartnersConnector extends ManafethConnector
{
    private const YEARS = 4;

    /** المؤشر => [حقل الدول، الاسم العربي، الإنجليزي، حقل الإجمالي الرسمي] */
    private const FLOWS = [
        'IMPORTS_FROM' => ['import_by_countries', 'الواردات من الدولة', 'Imports from partner', 'import_value'],
        'EXPORTS_TO' => ['export_by_countries', 'الصادرات إلى الدولة', 'Exports to partner', 'export_value'],
        'REEXPORTS_TO' => ['reexport_by_countries', 'إعادة التصدير إلى الدولة', 'Re-exports to partner', 'reexport_value'],
    ];

    /** تسامح مجموع الدول فوق الإجمالي الرسمي (فروق تقريب)؛ وما دونه يُعرض «غير موزّع». */
    private const OVERSHOOT_TOLERANCE = 0.005;

    private const UN_REPORTERS = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    private const UN_PARTNER_AREAS = 'https://comtradeapi.un.org/files/v1/app/reference/partnerAreas.json';

    /** أقصى حصة (من قيمة التدفق) مسموحة للدول التي تعذّر ترميزها قبل أن يُوقَف الجلب. */
    private const MAX_UNMAPPED_SHARE = 0.01;

    /** مئات الاستدعاءات أو ملفات ثقيلة: طابورها المستقل كي لا تحجب بقية المصادر. */
    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::FLOWS as $code => [, $ar, $en]) {
            $indicators[] = new IndicatorDefinition("manafeth.{$code}", $ar, $en, 'ريال عُماني', 'A',
                [new Range(0, 1e13), new RelativeRevision(0.5, 0.25), new Completeness(0.2)]);
            $indicators[] = new IndicatorDefinition("manafeth.{$code}_YTD", "{$ar} منذ بداية العام", "{$en}, year to date", 'ريال عُماني', 'A',
                [new Range(0, 1e13)]);
        }

        return new DatasetDefinition(
            key: 'manafeth.partners',
            source: $this->source(),
            nameAr: 'عُمان: الشركاء التجاريون (منافذ)',
            nameEn: 'Oman trade partners (Manafeth)',
            section: 'trade',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function fetch(): RawPayload
    {
        ['year' => $latestYear, 'month' => $latestMonth] = $this->latestPeriod();
        $partial = $latestMonth < 12;
        $lastComplete = $partial ? $latestYear - 1 : $latestYear;
        $reference = $this->reference();

        $body = ['years' => [], 'ytd' => null, 'names' => [], 'iso2' => [], 'unmapped' => []];

        for ($year = $lastComplete - self::YEARS + 1; $year <= $lastComplete; $year++) {
            $body['years'][$year] = $this->yearFlows($year, $reference, $body);
        }
        if ($partial) {
            $body['ytd'] = ['year' => $latestYear, 'month' => $latestMonth, 'flows' => $this->yearFlows($latestYear, $reference, $body)];
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $normaliser = app(EntityNormaliser::class);

        foreach ($data['names'] as $iso => $name) {
            yield new ParsedEntity(
                (string) $iso, $iso === 'ZZZ' ? 'Not specified' : ($name !== '' ? $name : (string) $iso),
                $iso === 'ZZZ' ? 'other' : 'country', $normaliser->arabicName((string) $iso, $data['iso2'][$iso] ?? null),
            );
        }

        foreach ($data['years'] as $year => $flows) {
            foreach ($flows as $flow => $byCountry) {
                foreach ($byCountry as $iso => $value) {
                    yield new ParsedRecord("manafeth.{$flow}", (string) $iso, (string) $year, (float) $value);
                }
            }
        }

        foreach (($data['ytd']['flows'] ?? []) as $flow => $byCountry) {
            foreach ($byCountry as $iso => $value) {
                yield new ParsedRecord("manafeth.{$flow}_YTD", (string) $iso, (string) $data['ytd']['year'], (float) $value);
            }
        }
    }

    /**
     * @param  array{codes: array<int, string>, iso2: array<string, string>}  $reference
     * @param  array<string, mixed>  $body  يُحدَّث بالأسماء ورموز ISO2 للدول المصادَفة
     * @return array<string, array<string, float>>
     */
    private function yearFlows(int $year, array $reference, array &$body): array
    {
        $row = $this->statistics((string) $year)[0];
        $flows = [];

        foreach (self::FLOWS as $flow => [$field, , , $totalField]) {
            $totals = [];
            $unmapped = [];
            $grand = 0.0;
            foreach (json_decode((string) ($row[$field] ?? '[]'), true, flags: JSON_THROW_ON_ERROR) as $r) {
                if (! is_numeric($r['value'])) {
                    continue;
                }
                $value = (float) $r['value'];
                $grand += $value;
                $iso = $reference['codes'][(int) $r['COUNTRY_ID']] ?? null;
                if ($iso === null) {
                    $label = trim((string) $r['ENG_DESC']).' ('.$r['COUNTRY_ID'].')';
                    $unmapped[$label] = ($unmapped[$label] ?? 0) + $value;

                    continue;
                }
                $totals[$iso] = ($totals[$iso] ?? 0) + $value;
                $body['names'][$iso] = trim((string) $r['ENG_DESC']);
                if (isset($reference['iso2'][$iso])) {
                    $body['iso2'][$iso] = $reference['iso2'][$iso];
                }
            }

            // دول بلا ترميز تُتخطى بصمت لو كانت صغيرة، لكن لو صارت وزنًا حقيقيًا (كما حدث لأمريكا وفرنسا) يتوقف الجلب.
            $lost = array_sum($unmapped);
            if ($grand > 0 && $lost / $grand > self::MAX_UNMAPPED_SHARE) {
                arsort($unmapped);
                throw new RuntimeException(sprintf('منافذ %d/%s: دول بلا ترميز تمثل %.1f%% من قيمة التدفق %s — أضفها إلى manafeth_country_overrides: %s',
                    $year, $flow, $lost / $grand * 100, $flow, implode('، ', array_keys(array_slice($unmapped, 0, 5, true)))));
            }
            foreach ($unmapped as $label => $v) {
                $body['unmapped']["{$year}/{$flow}"][$label] = $v;
            }

            // مجموع الدول يجب ألا يتجاوز الإجمالي الرسمي؛ وما ينقص عنه (المصدر لا يوزّعه على دول) يُعرض بندًا مستقلًا «غير موزّع».
            $official = is_numeric($row[$totalField] ?? null) ? (float) $row[$totalField] : null;
            if ($official !== null) {
                $residual = $official - array_sum($totals);
                if ($residual < -$official * self::OVERSHOOT_TOLERANCE) {
                    throw new RuntimeException(sprintf('منافذ %d/%s: مجموع الدول (%s) يتجاوز الإجمالي الرسمي (%s) — خلل في الترميز أو التكرار.', $year, $flow, number_format(array_sum($totals)), number_format($official)));
                }
                if ($residual > $official * self::OVERSHOOT_TOLERANCE) {
                    $totals['ZZZ'] = ($totals['ZZZ'] ?? 0.0) + $residual;
                    $body['names']['ZZZ'] = 'Not specified';
                }
            }

            $flows[$flow] = array_filter($totals, fn ($v) => $v > 0);
        }

        return $flows;
    }

    /**
     * رمز منافذ الرقمي => ISO3، وISO3 => ISO2. الأولوية: التصحيحات الصريحة، ثم Reporters، ثم PartnerAreas للمفقود
     * (مع استبعاد المجموعات). ترميز الأمم المتحدة نفسه (Comtrade) يستعمل رموزًا خاصة لبعض الدول، لذا لا يكفي وحده.
     *
     * @return array{codes: array<int, string>, iso2: array<string, string>}
     */
    private function reference(): array
    {
        $codes = [];
        $iso2 = [];

        $take = function (array $rows, string $codeKey, string $iso3Key, string $iso2Key) use (&$codes, &$iso2): void {
            foreach ($rows as $r) {
                $code = (int) ($r[$codeKey] ?? 0);
                if (! empty($r['isGroup']) || empty($r[$iso3Key]) || isset($codes[$code])) {
                    continue;
                }
                $codes[$code] = (string) $r[$iso3Key];
                if (! empty($r[$iso2Key])) {
                    $iso2[(string) $r[$iso3Key]] = (string) $r[$iso2Key];
                }
            }
        };

        $take($this->getJson(self::UN_REPORTERS)['results'] ?? [], 'reporterCode', 'reporterCodeIsoAlpha3', 'reporterCodeIsoAlpha2');
        if (count($codes) < 150) {
            throw new RuntimeException('مرجع رموز الدول لدى الأمم المتحدة غير مكتمل: '.count($codes));
        }
        $take($this->getJson(self::UN_PARTNER_AREAS)['results'] ?? [], 'PartnerCode', 'PartnerCodeIsoAlpha3', 'PartnerCodeIsoAlpha2');

        foreach ((array) config('omanilab.manafeth_country_overrides', []) as $code => $iso3) {
            $codes[(int) $code] = (string) $iso3;
        }

        return ['codes' => $codes, 'iso2' => $iso2];
    }
}
