import { ReadingGrid } from '@/core/components/ReadingGrid';
import { useState } from 'react';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';

const om = (slug: string, label: string, color?: string) => ({ indicator: `wmd.${slug}`, entity: 'OMN', label, color });

const MINERALS = [
  { id: 'gold', label: 'الذهب' },
  { id: 'copper', label: 'النحاس' },
  { id: 'chromium', label: 'الكروم' },
  { id: 'aluminium', label: 'الألمنيوم' },
  { id: 'iron', label: 'الحديد' },
  { id: 'gypsum', label: 'الجبس' },
  { id: 'salt', label: 'الملح' },
  { id: 'sulfur', label: 'الكبريت' },
  { id: 'manganese', label: 'المنغنيز' },
  { id: 'petroleum', label: 'النفط' },
  { id: 'natural_gas', label: 'الغاز الطبيعي' },
  { id: 'phosphate', label: 'الفوسفات' },
  { id: 'potash', label: 'البوتاس' },
  { id: 'lithium', label: 'الليثيوم' },
  { id: 'nickel', label: 'النيكل' },
  { id: 'zinc', label: 'الزنك' },
  { id: 'lead', label: 'الرصاص' },
  { id: 'silver', label: 'الفضة' },
  { id: 'uranium', label: 'اليورانيوم' },
  { id: 'rare_earths', label: 'العناصر الأرضية النادرة' },
  { id: 'bauxite', label: 'البوكسيت' },
  { id: 'cobalt', label: 'الكوبالت' },
  { id: 'tin', label: 'القصدير' },
  { id: 'graphite', label: 'الجرافيت' },
  { id: 'diamonds_gem', label: 'الماس' },
  { id: 'steam_coal', label: 'الفحم البخاري' },
  { id: 'coking_coal', label: 'فحم الكوك' },
  { id: 'kaolin', label: 'الكاولين' },
  { id: 'bentonite', label: 'البنتونيت' },
  { id: 'feldspar', label: 'الفلسبار' },
  { id: 'fluorspar', label: 'الفلورسبار' },
  { id: 'magnesite', label: 'المغنيسيت' },
  { id: 'perlite', label: 'البيرلايت' },
  { id: 'talc', label: 'التلك' },
  { id: 'titanium', label: 'التيتانيوم' },
  { id: 'tungsten', label: 'التنغستن' },
  { id: 'vanadium', label: 'الفاناديوم' },
  { id: 'molybdenum', label: 'الموليبدينوم' },
  { id: 'platinum', label: 'البلاتين' },
  { id: 'palladium', label: 'البلاديوم' },
];

export default function MineralsPage() {
  const [mineral, setMineral] = useState('gold');
  const label = MINERALS.find((m) => m.id === mineral)?.label ?? mineral;
  return (
    <>
      <PageHead
        title="المعادن والموارد الطبيعية"
        lead="إنتاج عُمان من المعادن والخامات ومصادر الطاقة ومقارنته بإنتاج العالم: الكروم والنحاس والذهب والجبس والملح والكبريت والألمنيوم والغاز والنفط وغيرها، من World Mining Data (وزارة المالية النمساوية) لنحو 170 دولة. يغطي كل إصدار آخر خمس سنوات وتتراكم السنوات مع الإصدارات."
      />

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="wmd.gypsum" label="الجبس والأنهيدريت (طن)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.chromium" label="الكروم (طن)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.aluminium" label="الألمنيوم (طن)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.copper" label="النحاس (طن)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.gold" label="الذهب (كغ)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.sulfur" label="الكبريت (طن)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.natural_gas" label="الغاز الطبيعي (مليون م³)" compactValue />
        <KeyStat entity="OMN" indicator="wmd.petroleum" label="النفط (طن)" compactValue />
      </ReadingGrid>

      <Section title="إنتاج عُمان من المعادن" lead="الأعمدة تُظهر الإنتاج السنوي بالطن (الذهب بالكيلوغرام).">
        <SeriesChart title="الجبس والأنهيدريت" subtitle="طن" kind="columns" lines={[om('gypsum', 'الجبس')]} />
        <SeriesChart title="الكروم" subtitle="طن" kind="columns" lines={[om('chromium', 'الكروم')]} />
        <SeriesChart title="الألمنيوم" subtitle="طن" kind="columns" lines={[om('aluminium', 'الألمنيوم')]} />
        <SeriesChart title="النحاس" subtitle="طن" kind="columns" lines={[om('copper', 'النحاس')]} />
        <SeriesChart title="الذهب" subtitle="كغ" kind="columns" lines={[om('gold', 'الذهب')]} />
        <SeriesChart title="الملح والكبريت" subtitle="طن" kind="columns" lines={[om('salt', 'الملح'), om('sulfur', 'الكبريت')]} />
        <SeriesChart title="المنغنيز والكاولين" subtitle="طن" kind="columns" lines={[om('manganese', 'المنغنيز'), om('kaolin', 'الكاولين')]} />
        <SeriesChart title="إنتاج الغاز الطبيعي" subtitle="مليون م³" kind="columns" lines={[om('natural_gas', 'الغاز')]} />
        <SeriesChart title="إنتاج النفط" subtitle="طن" kind="columns" lines={[om('petroleum', 'النفط')]} />
      </Section>

      <Section title="موقع عُمان عالميًا" lead="ترتيب أكبر المنتجين في 2024 مع إبراز عُمان.">
        <WorldRankCard title="الجبس والأنهيدريت" indicator="wmd.gypsum" period="2024" />
        <WorldRankCard title="الكروم" indicator="wmd.chromium" period="2024" />
        <WorldRankCard title="الألمنيوم" indicator="wmd.aluminium" period="2024" />
        <WorldRankCard title="الكبريت" indicator="wmd.sulfur" period="2024" />
        <WorldRankCard title="الغاز الطبيعي" indicator="wmd.natural_gas" period="2024" />
        <WorldRankCard title="النفط" indicator="wmd.petroleum" period="2024" />
      </Section>

      <Section title="مستكشف المعادن: أكبر المنتجين في العالم" lead="اختر معدنًا لتعرف أين يُنتج أكثر، وأين تقف عُمان إن كانت تنتجه.">
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <label className="field">
            المعدن
            <select value={mineral} onChange={(e) => setMineral(e.target.value)} aria-label="اختيار المعدن">
              {MINERALS.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.label}
                </option>
              ))}
            </select>
          </label>
        </div>
        <WorldRankCard key={mineral} title={`أكبر منتجي ${label} في 2024`} indicator={`wmd.${mineral}`} period="2024" limit={15} />
        <WorldRankCard key={`${mineral}-23`} title={`أكبر منتجي ${label} في 2022`} indicator={`wmd.${mineral}`} period="2022" limit={15} />
      </Section>
    </>
  );
}
