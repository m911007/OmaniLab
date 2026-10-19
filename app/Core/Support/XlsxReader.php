<?php

namespace App\Core\Support;

use RuntimeException;
use ZipArchive;

/**
 * قارئ xlsx مبسّط بلا اعتماديات: يفتح الملف كـZIP ويقرأ الجداول والنصوص المشتركة. يكفي للجداول البسيطة المنشورة (قيم وسلاسل فقط، بلا صيغ).
 * الاستخدام: (new XlsxReader($path))->sheet('اسم الورقة') يعيد صفوفًا، كل صف مصفوفة [حرف العمود => قيمة].
 */
final class XlsxReader
{
    private ZipArchive $zip;

    /** @var list<string> */
    private array $strings = [];

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException('تعذّر فتح ملف xlsx.');
        }
        $shared = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($shared !== false && preg_match_all('#<si>(.*?)</si>#s', $shared, $m)) {
            foreach ($m[1] as $si) {
                $this->strings[] = html_entity_decode(strip_tags($si), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths());
    }

    /** @return array<int, array<string, string|null>> رقم الصف => [حرف العمود => قيمة] */
    public function sheet(string $name): array
    {
        $path = $this->sheetPaths()[$name] ?? throw new RuntimeException("xlsx: لا ورقة باسم {$name}.");
        $xml = $this->zip->getFromName($path);
        if ($xml === false) {
            throw new RuntimeException("xlsx: تعذّر قراءة {$path}.");
        }
        $rows = [];
        preg_match_all('#<row [^>]*?r="(\d+)"[^>]*?(?:/>|>(.*?)</row>)#s', $xml, $rm, PREG_SET_ORDER);
        foreach ($rm as $row) {
            $cells = [];
            if (isset($row[2]) && preg_match_all('#<c r="([A-Z]+)\d+"([^>]*?)(?:/>|>(.*?)</c>)#s', $row[2], $cm, PREG_SET_ORDER)) {
                foreach ($cm as $c) {
                    $value = null;
                    if (isset($c[3]) && preg_match('#<v>(.*?)</v>#s', $c[3], $v)) {
                        $value = str_contains($c[2], 't="s"') ? ($this->strings[(int) $v[1]] ?? null) : html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    } elseif (isset($c[3]) && preg_match('#<t[^>]*>(.*?)</t>#s', $c[3], $t)) {
                        $value = html_entity_decode($t[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                    $cells[$c[1]] = $value;
                }
            }
            $rows[(int) $row[1]] = $cells;
        }

        return $rows;
    }

    /** @return array<string, string> اسم الورقة => مسارها داخل الأرشيف */
    private function sheetPaths(): array
    {
        $wb = $this->zip->getFromName('xl/workbook.xml');
        $rels = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            throw new RuntimeException('xlsx: ملف غير صالح.');
        }
        $targets = [];
        preg_match_all('#<Relationship [^>]*?Id="([^"]+)"[^>]*?Target="([^"]+)"#', $rels, $rm, PREG_SET_ORDER);
        foreach ($rm as $r) {
            $targets[$r[1]] = 'xl/'.ltrim($r[2], '/');
        }
        preg_match_all('#<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"#', $wb, $sm, PREG_SET_ORDER);
        $out = [];
        foreach ($sm as $s) {
            $out[html_entity_decode($s[1], ENT_QUOTES | ENT_XML1, 'UTF-8')] = $targets[$s[2]] ?? '';
        }

        return $out;
    }
}
