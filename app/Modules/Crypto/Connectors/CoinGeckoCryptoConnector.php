<?php

namespace App\Modules\Crypto\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * العملات المشفرة الكبرى من CoinGecko (واجهة عامة بلا مفتاح): السعر بالدولار والقيمة السوقية يوميًا لآخر 365 يومًا، تتراكم عبر التشغيلات.
 * تُستبعد قراءة اليوم الجاري لأنها لحظية وغير مكتملة. الاستخدام مع الإشارة إلى CoinGecko.
 */
final class CoinGeckoCryptoConnector extends ApiConnector
{
    private const BASE = 'https://api.coingecko.com/api/v3/coins/';

    /** @var array<string, array{0: string, 1: string}> معرّف CoinGecko => [رمز مختصر، اسم عربي] */
    private const COINS = [
        'bitcoin' => ['btc', 'بيتكوين'], 'ethereum' => ['eth', 'إيثريوم'], 'binancecoin' => ['bnb', 'BNB'], 'solana' => ['sol', 'سولانا'],
        'ripple' => ['xrp', 'ريبل XRP'], 'dogecoin' => ['doge', 'دوجكوين'], 'cardano' => ['ada', 'كاردانو'], 'tron' => ['trx', 'ترون'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::COINS as [$sym, $ar]) {
            $indicators[] = new IndicatorDefinition("crypto.{$sym}_usd", "سعر {$ar} (دولار)", strtoupper($sym).' price (USD)', 'دولار', 'D', [new Range(0, 1e7, 'reject')]);
            $indicators[] = new IndicatorDefinition("crypto.{$sym}_mcap", "القيمة السوقية لـ{$ar} (دولار)", strtoupper($sym).' market cap (USD)', 'دولار', 'D', [new Range(0, 1e14, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'crypto.coingecko',
            source: new SourceDefinition(key: 'coingecko', nameAr: 'CoinGecko', nameEn: 'CoinGecko', publisher: 'CoinGecko', url: 'https://www.coingecko.com', license: 'CoinGecko API — مع الإشارة إلى المصدر'),
            nameAr: 'العملات المشفرة: الأسعار والقيمة السوقية (يومي)',
            nameEn: 'Crypto assets: prices and market caps (daily)',
            section: 'markets',
            intervalMinutes: 6 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        $today = gmdate('Y-m-d');
        $i = 0;
        foreach (self::COINS as $id => [$sym]) {
            if ($i++ > 0) {
                sleep(8);
            }
            $res = $this->http()->timeout(60)->retry(4, 15000)->get(self::BASE.$id.'/market_chart', ['vs_currency' => 'usd', 'days' => 365, 'interval' => 'daily'])->throw()->json();
            if (! isset($res['prices']) || ! is_array($res['prices'])) {
                throw new RuntimeException("CoinGecko {$id}: تغيّر شكل الاستجابة.");
            }
            foreach (['prices' => 'usd', 'market_caps' => 'mcap'] as $field => $suffix) {
                foreach ($res[$field] ?? [] as [$ms, $value]) {
                    $day = gmdate('Y-m-d', (int) ($ms / 1000));
                    if ($day < $today && is_numeric($value) && $value > 0) {
                        $out[] = ["crypto.{$sym}_{$suffix}", $day, (float) $value];
                    }
                }
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $day, $value]) {
            yield new ParsedRecord($key, 'WLD', $day, $value);
        }
    }
}
