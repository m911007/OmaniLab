<?php

namespace App\Modules\Mafwr\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Support\XlsxReader;
use RuntimeException;
use Throwable;

/**
 * أساس موصّلات البيانات المفتوحة لوزارة الثروة الزراعية والسمكية وموارد المياه (mafwr.gov.om/public/open-data)، برخصة البيانات الحكومية المفتوحة.
 * قائمة المجموعات من واجهة المحتوى العامة، والملفات بمفتاح تنزيل عام يصدره الموقع لكل زائر (ACTION_DOWNLOAD عبر gRPC-web).
 */
abstract class MafwrConnector extends ApiConnector
{
    protected const API = 'https://apis.mafwr.gov.om';

    protected const LIST = '/mafwr-cms-be/api/collections/data_sets/search/records?sort=-updated&search=&filter=&expand=metadata&page=1&perPage=200';

    protected const TOKEN = '/mafwr/grpc-web/public-permit/v1/tes.permit.PublicPermit/PublicFileToken';

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    protected function source(): SourceDefinition
    {
        return new SourceDefinition(
            key: 'mafwr',
            nameAr: 'وزارة الثروة الزراعية والسمكية وموارد المياه',
            nameEn: 'Ministry of Agriculture, Fisheries Wealth and Water Resources',
            publisher: 'MAFWR',
            url: 'https://www.mafwr.gov.om/public/open-data',
            license: 'رخصة البيانات الحكومية المفتوحة — سلطنة عُمان',
        );
    }

    /**
     * أحدث ملف xlsx لمجموعة من نوع معيّن يحوي عنوانها كلمة معيّنة؛ إن تعذّر تنزيل الأحدث يُجرَّب الذي قبله.
     *
     * @param  callable(string, array<string, mixed>): mixed  $read  يستقبل مسار الملف ومجموعة البيانات ويعيد ما استُخرج
     */
    protected function latestFile(string $type, string $titleContains, callable $read): mixed
    {
        $items = array_values(array_filter($this->datasets(), fn (array $i) => $i['type'] === $type
            && str_contains($this->squash($i['title_ar']), $this->squash($titleContains))
            && str_ends_with((string) ($i['expand']['metadata']['file_name'] ?? ''), '.xlsx')));
        $token = $this->fileToken();
        foreach ($items as $item) {
            try {
                $path = $this->download($item['expand']['metadata']['url'], $token);
                try {
                    return $read($path, $item);
                } finally {
                    @unlink($path);
                }
            } catch (Throwable $e) {
                logger()->warning("[mafwr] {$item['title_ar']}: {$e->getMessage()}");
            }
        }

        throw new RuntimeException("MAFWR: تعذّر قراءة أي ملف «{$titleContains}».");
    }

    /**
     * صفوف ورقة كمصفوفات بعناوين الأعمدة (بعد حذف المسافات)، بدءًا من أول صف يحوي العنوان المطلوب.
     *
     * @return list<array<string, string|null>>
     */
    protected function table(XlsxReader $x, string $mustHave, ?string $sheet = null): array
    {
        $rows = $x->sheet($sheet ?? $x->sheetNames()[0]);
        $header = null;
        $out = [];
        foreach ($rows as $cells) {
            if ($header === null) {
                $squashed = array_map(fn ($v) => $this->squash((string) $v), $cells);
                if (in_array($this->squash($mustHave), $squashed, true)) {
                    $header = $squashed;
                }

                continue;
            }
            $row = [];
            foreach ($header as $col => $name) {
                if ($name !== '') {
                    $row[$name] = $cells[$col] ?? null;
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    protected function squash(string $s): string
    {
        return self::squashText($s);
    }

    /**
     * يوحّد النص العربي للمقارنة: يركّب الحروف المفكَّكة (الألف + علامة الهمزة تصير «أ») ويحذف التطويل والتشكيل والرموز غير المرئية والمسافات.
     * يأتي بعض العناوين من الموقع بالصيغة المفكَّكة فلا تطابق نصًّا مكتوبًا بالصيغة المركَّبة.
     */
    public static function squashText(string $s): string
    {
        $s = strtr(self::compose($s), ["\u{0640}" => '']);

        return (string) preg_replace('/[\s\p{Cf}\p{Mn}]+/u', '', $s);
    }

    /** الصيغة المركَّبة للحروف العربية ذات الهمزة والمدّة (بلا اعتماد على امتداد intl). */
    public static function compose(string $s): string
    {
        return strtr($s, [
            "\u{0627}\u{0654}" => "\u{0623}", "\u{0627}\u{0655}" => "\u{0625}", "\u{0627}\u{0653}" => "\u{0622}",
            "\u{0648}\u{0654}" => "\u{0624}", "\u{064A}\u{0654}" => "\u{0626}", "\u{06C1}\u{0654}" => "\u{06C2}",
        ]);
    }

    protected function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    protected function year(mixed $v): ?string
    {
        return is_numeric($v) && (int) $v >= 1900 && (int) $v <= 2100 ? (string) (int) $v : null;
    }

    /** @return list<array<string, mixed>> */
    protected function datasets(): array
    {
        return $this->getJson(self::API.self::LIST)['items'] ?? [];
    }

    /** مفتاح تنزيل عام (ACTION_DOWNLOAD = 1) كما يطلبه الموقع لكل زائر. */
    protected function fileToken(): string
    {
        $res = $this->http()->withHeaders(['Content-Type' => 'application/grpc-web+proto', 'X-Grpc-Web' => '1', 'Origin' => 'https://www.mafwr.gov.om'])
            ->withBody("\x00\x00\x00\x00\x02\x08\x01", 'application/grpc-web+proto')->post(self::API.self::TOKEN)->throw();
        $body = $res->body();
        $msg = substr($body, 5, (int) unpack('N', substr($body, 1, 4))[1]);
        if (($msg[0] ?? '') !== "\x0a") {
            throw new RuntimeException('MAFWR: استجابة غير متوقعة لمفتاح التنزيل.');
        }
        $len = 0;
        $shift = 0;
        $i = 1;
        do {
            $byte = ord($msg[$i++]);
            $len |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte >= 0x80);

        return substr($msg, $i, $len);
    }

    protected function download(string $url, string $token): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'mafwr');
        $this->http()->timeout(90)->sink($path)->get($url.(str_contains($url, '?') ? '&' : '?').'token='.$token)->throw();

        return $path;
    }
}
