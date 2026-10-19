import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines } from '@/core/groups';

const DAILY: readonly RangeOption[] = [
  { value: '3m', label: '3m', months: 3 },
  { value: '1y', label: '1y', months: 12 },
  { value: '3y', label: '3y', months: 36 },
  { value: 'all', label: 'الكل', months: 0 },
];
const MONTHLY: readonly RangeOption[] = [
  { value: '3y', label: '3y', months: 36 },
  { value: 'all', label: 'الكل', months: 0 },
];
const H = 340;

const choke = (id: number, label: string, indicator = 'portwatch.chokepoint_transits') => ({ indicator, entity: `PW_CHOKEPOINT${id}`, label });
const PORTS: [string, string][] = [['port988', 'صحار'], ['port746', 'صلالة'], ['port984', 'الدقم'], ['port745', 'ميناء السلطان قابوس'], ['port1068', 'محطة قلهات للغاز المسال']];
const port = (indicator: string, ids = PORTS) => ids.map(([id, label]) => ({ indicator, entity: `PW_${id.toUpperCase()}`, label }));

export default function MaritimePage() {
  return (
    <>
      <PageHead
        title="الملاحة والموانئ: هرمز وباب المندب وموانئ عُمان"
        lead="حركة السفن عبر أهم الممرات البحرية في العالم وموانئ عُمان، من منصة PortWatch التابعة لصندوق النقد الدولي (تقديرات مبنية على إشارات تتبع السفن AIS، تُحدَّث يوميًا وتُراجَع). مضيق هرمز تمر منه نحو خُمس تجارة النفط العالمية، وموقع عُمان على بحر العرب وخارج المضيق يمنح موانئها قيمة استراتيجية."
      />

      <div className="grid cols-4">
        <KeyStat entity="PW_CHOKEPOINT6" indicator="portwatch.chokepoint_transits" label="عبور مضيق هرمز (سفينة/يوم)" />
        <KeyStat entity="PW_CHOKEPOINT6" indicator="portwatch.chokepoint_tankers" label="ناقلات هرمز (سفينة/يوم)" />
        <KeyStat entity="PW_CHOKEPOINT4" indicator="portwatch.chokepoint_transits" label="عبور باب المندب (سفينة/يوم)" />
        <KeyStat entity="PW_CHOKEPOINT1" indicator="portwatch.chokepoint_transits" label="عبور قناة السويس (سفينة/يوم)" />
        <KeyStat entity="PW_PORT746" indicator="portwatch.port_calls" label="زيارات ميناء صلالة (شهريًا)" />
        <KeyStat entity="PW_PORT988" indicator="portwatch.port_calls" label="زيارات ميناء صحار (شهريًا)" />
        <KeyStat entity="PW_PORT984" indicator="portwatch.port_calls" label="زيارات ميناء الدقم (شهريًا)" />
        <KeyStat entity="OMN" indicator="portwatch.country_port_calls" label="زيارات الموانئ العُمانية (شهريًا)" />
      </div>

      <Section title="مضيق هرمز" lead="بوابة النفط الخليجي: عدد السفن عبر المضيق يوميًا وناقلات النفط منها وحمولتها. اضطراب الحركة هنا يرفع أسعار النفط والتأمين البحري فورًا.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="1y" title="عبور السفن في مضيق هرمز" note="الانخفاض الحاد منذ مارس 2026 كما تنشره المنصة؛ قد يعكس اضطرابًا فعليًا في الملاحة أو نقصًا في تغطية إشارات AIS، فلا يُقرأ وحده دليلًا على أحدهما." subtitle="سفينة/يوم — كل الأنواع وناقلات النفط" lines={[choke(6, 'كل السفن'), choke(6, 'الناقلات', 'portwatch.chokepoint_tankers')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="1y" title="حمولة السفن العابرة هرمز" subtitle="طن حمولة" lines={[choke(6, 'الحمولة', 'portwatch.chokepoint_capacity')]} />
      </Section>

      <Section title="الممرات البحرية الأخرى" lead="مقارنة حركة السفن اليومية بين أهم الممرات: ماذا يحدث لقناة السويس وباب المندب مقابل رأس الرجاء الصالح؟ (تحوّل الملاحة حول أفريقيا يعني تعثر الممر الأحمر).">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="3y" title="باب المندب وقناة السويس ورأس الرجاء الصالح" subtitle="سفينة/يوم" lines={[choke(4, 'باب المندب'), choke(1, 'قناة السويس'), choke(7, 'رأس الرجاء الصالح')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="3y" title="هرمز مقابل ملقا" subtitle="سفينة/يوم" lines={[choke(6, 'هرمز'), choke(5, 'ملقا')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="3y" title="قناة بنما والبوسفور" subtitle="سفينة/يوم" lines={[choke(2, 'بنما'), choke(3, 'البوسفور')]} />
      </Section>

      <Section title="موانئ عُمان" lead="زيارات السفن وأحجام الاستيراد والتصدير (طن) شهريًا في أهم الموانئ والمحطات العُمانية.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="all" title="زيارات السفن للموانئ العُمانية" subtitle="زيارة/شهر" lines={port('portwatch.port_calls')} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="واردات الموانئ العُمانية" subtitle="طن/شهر" lines={port('portwatch.port_imports', PORTS.slice(0, 4))} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="صادرات الموانئ العُمانية" subtitle="طن/شهر" lines={port('portwatch.port_exports')} />
      </Section>

      <Section title="عُمان ودول الخليج" lead="مجاميع الموانئ لكل دولة شهريًا.">
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="زيارات السفن لموانئ دول الخليج" subtitle="زيارة/شهر" lines={gccLines('portwatch.country_port_calls')} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="الواردات البحرية لدول الخليج" subtitle="طن/شهر" lines={gccLines('portwatch.country_imports')} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="الصادرات البحرية لدول الخليج" subtitle="طن/شهر" lines={gccLines('portwatch.country_exports')} />
        <WorldRankCard title="أكبر الدول في زيارات السفن" indicator="portwatch.country_port_calls" period="2026-09" limit={15} />
        <WorldRankCard title="أكبر الدول في الصادرات البحرية" indicator="portwatch.country_exports" period="2026-09" limit={15} />
      </Section>

      <Section title="البحر قبالة السواحل العُمانية" lead="حرارة سطح البحر وارتفاع الموج في ست نقاط ساحلية (Open-Meteo Marine، منذ 1990). الخريف الموسمي يرفع الموج في بحر العرب ويوقف الصيد التقليدي، ويؤثر في عمل الموانئ؛ وارتفاع حرارة السطح يضغط على الأسماك والشعاب.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="all" title="حرارة سطح البحر" subtitle="°م — متوسط شهري" lines={[{ indicator: 'marine.sst_musandam', entity: 'OMN', label: 'مسندم' }, { indicator: 'marine.sst_sohar', entity: 'OMN', label: 'صحار' }, { indicator: 'marine.sst_muscat', entity: 'OMN', label: 'مسقط' }, { indicator: 'marine.sst_duqm', entity: 'OMN', label: 'الدقم' }, { indicator: 'marine.sst_masirah', entity: 'OMN', label: 'مصيرة' }, { indicator: 'marine.sst_salalah', entity: 'OMN', label: 'صلالة' }]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="متوسط ارتفاع الموج (أقصى يومي)" subtitle="متر" lines={[{ indicator: 'marine.wave_mean_musandam', entity: 'OMN', label: 'مسندم' }, { indicator: 'marine.wave_mean_sohar', entity: 'OMN', label: 'صحار' }, { indicator: 'marine.wave_mean_muscat', entity: 'OMN', label: 'مسقط' }, { indicator: 'marine.wave_mean_duqm', entity: 'OMN', label: 'الدقم' }, { indicator: 'marine.wave_mean_masirah', entity: 'OMN', label: 'مصيرة' }, { indicator: 'marine.wave_mean_salalah', entity: 'OMN', label: 'صلالة' }]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="أعلى موجة في الشهر" subtitle="متر" lines={[{ indicator: 'marine.wave_peak_sohar', entity: 'OMN', label: 'صحار' }, { indicator: 'marine.wave_peak_duqm', entity: 'OMN', label: 'الدقم' }, { indicator: 'marine.wave_peak_salalah', entity: 'OMN', label: 'صلالة' }]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="متوسط زمن الموجة" subtitle="ثانية" lines={[{ indicator: 'marine.wave_period_musandam', entity: 'OMN', label: 'مسندم' }, { indicator: 'marine.wave_period_muscat', entity: 'OMN', label: 'مسقط' }, { indicator: 'marine.wave_period_salalah', entity: 'OMN', label: 'صلالة' }]} />
      </Section>
    </>
  );
}
