<?php

namespace App\Core\Support;

/**
 * أسماء الدول الإنجليزية كما تنشرها الملفات المفتوحة (SIPRI، World Mining Data…) إلى رموز ISO3. ما لا يُعرف يُعاد null فيُتخطى (لا تخمين).
 */
final class CountryNames
{
    private const ISO = [
        'Algeria' => 'DZA', 'Libya' => 'LBY', 'Morocco' => 'MAR', 'Tunisia' => 'TUN', 'Angola' => 'AGO', 'Benin' => 'BEN', 'Botswana' => 'BWA', 'Burkina Faso' => 'BFA', 'Burundi' => 'BDI',
        'Cameroon' => 'CMR', 'Cape Verde' => 'CPV', 'Central African Republic' => 'CAF', 'Chad' => 'TCD', 'Congo, DR' => 'COD', 'Congo, Republic' => 'COG', "Cote d'Ivoire" => 'CIV',
        'Djibouti' => 'DJI', 'Equatorial Guinea' => 'GNQ', 'Eritrea' => 'ERI', 'Ethiopia' => 'ETH', 'Gabon' => 'GAB', 'Gambia, The' => 'GMB', 'Ghana' => 'GHA', 'Guinea' => 'GIN',
        'Guinea-Bissau' => 'GNB', 'Kenya' => 'KEN', 'Lesotho' => 'LSO', 'Liberia' => 'LBR', 'Madagascar' => 'MDG', 'Malawi' => 'MWI', 'Mali' => 'MLI', 'Mauritania' => 'MRT',
        'Mauritius' => 'MUS', 'Mozambique' => 'MOZ', 'Namibia' => 'NAM', 'Niger' => 'NER', 'Nigeria' => 'NGA', 'Rwanda' => 'RWA', 'Senegal' => 'SEN', 'Seychelles' => 'SYC',
        'Sierra Leone' => 'SLE', 'Somalia' => 'SOM', 'South Africa' => 'ZAF', 'South Sudan' => 'SSD', 'Sudan' => 'SDN', 'Eswatini' => 'SWZ', 'Tanzania' => 'TZA', 'Togo' => 'TGO',
        'Uganda' => 'UGA', 'Zambia' => 'ZMB', 'Zimbabwe' => 'ZWE', 'Belize' => 'BLZ', 'Costa Rica' => 'CRI', 'Cuba' => 'CUB', 'Dominican Republic' => 'DOM', 'El Salvador' => 'SLV',
        'Guatemala' => 'GTM', 'Haiti' => 'HTI', 'Honduras' => 'HND', 'Jamaica' => 'JAM', 'Mexico' => 'MEX', 'Nicaragua' => 'NIC', 'Panama' => 'PAN', 'Trinidad and Tobago' => 'TTO',
        'Canada' => 'CAN', 'United States of America' => 'USA', 'Argentina' => 'ARG', 'Bolivia' => 'BOL', 'Brazil' => 'BRA', 'Chile' => 'CHL', 'Colombia' => 'COL', 'Ecuador' => 'ECU',
        'Guyana' => 'GUY', 'Paraguay' => 'PRY', 'Peru' => 'PER', 'Uruguay' => 'URY', 'Venezuela' => 'VEN', 'Australia' => 'AUS', 'Fiji' => 'FJI', 'New Zealand' => 'NZL',
        'Papua New Guinea' => 'PNG', 'Afghanistan' => 'AFG', 'Bangladesh' => 'BGD', 'India' => 'IND', 'Nepal' => 'NPL', 'Pakistan' => 'PAK', 'Sri Lanka' => 'LKA', 'China' => 'CHN',
        'Japan' => 'JPN', 'Korea, North' => 'PRK', 'Korea, South' => 'KOR', 'Mongolia' => 'MNG', 'Taiwan' => 'TWN', 'Brunei' => 'BRN', 'Cambodia' => 'KHM', 'Indonesia' => 'IDN',
        'Laos' => 'LAO', 'Malaysia' => 'MYS', 'Myanmar' => 'MMR', 'Philippines' => 'PHL', 'Singapore' => 'SGP', 'Thailand' => 'THA', 'Timor Leste' => 'TLS', 'Viet Nam' => 'VNM',
        'Kazakhstan' => 'KAZ', 'Kyrgyz Republic' => 'KGZ', 'Tajikistan' => 'TJK', 'Turkmenistan' => 'TKM', 'Uzbekistan' => 'UZB', 'Albania' => 'ALB', 'Bosnia and Herzegovina' => 'BIH',
        'Bulgaria' => 'BGR', 'Croatia' => 'HRV', 'Czechia' => 'CZE', 'Estonia' => 'EST', 'Hungary' => 'HUN', 'Kosovo' => 'XKX', 'Latvia' => 'LVA', 'Lithuania' => 'LTU',
        'North Macedonia' => 'MKD', 'Montenegro' => 'MNE', 'Poland' => 'POL', 'Romania' => 'ROU', 'Serbia' => 'SRB', 'Slovakia' => 'SVK', 'Slovenia' => 'SVN', 'Armenia' => 'ARM',
        'Azerbaijan' => 'AZE', 'Belarus' => 'BLR', 'Georgia' => 'GEO', 'Moldova' => 'MDA', 'Russia' => 'RUS', 'Ukraine' => 'UKR', 'Austria' => 'AUT', 'Belgium' => 'BEL', 'Cyprus' => 'CYP',
        'Denmark' => 'DNK', 'Finland' => 'FIN', 'France' => 'FRA', 'Germany' => 'DEU', 'Greece' => 'GRC', 'Iceland' => 'ISL', 'Ireland' => 'IRL', 'Italy' => 'ITA', 'Luxembourg' => 'LUX',
        'Malta' => 'MLT', 'Netherlands' => 'NLD', 'Norway' => 'NOR', 'Portugal' => 'PRT', 'Spain' => 'ESP', 'Sweden' => 'SWE', 'Switzerland' => 'CHE', 'United Kingdom' => 'GBR',
        'Bahrain' => 'BHR', 'Egypt' => 'EGY', 'Iran' => 'IRN', 'Iraq' => 'IRQ', 'Israel' => 'ISR', 'Jordan' => 'JOR', 'Kuwait' => 'KWT', 'Lebanon' => 'LBN', 'Oman' => 'OMN',
        'Qatar' => 'QAT', 'Saudi Arabia' => 'SAU', 'Syria' => 'SYR', 'Türkiye' => 'TUR', 'United Arab Emirates' => 'ARE', 'Yemen' => 'YEM',
        'Bhutan' => 'BTN', 'Bosnia-Herzegovina' => 'BIH', 'Congo, Rep.' => 'COG', 'United States' => 'USA', 'Vietnam' => 'VNM', 'Congo, D.R.' => 'COD', 'New Caledonia' => 'NCL',
        'Solomon Islands' => 'SLB', 'Kyrgyzstan' => 'KGZ', 'French Guiana' => 'GUF', 'Suriname' => 'SUR', 'Christmas Island' => 'CXR', 'Nauru' => 'NRU', 'Bahamas' => 'BHS', 'Barbados' => 'BRB',
        'South Korea' => 'KOR', 'North Korea' => 'PRK', 'Czech Republic' => 'CZE', 'Turkey' => 'TUR', 'Macedonia' => 'MKD', 'Burma' => 'MMR', 'Ivory Coast' => 'CIV',
    ];

    public static function iso(?string $name): ?string
    {
        return $name === null ? null : (self::ISO[trim($name)] ?? null);
    }
}
