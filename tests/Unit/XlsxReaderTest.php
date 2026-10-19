<?php

namespace Tests\Unit;

use App\Core\Support\XlsxReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxReaderTest extends TestCase
{
    public function test_it_reads_shared_strings_and_numbers_by_sheet_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>Oman</t></si><si><t>Country</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>1</v></c><c r="B1"><v>2024</v></c></row><row r="2"><c r="A2" t="s"><v>0</v></c><c r="B2"><v>5.5E-2</v></c><c r="C2"/></row></sheetData></worksheet>');
        $zip->close();

        $book = new XlsxReader($path);
        $rows = $book->sheet('Data');
        unlink($path);

        $this->assertSame(['Data'], $book->sheetNames());
        $this->assertSame('Country', $rows[1]['A']);
        $this->assertSame('Oman', $rows[2]['A']);
        $this->assertSame('5.5E-2', $rows[2]['B']);
        $this->assertNull($rows[2]['C']);
    }
}
