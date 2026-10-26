import { useReadings } from '../hooks';
import { ReadingCard } from './ReadingCard';
import { Loading } from './States';

interface Props {
  entity: string;
  indicator: string;
  label: string;
  /** آخر فترة فعلية (لتجاوز التوقعات) */
  maxPeriod?: string;
  /** اتجاه التحسّن: يحدد الحكم ولون التغيّر؛ بلا تحديد تُستخدم قطبية المؤشر المعرّفة أو يُعرض «للمعرفة» */
  goodWhen?: 'up' | 'down';
  /** تحويل قيم الدولار إلى الريال العُماني */
  omr?: boolean;
  /** خيارات قديمة: صار الاختصار (مليون/مليار) وخط الاتجاه آليين في بطاقة القراءة */
  compactValue?: boolean;
  trend?: boolean;
}

/** وحدة بين قوسين في آخر العنوان («(طن)»، «(% من الناتج)»): البطاقة تعرض الوحدة الفعلية بعد التكبير الآلي، فتُحذف من العنوان كي لا تتعارض. */
const UNIT_SUFFIX = /\s*\((?:%|% من الناتج|طن|ألف طن|ألف رأس|كغ|دولار|مليون م³|(?:مليون|مليار) (?:ر\.ع|ريال)|لكل 100)\)\s*$/u;

export const cleanLabel = (label: string): string => label.replace(UNIT_SUFFIX, '');

/**
 * رقم رئيسي لمؤشر وكيان، بتصميم بطاقة القراءة الموحّد: وجه صغير بالرقم والتغيّر والحكم، و«؟» يقلبها للتفاصيل
 * (الجملة المولَّدة، ومراجع الحكم، والمصدر). يُستخدم في كل الصفحات فيتوحّد شكل البطاقات.
 */
export function KeyStat({ entity, indicator, label, maxPeriod, goodWhen, omr }: Props) {
  const { data, isLoading } = useReadings({ indicators: [indicator], entity, maxPeriod, polarity: goodWhen ? (goodWhen === 'up' ? 'higher' : 'lower') : undefined, omr });
  const reading = data?.[0];
  if (isLoading || !reading) {
    return (
      <div className="reading-flip">
        <div className="card reading-card reading-face front">
          {isLoading ? <Loading height={120} /> : <><h3 className="stat-label">{cleanLabel(label)}</h3><span className="muted">لا توجد بيانات</span></>}
        </div>
      </div>
    );
  }
  return <ReadingCard reading={{ ...reading, label: cleanLabel(label) }} />;
}
