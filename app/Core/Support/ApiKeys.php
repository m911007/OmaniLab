<?php

namespace App\Core\Support;

/**
 * يطبّق مفاتيح المصادر المحفوظة من صفحة الإعدادات على طلبات الجلب: بلا مفتاح تبقى الواجهات العامة المجانية كما هي،
 * ومع مفتاح تُستخدم واجهات الحد الأعلى. القيمة لا تُسجَّل ولا تُعرض.
 */
final class ApiKeys
{
    private const BLS_V1 = 'https://api.bls.gov/publicAPI/v1/timeseries/data/';

    private const BLS_V2 = 'https://api.bls.gov/publicAPI/v2/timeseries/data/';

    /** v2 (500 طلب يوميًا) مع مفتاح التسجيل، وإلا v1 العامة (25 طلبًا يوميًا). */
    public static function blsUrl(): string
    {
        return Secrets::has('bls_api_key') ? self::BLS_V2 : self::BLS_V1;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function blsBody(array $body): array
    {
        return Secrets::has('bls_api_key') ? $body + ['registrationkey' => Secrets::get('bls_api_key')] : $body;
    }

    /** مسار Comtrade المعتمد (data/v1/get) مع مفتاح الاشتراك بدل المعاينة العامة (public/v1/preview) المحدودة الحصة. */
    public static function comtradeUrl(string $previewUrl): string
    {
        return Secrets::has('comtrade_api_key') ? str_replace('/public/v1/preview/', '/data/v1/get/', $previewUrl) : $previewUrl;
    }

    /** @return array<string, string> */
    public static function comtradeHeaders(): array
    {
        return Secrets::has('comtrade_api_key') ? ['Ocp-Apim-Subscription-Key' => Secrets::get('comtrade_api_key')] : [];
    }
}
