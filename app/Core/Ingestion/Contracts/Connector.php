<?php

namespace App\Core\Ingestion\Contracts;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;

/**
 * عقد مصدر البيانات. إضافة مصدر جديد = كلاس واحد ينفّذ هذا العقد
 * ويُسجَّل في config/omanilab.php تحت 'connectors'.
 */
interface Connector
{
    /**
     * طابور الجلب: الافتراضي «ingest». المصادر الثقيلة (مئات الاستدعاءات، دقائق طويلة) تعيد «ingest-heavy»
     * كي لا تؤخر الخفيفة (حجب الرأس الطويل).
     */
    public function queue(): string;

    /** تعريف المجموعة والمؤشرات وقواعد التحقق (يُزامَن إلى قاعدة البيانات). */
    public function definition(): DatasetDefinition;

    /**
     * مراجعة منطق التحليل. يرفعها المطوّر كلما تغيّر ما ينتجه parse() أو fetch() من البيانات نفسها
     * (مؤشر جديد، سمة جديدة…)، فتُعاد المعالجة ولو لم يتغير المصدر. تدخل في بصمة الفحص وبصمة الحمولة.
     */
    public function revision(): int;

    /** فحص رخيص: هل صدر شيء جديد؟ لا يجلب البيانات نفسها. */
    public function probe(): ProbeResult;

    /** جلب الحمولة الكاملة كما هي من المصدر. */
    public function fetch(): RawPayload;

    /**
     * تحويل الحمولة الخام إلى سجلات موحّدة.
     *
     * @return iterable<ParsedRecord|ParsedEntity|ParsedAttribute|ParsedRelation>
     */
    public function parse(RawPayload $payload): iterable;
}
