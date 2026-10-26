<?php

namespace App\Modules\Prices;

/** الأسواق المتاحة لمقارنة الأسعار والاشتراكات: رمز ISO2 => [الاسم العربي، العملة المحلية الغالبة، ISO3]. */
final class Countries
{
    /** @var array<string, array{0: string, 1: string, 2: string}> */
    public const ALL = [
        'OM' => ['عُمان', 'OMR', 'OMN'], 'AE' => ['الإمارات', 'AED', 'ARE'], 'SA' => ['السعودية', 'SAR', 'SAU'], 'QA' => ['قطر', 'QAR', 'QAT'],
        'KW' => ['الكويت', 'KWD', 'KWT'], 'BH' => ['البحرين', 'BHD', 'BHR'], 'EG' => ['مصر', 'EGP', 'EGY'], 'JO' => ['الأردن', 'JOD', 'JOR'],
        'TR' => ['تركيا', 'TRY', 'TUR'], 'IN' => ['الهند', 'INR', 'IND'], 'PK' => ['باكستان', 'PKR', 'PAK'], 'US' => ['الولايات المتحدة', 'USD', 'USA'],
        'GB' => ['بريطانيا', 'GBP', 'GBR'], 'DE' => ['ألمانيا', 'EUR', 'DEU'], 'FR' => ['فرنسا', 'EUR', 'FRA'], 'CA' => ['كندا', 'CAD', 'CAN'],
        'AU' => ['أستراليا', 'AUD', 'AUS'], 'SG' => ['سنغافورة', 'SGD', 'SGP'], 'MY' => ['ماليزيا', 'MYR', 'MYS'], 'ZA' => ['جنوب أفريقيا', 'ZAR', 'ZAF'],
        'BR' => ['البرازيل', 'BRL', 'BRA'], 'JP' => ['اليابان', 'JPY', 'JPN'], 'CN' => ['الصين', 'CNY', 'CHN'],
    ];

    /** @param  list<string>  $codes @return list<string> الرموز الصالحة فقط (بحد أقصى 8) */
    public static function valid(array $codes): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('strtoupper', $codes), fn ($c) => isset(self::ALL[$c])))), 0, 8);
    }
}
