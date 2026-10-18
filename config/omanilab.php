<?php

use App\Modules\Climate\Connectors\OpenMeteoClimateConnector;
use App\Modules\Climate\Connectors\UsgsEarthquakesConnector;
use App\Modules\Places\Connectors\OsmPlacesConnector;
use App\Modules\Firms\Connectors\WikidataFirmsConnector;
use App\Modules\Adp\Connectors\AdpEnvironmentConnector;
use App\Modules\Adp\Connectors\AdpLabourConnector;
use App\Modules\Adp\Connectors\AdpPopulationConnector;
use App\Modules\Companies\Connectors\GleifLeiCountsConnector;
use App\Modules\Companies\Connectors\GleifOmanConnector;
use App\Modules\Comtrade\Connectors\ComtradeProductsConnector;
use App\Modules\Fao\Connectors\FaoFoodSecurityConnector;
use App\Modules\Fao\Connectors\FaoProductionConnector;
use App\Modules\Gcc\Connectors\GccStatEconomyConnector;
use App\Modules\Gcc\Connectors\GccStatEnergyTourismConnector;
use App\Modules\Gcc\Connectors\GccStatMoreConnector;
use App\Modules\Gcc\Connectors\GccStatSocietyConnector;
use App\Modules\Ilo\Connectors\IloLabourConnector;
use App\Modules\Macro\Connectors\ImfAiReadinessConnector;
use App\Modules\Macro\Connectors\ImfCpiConnector;
use App\Modules\Macro\Connectors\ImfCtotConnector;
use App\Modules\Macro\Connectors\ImfMonthlyTradeConnector;
use App\Modules\Macro\Connectors\ImfDebtDatabaseConnector;
use App\Modules\Macro\Connectors\ImfFiscalMonitorConnector;
use App\Modules\Macro\Connectors\ImfFiscalPolicyConnector;
use App\Modules\Macro\Connectors\ImfFossilSubsidiesConnector;
use App\Modules\Macro\Connectors\ImfIipConnector;
use App\Modules\Macro\Connectors\ImfNdGainConnector;
use App\Modules\Macro\Connectors\ImfReservesConnector;
use App\Modules\Macro\Connectors\ImfWeoConnector;
use App\Modules\Manafeth\Connectors\ManafethPartnersConnector;
use App\Modules\Manafeth\Connectors\ManafethTotalsConnector;
use App\Modules\Markets\Connectors\EiaSpotPricesConnector;
use App\Modules\Markets\Connectors\FrankfurterFxConnector;
use App\Modules\Markets\Connectors\ImfCommodityPricesConnector;
use App\Modules\Markets\Connectors\UsTreasuryYieldsConnector;
use App\Modules\Markets\Connectors\WorldBankPinkSheetConnector;
use App\Modules\Ncsi\Connectors\NcsiBalanceOfPaymentsConnector;
use App\Modules\Ncsi\Connectors\NcsiCommunicationConnector;
use App\Modules\Ncsi\Connectors\NcsiElectricityConnector;
use App\Modules\Ncsi\Connectors\NcsiFoodSecurityConnector;
use App\Modules\Ncsi\Connectors\NcsiHealthSpendingConnector;
use App\Modules\Ncsi\Connectors\NcsiLabourConnector;
use App\Modules\Ncsi\Connectors\NcsiMonetaryConnector;
use App\Modules\Ncsi\Connectors\NcsiNationalAccountsConnector;
use App\Modules\Ncsi\Connectors\NcsiOilGasConnector;
use App\Modules\Ncsi\Connectors\NcsiPopulationConnector;
use App\Modules\Ncsi\Connectors\NcsiPricesConnector;
use App\Modules\Ncsi\Connectors\NcsiProducerPricesConnector;
use App\Modules\Ncsi\Connectors\NcsiRenewableEnergyConnector;
use App\Modules\Ncsi\Connectors\NcsiTourismConnector;
use App\Modules\Oec\Connectors\OecComplexityConnector;
use App\Modules\Oec\Connectors\OecProductsConnector;
use App\Modules\Owid\Connectors\OwidEnergyConnector;
use App\Modules\Bis\Connectors\BisStatisticsConnector;
use App\Modules\Irena\Connectors\IrenaElectricityConnector;
use App\Modules\Owid\Connectors\OwidFossilFuelsConnector;
use App\Modules\Owid\Connectors\OwidIndicesConnector;
use App\Modules\Undp\Connectors\UndpHdrConnector;
use App\Modules\Unesco\Connectors\UnescoUisConnector;
use App\Modules\Who\Connectors\WhoHealthConnector;
use App\Modules\Wits\Connectors\WitsTradeConnector;
use App\Modules\WorldBank\Connectors\WorldBankBankingConnector;
use App\Modules\WorldBank\Connectors\WorldBankDigitalConnector;
use App\Modules\WorldBank\Connectors\WorldBankEnvironmentConnector;
use App\Modules\WorldBank\Connectors\WorldBankExtraConnector;
use App\Modules\WorldBank\Connectors\WorldBankFindexConnector;
use App\Modules\WorldBank\Connectors\WorldBankIctConnector;
use App\Modules\WorldBank\Connectors\WorldBankInvestmentConnector;
use App\Modules\WorldBank\Connectors\WorldBankPurchasingPowerConnector;
use App\Modules\WorldBank\Connectors\WorldBankResourcesConnector;
use App\Modules\WorldBank\Connectors\WorldBankFinanceConnector;
use App\Modules\WorldBank\Connectors\WorldBankGovernanceConnector;
use App\Modules\WorldBank\Connectors\WorldBankHealthConnector;
use App\Modules\WorldBank\Connectors\WorldBankInnovationConnector;
use App\Modules\WorldBank\Connectors\WorldBankLabourConnector;
use App\Modules\WorldBank\Connectors\WorldBankMacroConnector;
use App\Modules\WorldBank\Connectors\WorldBankProjectionsConnector;
use App\Modules\WorldBank\Connectors\WorldBankSocietyConnector;
use App\Modules\WorldBank\Connectors\WorldBankSustainabilityConnector;
use App\Modules\WorldBank\Connectors\WorldBankTourismConnector;
use App\Modules\WorldBank\Connectors\WorldBankTradeConnector;
use App\Modules\WorldBank\Connectors\WorldBankWealthConnector;

return [
    // رمز وكيل الذكاء الاصطناعي لواجهة /api/agent (فارغ = معطّلة).
    'agent_token' => env('AGENT_TOKEN', ''),

    'display_timezone' => 'Asia/Muscat',

    // كلمات مرور أدوار قاعدة البيانات (تُنشأ في الترحيل الأول).
    'db_roles' => [
        'app_reader' => env('DB_APP_READER_PASSWORD', 'reader_dev'),
        'ingest_writer' => env('DB_INGEST_WRITER_PASSWORD', 'ingest_dev'),
        'promoter' => env('DB_PROMOTER_PASSWORD', 'promoter_dev'),
    ],

    // الموصلات المسجّلة. إضافة مصدر جديد = كلاس ينفّذ Connector + سطر هنا.
    'connectors' => [
        ImfWeoConnector::class,
        ImfFiscalMonitorConnector::class,
        ImfFiscalPolicyConnector::class,
        ImfDebtDatabaseConnector::class,
        ImfReservesConnector::class,
        ImfAiReadinessConnector::class,
        GccStatEconomyConnector::class,
        GccStatSocietyConnector::class,
        GccStatMoreConnector::class,
        GccStatEnergyTourismConnector::class,
        WorldBankMacroConnector::class,
        WorldBankTradeConnector::class,
        WorldBankSocietyConnector::class,
        WorldBankDigitalConnector::class,
        WorldBankHealthConnector::class,
        WorldBankEnvironmentConnector::class,
        WorldBankTourismConnector::class,
        WorldBankFinanceConnector::class,
        WorldBankLabourConnector::class,
        WorldBankGovernanceConnector::class,
        NcsiPricesConnector::class,
        NcsiNationalAccountsConnector::class,
        NcsiOilGasConnector::class,
        NcsiCommunicationConnector::class,
        NcsiBalanceOfPaymentsConnector::class,
        NcsiLabourConnector::class,
        NcsiMonetaryConnector::class,
        NcsiTourismConnector::class,
        NcsiElectricityConnector::class,
        NcsiRenewableEnergyConnector::class,
        NcsiHealthSpendingConnector::class,
        NcsiFoodSecurityConnector::class,
        NcsiPopulationConnector::class,
        NcsiProducerPricesConnector::class,
        ManafethTotalsConnector::class,
        WitsTradeConnector::class,
        UndpHdrConnector::class,
        OsmPlacesConnector::class,
        GleifOmanConnector::class,
        AdpEnvironmentConnector::class,
        AdpLabourConnector::class,
        AdpPopulationConnector::class,
        WikidataFirmsConnector::class,
        GleifLeiCountsConnector::class,
        ManafethPartnersConnector::class,
        ImfCommodityPricesConnector::class,
        UsTreasuryYieldsConnector::class,
        FrankfurterFxConnector::class,
        EiaSpotPricesConnector::class,
        WorldBankPinkSheetConnector::class,
        IloLabourConnector::class,
        WhoHealthConnector::class,
        ImfFossilSubsidiesConnector::class,
        ImfNdGainConnector::class,
        ImfIipConnector::class,
        ImfCtotConnector::class,
        ImfCpiConnector::class,
        ImfMonthlyTradeConnector::class,
        OpenMeteoClimateConnector::class,
        UsgsEarthquakesConnector::class,
        WorldBankBankingConnector::class,
        WorldBankSustainabilityConnector::class,
        WorldBankInnovationConnector::class,
        WorldBankWealthConnector::class,
        WorldBankExtraConnector::class,
        WorldBankPurchasingPowerConnector::class,
        WorldBankInvestmentConnector::class,
        WorldBankResourcesConnector::class,
        WorldBankFindexConnector::class,
        WorldBankIctConnector::class,
        WorldBankProjectionsConnector::class,
        OwidEnergyConnector::class,
        OwidIndicesConnector::class,
        OwidFossilFuelsConnector::class,
        IrenaElectricityConnector::class,
        BisStatisticsConnector::class,
        OecComplexityConnector::class,
        OecProductsConnector::class,
        FaoFoodSecurityConnector::class,
        FaoProductionConnector::class,
        UnescoUisConnector::class,
        ComtradeProductsConnector::class,
    ],

    // رموز كيانات يستعملها مصدر ويستعمل غيره رمزًا آخر لنفس الكيان. المفتاح (الشاذ) => الرمز الموحَّد.
    'entity_aliases' => [
        'UVK' => 'XKX', // كوسوفو: IMF مقابل البنك الدولي
        'WBG' => 'PSE', // الضفة الغربية وغزة: IMF مقابل البنك الدولي
        'KOS' => 'XKX', // كوسوفو: WHO وILO مقابل البنك الدولي
    ],

    // رموز دول منافذ (تقترب من ISO numeric القديم) التي لا يترجمها مرجع الأمم المتحدة صحيحًا: مفقودة منه
    // (أمريكا 840، فرنسا 250، النرويج 578، سويسرا 756…) أو تشير فيه إلى كيان منتهٍ (200 تشيكوسلوفاكيا، 720 اليمن الجنوبي).
    // المفتاح: رمز منافذ الرقمي => ISO3 الصحيح. تحقّقتُ منها من أسماء منافذ نفسها (ENG_DESC).
    'manafeth_country_overrides' => [
        16 => 'ASM', 162 => 'CXR', 200 => 'CZE', 250 => 'FRA', 316 => 'GUM', 438 => 'LIE', 492 => 'MCO', 529 => 'ABW',
        578 => 'NOR', 581 => 'UMI', 630 => 'PRI', 720 => 'YEM', 732 => 'ESH', 756 => 'CHE', 831 => 'GGY', 832 => 'JEY',
        840 => 'USA', 856 => 'PLW', 901 => 'TWN', 999 => 'PSE',
        512 => 'OFZ', // «عُمان – المناطق الحرة»: تدفقات مع مناطق حرة داخل عُمان؛ كيان مستقل لا يُدمج مع دولة عُمان
        0 => 'ZZZ', // «غير محدد»: صادرات/واردات بلا دولة منشأ أو وجهة معلنة (نحو 8% من صادرات 2022)؛ تُحفظ ككيان مستقل كي تتطابق المجاميع
    ],

    'ingest' => [
        // تأخير إعادة المحاولة بالدقائق بعد كل فشل متتالٍ (يُقصّ عند آخر عنصر).
        'retry_backoff_minutes' => [5, 15, 60, 240],
        // عدد الإخفاقات المتتالية قبل اعتبار المجموعة failing وإرسال تنبيه.
        'failures_before_alert' => 3,
        // المجموعة تُعد متأخرة (stale) إذا تجاوز عمر آخر نجاح دورتها × هذا المعامل.
        'stale_factor' => 1.5,
        'http_timeout_seconds' => 60,
        // GLEIF: الحد المعلن 60 طلبًا/دقيقة.
        'gleif_throttle_microseconds' => 1_100_000,
        // بين طلبات pivot في بوابة data.gov.om: تخنق الطلبات المتتابعة السريعة.
        'knoema_throttle_microseconds' => 1_200_000,
        'user_agent' => 'OmaniLab/1.0 (+economic-data-monitor)',
    ],

    'alerts' => [
        // لا يتكرر نفس التنبيه لنفس المجموعة قبل مرور هذه المدة.
        'dedupe_hours' => 6,
        'digest_time' => '07:00', // بتوقيت مسقط
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
];
