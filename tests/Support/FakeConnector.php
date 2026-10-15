<?php

namespace Tests\Support;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\SeriesScale;
use Carbon\CarbonImmutable;
use RuntimeException;

/** موصل اختباري يُتحكَّم بسلوكه من الاختبار عبر الخصائص الساكنة. */
final class FakeConnector extends ApiConnector
{
    /** @var array<string, array<int|string, float>> entity => [period => value] */
    public static array $data = [];

    public static string $token = 'v1';

    public static string $version = 'Fake Outlook v1';

    public static string $publishedAt = '2026-10-01 00:00:00';

    public static ?string $failWith = null;

    /** مصدر بلا فحص رخيص: الفحص يعيد token = null. */
    public static bool $noCheapProbe = false;

    public static int $revision = 1;

    public static string $key = 'fake.test';

    /** يغيّر بصمة الحمولة حين يتغير ناتج $custom (الحمولة نفسها لا تحمله). */
    public static string $payloadSalt = '';

    public static string $queueName = 'ingest';

    /** @var (callable(): iterable<mixed>)|null يستبدل ناتج parse كاملًا */
    public static $custom = null;

    public static function reset(): void
    {
        self::$data = ['OMN' => ['2023' => 1.4, '2024' => 1.6, '2025' => 2.4], 'SAU' => ['2023' => 0.8, '2024' => 1.3, '2025' => 3.0]];
        self::$token = 'v1';
        self::$version = 'Fake Outlook v1';
        self::$publishedAt = '2026-10-01 00:00:00';
        self::$failWith = null;
        self::$noCheapProbe = false;
        self::$revision = 1;
        self::$key = 'fake.test';
        self::$payloadSalt = '';
        self::$queueName = 'ingest';
        self::$custom = null;
    }

    /** @return iterable<ParsedEntity|ParsedRecord> */
    public static function items(): iterable
    {
        foreach (self::$data as $entity => $series) {
            yield new ParsedEntity($entity, "Country {$entity}");
            foreach ($series as $period => $value) {
                yield new ParsedRecord('fake.GDP', $entity, (string) $period, (float) $value);
            }
        }
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: self::$key,
            source: new SourceDefinition('fake', 'مصدر اختباري', 'Fake Source'),
            nameAr: 'مجموعة اختبارية',
            nameEn: 'Fake dataset',
            section: 'test',
            intervalMinutes: 60,
            indicators: [
                new IndicatorDefinition('fake.GDP', 'نمو', 'Growth', '%', rules: [new Range(-50, 50), new SeriesScale(100), new MaxRevision(2.0, 0.25), new Completeness(0.1)]),
            ],
        );
    }

    public function revision(): int
    {
        return self::$revision;
    }

    public function queue(): string
    {
        return self::$queueName;
    }

    public function probe(): ProbeResult
    {
        if (self::$failWith) {
            throw new RuntimeException(self::$failWith);
        }

        return new ProbeResult(self::$noCheapProbe ? null : self::$token, self::$version, CarbonImmutable::parse(self::$publishedAt, 'UTC'));
    }

    public function fetch(): RawPayload
    {
        return new RawPayload(json_encode(self::$data).self::$payloadSalt);
    }

    public function parse(RawPayload $payload): iterable
    {
        yield from self::$custom ? (self::$custom)() : self::items();
    }
}
