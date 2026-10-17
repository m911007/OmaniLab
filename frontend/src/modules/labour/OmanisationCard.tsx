import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

const OMANISATION: import('@/core/explain').Explanation = {
  meaning: 'التعمين هو نسبة العُمانيين من إجمالي العاملين. تُحسب هنا بقسمة عدد العُمانيين على مجموع العُمانيين والوافدين في الفئة المعروضة.',
  shows: 'خط النسبة المئوية عبر السنوات المتاحة، مع جملة تلخّص آخر قيمة وجدول القيم الأصلية في زر «جدول».',
  read: ['صعود الخط يعني أن حصة المواطنين تتزايد.', 'النسبة في القطاع الخاص أقل بكثير منها في القطاع الحكومي، وهي موضع سياسات التعمين.', 'قد يبدو الخط متذبذبًا لأن المحور مكبَّر على نطاق ضيق؛ انظر الأرقام لا الشكل وحده.'],
  why: 'التعمين هدف وطني معلن، ومؤشره يبيّن مدى نجاح الاقتصاد في خلق وظائف للمواطنين خارج الحكومة.',
  terms: [{ term: 'التعمين', definition: 'رفع نسبة العُمانيين في الوظائف، خصوصًا في القطاع الخاص.' }, { term: 'التأمينات الاجتماعية', definition: 'سجل العاملين المشتركين في صندوق التأمين، ومصدر أعداد العاملين الرسمية.' }],
  caution: 'أرقام العُمانيين قبل 2020 في سجلات التأمينات غير قابلة للمقارنة بسبب توسّع التغطية.',
};

interface Props {
  title?: string;
  subtitle?: string;
  omani?: string;
  expat?: string;
  /** وصف ما يُحسب (مثل: من العاملين في القطاع الخاص) */
  of?: string;
  note?: string;
}

/** حصة العُمانيين من إجمالي العاملين (التعمين الفعلي) كل سنة، محسوبة من سلسلتَي منظمة العمل الدولية. */
export function OmanisationCard({ title = 'التعمين: حصة العُمانيين من العاملين', subtitle = '% من إجمالي العاملين', omani = 'ilo.emp_citizen', expat = 'ilo.emp_noncitizen', of = 'من العاملين؛ والباقي وافدون', note = 'العاملون بحسب الجنسية (15 سنة فأكثر)؛ تقديرات منظمة العمل الدولية من السجلات الإدارية والمسوح.' }: Props) {
  const lines = useMemo<LineSpec[]>(
    () => [
      { indicator: omani, entity: 'OMN', label: 'العُمانيون' },
      { indicator: expat, entity: 'OMN', label: 'غير العُمانيين' },
    ],
    [omani, expat],
  );
  const { isLoading, error, series, datasets } = useLines(lines);

  const { share, cit, non } = useMemo(() => {
    const cit = new Map(series.find((s) => s.id.startsWith(omani))?.points ?? []);
    const non = new Map(series.find((s) => s.id.startsWith(expat))?.points ?? []);
    const points = [...cit].filter(([p]) => non.has(p)).map(([p, v]): [string, number] => [p, (v / (v + non.get(p)!)) * 100]);
    const share: SeriesItem[] = points.length > 0 ? [{ id: 'omanisation', label: 'حصة العُمانيين من العاملين', color: 'var(--series-1)', points }] : [];
    return { share, cit, non };
  }, [series]);

  const last = share[0]?.points.at(-1);
  const table = share[0] && {
    columns: ['السنة', 'العُمانيون', 'غير العُمانيون', 'حصة العُمانيين %'],
    rows: share[0].points.map(([p, s]) => [p, formatNumber(cit.get(p) ?? 0, 0), formatNumber(non.get(p) ?? 0, 0), formatNumber(s, 1)]),
  };

  return (
    <ChartCard title={title} subtitle={subtitle} datasets={datasets} table={table ?? undefined} note={note} explain={OMANISATION}>
      {isLoading ? (
        <Loading height={260} />
      ) : error ? (
        <ErrorState error={error} />
      ) : !last ? (
        <Empty />
      ) : (
        <>
          <p style={{ marginBottom: 8 }}>
            في <span className="num">{last[0]}</span> شكّل العُمانيون <strong className="num">{formatNumber(last[1], 1)}%</strong> {of}.
          </p>
          <LineChartView series={share} unit="%" height={240} zeroLine={false} />
        </>
      )}
    </ChartCard>
  );
}
