<?php

namespace App\Modules\Companies\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * الكيانات القانونية العُمانية المسجَّلة في GLEIF: كيان لكل شركة (الرمز = LEI) بسماتها الوصفية،
 * وسلسلة عدد الكيانات بحسب سنة التأسيس. أول طبقة «شركات» في المنصة؛ تتوسع لاحقًا بمصادر أخرى.
 */
final class GleifOmanConnector extends GleifConnector
{
    private const PAGE_SIZE = 200;

    /** 2: تنظيف أسماء المدن، وسمة governorate من رمز المنطقة. */
    public function revision(): int
    {
        return 2;
    }

    /** رمز ISO 3166-2 لمحافظات عُمان → الاسم العربي. */
    public const GOVERNORATES = [
        'OM-MA' => 'مسقط', 'OM-DA' => 'الداخلية', 'OM-BU' => 'البريمي', 'OM-WU' => 'الوسطى', 'OM-ZA' => 'الظاهرة',
        'OM-BJ' => 'جنوب الباطنة', 'OM-SJ' => 'جنوب الشرقية', 'OM-BS' => 'شمال الباطنة', 'OM-SS' => 'شمال الشرقية',
        'OM-ZU' => 'ظفار', 'OM-MU' => 'مسندم',
    ];

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'gleif.oman',
            source: $this->source(),
            nameAr: 'الشركات العُمانية المسجَّلة بمعرّف LEI',
            nameEn: 'Omani legal entities with an LEI',
            section: 'companies',
            intervalMinutes: 24 * 60,
            indicators: [
                new IndicatorDefinition('gleif.OM_LEI_CREATED', 'عدد الكيانات المؤسَّسة في السنة (من حاملي LEI)', 'Omani LEI entities by year of creation', 'كيان', 'A',
                    [new Range(0, 100_000), new Completeness(0.2)]),
            ],
        );
    }

    public function fetch(): RawPayload
    {
        $records = [];
        $page = 1;
        do {
            $res = $this->page('filter%5Bentity.legalAddress.country%5D=OM&page%5Bsize%5D='.self::PAGE_SIZE.'&page%5Bnumber%5D='.$page);
            foreach ($res['data'] ?? [] as $r) {
                $records[] = $r['attributes'];
            }
            $last = (int) ($res['meta']['pagination']['lastPage'] ?? 1);
        } while ($page++ < $last);

        return new RawPayload(json_encode(['records' => $records], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $byYear = [];

        foreach ($data['records'] as $a) {
            $lei = (string) $a['lei'];
            $e = $a['entity'];
            $legal = (string) ($e['legalName']['name'] ?? $lei);
            $lang = (string) ($e['legalName']['language'] ?? '');

            [$english, $arabic] = $this->names($legal, $lang, $e);
            yield new ParsedEntity($lei, $english, 'company', $arabic);

            $created = $this->date($e['creationDate'] ?? null);
            $attrs = [
                'status' => $e['status'] ?? null,
                'category' => $e['category'] ?? null,
                'legal_form' => $e['legalForm']['id'] ?? $e['legalForm']['other'] ?? null,
                'jurisdiction' => $e['jurisdiction'] ?? null,
                'city' => $this->city($e['legalAddress']['city'] ?? null),
                'region' => $e['legalAddress']['region'] ?? null,
                'governorate' => self::GOVERNORATES[$e['legalAddress']['region'] ?? ''] ?? null,
                'country' => $e['legalAddress']['country'] ?? null,
                'address' => trim(implode('، ', array_filter((array) ($e['legalAddress']['addressLines'] ?? [])))),
                'postal_code' => $e['legalAddress']['postalCode'] ?? null,
                'creation_date' => $created,
                'registered_as' => $e['registeredAs'] ?? null,
                'registration_authority' => $e['registeredAt']['id'] ?? null,
                'registration_status' => $a['registration']['status'] ?? null,
                'last_update' => $this->date($a['registration']['lastUpdateDate'] ?? null),
                'next_renewal' => $this->date($a['registration']['nextRenewalDate'] ?? null),
                'other_names' => array_values(array_unique(array_filter(array_map(fn ($n) => $n['name'] ?? null, (array) ($e['otherNames'] ?? []))))) ?: null,
            ];
            foreach ($attrs as $key => $value) {
                if ($value !== null && $value !== '' && $value !== []) {
                    yield new ParsedAttribute($lei, $key, $value);
                }
            }

            if ($created !== null) {
                $year = substr($created, 0, 4);
                $byYear[$year] = ($byYear[$year] ?? 0) + 1;
            }
        }

        foreach ($byYear as $year => $count) {
            yield new ParsedRecord('gleif.OM_LEI_CREATED', 'OMN', (string) $year, (float) $count);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array{0: string, 1: ?string} [الاسم الإنجليزي، الاسم العربي]
     */
    private function names(string $legal, string $lang, array $entity): array
    {
        $english = $lang === 'en' ? $legal : null;
        foreach ((array) ($entity['otherNames'] ?? []) as $n) {
            if (($n['language'] ?? '') === 'en' && ! empty($n['name'])) {
                $english ??= (string) $n['name'];
            }
        }
        foreach ((array) ($entity['transliteratedOtherNames'] ?? []) as $n) {
            $english ??= ! empty($n['name']) ? (string) $n['name'] : null;
        }

        return [$english ?? $legal, $lang === 'ar' ? $legal : null];
    }

    /** يوحّد كتابة المدينة: يزيل تشكيل العربية ويحوّل اللاتينية إلى صيغة عنوان (MUSCAT = Muscat). */
    private function city(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }
        $city = trim((string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $city));
        $city = (string) preg_replace('/\s+/u', ' ', $city);

        return preg_match('/\p{Arabic}/u', $city) ? $city : mb_convert_case(mb_strtolower($city), MB_CASE_TITLE);
    }

    private function date(?string $iso): ?string
    {
        return $iso ? substr($iso, 0, 10) : null;
    }
}
