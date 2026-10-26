<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * مخزن مفاتيح الخدمات الخارجية: القيمة المخزَّنة في app.secrets (مشفَّرة) تتقدم على متغير البيئة الاحتياطي في .env.
 * لا تُسجَّل القيم ولا تُعاد في أي رد.
 */
final class Secrets
{
    /**
     * kind: secret (تُخفى ولا تعود للمتصفح) | text (قيمة غير حساسة مثل معرّف محادثة، تُعرض كما هي).
     * pattern: صيغة القيمة المقبولة؛ example: مثال للصيغة يظهر في رسالة الخطأ.
     *
     * @var array<string, array{label: string, group: string, kind: string, env_config: string, help: string, enables: string, url: ?string, pattern: string, example: string}>
     */
    public const NAMES = [
        'anthropic_api_key' => [
            'label' => 'مفتاح Anthropic (الذكاء الاصطناعي)', 'group' => 'ذكاء الأسواق', 'kind' => 'secret', 'env_config' => 'omanilab.ai.key',
            'help' => 'للتوصيات الذكية وتحديد رمز المنتج وأسعار الاشتراكات.', 'enables' => 'التحليل الذكي والتوصيات في صفحة ذكاء الأسواق',
            'url' => 'https://console.anthropic.com/settings/keys', 'pattern' => '/^[\x21-\x7E]{16,300}$/', 'example' => 'sk-ant-…',
        ],
        'serpapi_key' => [
            'label' => 'مفتاح SerpAPI (أسعار المتاجر)', 'group' => 'ذكاء الأسواق', 'kind' => 'secret', 'env_config' => 'omanilab.prices.serpapi_key',
            'help' => 'لعروض Google Shopping الحية بين متاجر الدول.', 'enables' => 'مقارنة أسعار المتاجر الإلكترونية الحية',
            'url' => 'https://serpapi.com/manage-api-key', 'pattern' => '/^[\x21-\x7E]{16,300}$/', 'example' => 'مفتاح من 40–64 حرفًا',
        ],
        'telegram_bot_token' => [
            'label' => 'رمز بوت تيليغرام', 'group' => 'التنبيهات', 'kind' => 'secret', 'env_config' => 'omanilab.telegram.token',
            'help' => 'يرسل البوت إشارات السوق والملخص اليومي وتنبيهات تعطّل المصادر. أنشئ البوت من BotFather وانسخ رمزه.', 'enables' => 'إرسال التنبيهات والملخص اليومي إلى تيليغرام (مع معرّف المحادثة)',
            'url' => 'https://t.me/BotFather', 'pattern' => '/^\d{6,}:[A-Za-z0-9_-]{30,}$/', 'example' => '123456789:AAF…',
        ],
        'telegram_chat_id' => [
            'label' => 'معرّف محادثة تيليغرام', 'group' => 'التنبيهات', 'kind' => 'text', 'env_config' => 'omanilab.telegram.chat_id',
            'help' => 'المحادثة أو القناة التي تصلها التنبيهات: رقم (قد يبدأ بـ −) أو @اسم_القناة. أضِف البوت إليها أولًا.', 'enables' => 'وجهة التنبيهات في تيليغرام',
            'url' => 'https://t.me/userinfobot', 'pattern' => '/^(-?\d{5,20}|@[A-Za-z0-9_]{5,64})$/', 'example' => '-1001234567890 أو @my_channel',
        ],
        'bls_api_key' => [
            'label' => 'مفتاح تسجيل BLS (إحصاءات العمل الأمريكية)', 'group' => 'مصادر البيانات', 'kind' => 'secret', 'env_config' => 'omanilab.keys.bls',
            'help' => 'مجاني. يرفع حد الجلب اليومي من 25 إلى 500 طلب ويتيح سلاسل أطول، فيكتمل تاريخ التضخم والأجور والأسعار الأمريكية وأسعار المنتجين لسلاسل القيمة.', 'enables' => 'جلب أعمق وأسرع لبيانات BLS (v2)',
            'url' => 'https://data.bls.gov/registrationEngine/', 'pattern' => '/^[A-Fa-f0-9]{32}$/', 'example' => '32 خانة من الأرقام وa–f',
        ],
        'comtrade_api_key' => [
            'label' => 'مفتاح اشتراك UN Comtrade', 'group' => 'مصادر البيانات', 'kind' => 'secret', 'env_config' => 'omanilab.keys.comtrade',
            'help' => 'مجاني بعد التسجيل. الواجهة العامة بلا مفتاح محدودة جدًا (تنفد حصتها ويُسمح بفترة واحدة في الطلب)، والمفتاح يرفع السقف إلى آلاف الطلبات يوميًا.', 'enables' => 'أسعار وحدة الاستيراد والتصدير، وتجارة المنتجات، وتحليل الأسواق دون انقطاع الحصة',
            'url' => 'https://comtradedeveloper.un.org/', 'pattern' => '/^[A-Za-z0-9]{24,64}$/', 'example' => '32 خانة تقريبًا (حروف وأرقام)',
        ],
    ];

    public static function get(string $name): string
    {
        try {
            $row = DB::table('app.secrets')->where('name', $name)->value('value');
            if (is_string($row) && $row !== '') {
                return Crypt::decryptString($row);
            }
        } catch (Throwable) {
            // الجدول غير موجود بعد أو المفتاح تغيّر: نرجع للاحتياطي.
        }

        return (string) config(self::NAMES[$name]['env_config'] ?? '');
    }

    public static function has(string $name): bool
    {
        return self::get($name) !== '';
    }

    public static function put(string $name, string $value, ?int $userId): void
    {
        DB::table('app.secrets')->updateOrInsert(['name' => $name], ['value' => Crypt::encryptString($value), 'hint' => mb_substr($value, -4), 'updated_by' => $userId, 'updated_at' => now()]);
    }

    public static function forget(string $name): void
    {
        DB::table('app.secrets')->where('name', $name)->delete();
    }

    /** @return list<array{name: string, label: string, group: string, kind: string, help: string, enables: string, url: ?string, example: string, configured: bool, source: ?string, hint: ?string, value: ?string, updated_at: ?string}> */
    public static function status(): array
    {
        $rows = DB::table('app.secrets')->get()->keyBy('name');
        $out = [];
        foreach (self::NAMES as $name => $meta) {
            $row = $rows->get($name);
            $env = (string) config($meta['env_config']) !== '';
            // القيمة تُعاد فقط للنوع text (غير حساس)؛ الأسرار لا تُعاد أبدًا، يُعاد آخر 4 أحرف تلميحًا
            $value = $meta['kind'] === 'text' ? self::get($name) : null;
            $out[] = [
                'name' => $name, 'label' => $meta['label'], 'group' => $meta['group'], 'kind' => $meta['kind'], 'help' => $meta['help'], 'enables' => $meta['enables'],
                'url' => $meta['url'], 'example' => $meta['example'], 'configured' => $row !== null || $env, 'source' => $row ? 'db' : ($env ? 'env' : null),
                'hint' => $meta['kind'] === 'secret' ? $row?->hint : null, 'value' => $value !== '' ? $value : null, 'updated_at' => $row?->updated_at,
            ];
        }

        return $out;
    }
}
