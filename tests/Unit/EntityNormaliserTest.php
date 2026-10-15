<?php

namespace Tests\Unit;

use App\Core\Ingestion\EntityNormaliser;
use Tests\TestCase;

class EntityNormaliserTest extends TestCase
{
    public function test_aliased_codes_collapse_to_one_canonical_entity(): void
    {
        $n = new EntityNormaliser;

        $this->assertSame('XKX', $n->code('UVK'));
        $this->assertSame('PSE', $n->code(' wbg '));
        $this->assertSame('OMN', $n->code('omn'));
    }

    public function test_names_are_cleaned_of_noise_and_shouting_case(): void
    {
        $n = new EntityNormaliser;

        $this->assertSame('Curacao', $n->name('CURACAO'));
        $this->assertSame('Northern Mariana Islands', $n->name("NORTHERN  MARIANA\u{00A0}ISLANDS"));
        $this->assertSame('St. Martin (French part)', $n->name('St. Martin (French part)'));
        $this->assertSame('GCC', $n->name('GCC'), 'الاختصار القصير يبقى');
        $this->assertSame('Sint Maarten (Dutch part)', $n->name('SINT MAARTEN (DUTCH PART)'));
    }

    public function test_arabic_names_come_from_icu_for_countries_and_a_table_for_aggregates(): void
    {
        $n = new EntityNormaliser;

        $this->assertSame('عُمان', $n->arabicName('OMN', 'OM'));
        $this->assertSame('العالم', $n->arabicName('WLD'));
        $this->assertNull($n->arabicName('QQQ'), 'لا اسم موثوقًا بلا ISO2');
        $this->assertNull($n->arabicName('QQQ', 'ZZ'), 'منطقة غير معروفة لا تُقبل');
    }
}
