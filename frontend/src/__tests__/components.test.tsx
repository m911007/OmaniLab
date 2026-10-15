import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Compact } from '@/core/components/Compact';
import { FreshnessBadge } from '@/core/components/FreshnessBadge';
import { RankedBars } from '@/core/components/RankedBars';
import { SourceNote } from '@/core/components/SourceNote';
import { StatCard } from '@/core/components/StatCard';
import { parseManafethVersion } from '@/modules/trade/manafeth';
import { dataset, latest } from './fixtures';

describe('FreshnessBadge', () => {
  it('الحالة بأيقونة ونص معًا (لا لون وحده) مع آخر فحص وآخر تغيير', () => {
    render(<FreshnessBadge dataset={dataset()} />);
    expect(screen.getByText('محدّثة')).toBeInTheDocument();
    expect(screen.getByText('✓')).toBeInTheDocument();
    expect(screen.getByText(/آخر فحص/)).toBeInTheDocument();
    expect(screen.getByText(/آخر تغيير/)).toBeInTheDocument();
  });

  it('مجموعة محجوزة أو فاشلة تظهر بنص وأيقونة تنبيه', () => {
    const { rerender } = render(<FreshnessBadge dataset={dataset({ status: 'quarantined', status_label: 'محجوزة للمراجعة' })} />);
    expect(screen.getByText('محجوزة للمراجعة')).toBeInTheDocument();
    rerender(<FreshnessBadge dataset={dataset({ status: 'failing', status_label: 'فشل الجلب' })} />);
    expect(screen.getByText('✕')).toBeInTheDocument();
  });
});

describe('SourceNote', () => {
  it('يعرض المصدر والإصدار ويزيل تكرار المجموعة نفسها', () => {
    render(<SourceNote datasets={[dataset(), dataset()]} note="ملاحظة" />);
    expect(screen.getAllByText(/صندوق النقد الدولي/)).toHaveLength(1);
    expect(screen.getByText(/October 2026/)).toBeInTheDocument();
    expect(screen.getByText('ملاحظة')).toBeInTheDocument();
  });
});

describe('StatCard', () => {
  it('يعرض القيمة وتغيّرها وترتيبها ومصدرها', () => {
    render(<StatCard label="الناتج المحلي" item={latest()} />);
    expect(screen.getByText('الناتج المحلي')).toBeInTheDocument();
    expect(screen.getByText('109.4')).toBeInTheDocument();
    expect(screen.getByText(/المرتبة/)).toBeInTheDocument();
    expect(screen.getByText('72')).toBeInTheDocument();
    expect(screen.getByText('صندوق النقد الدولي')).toBeInTheDocument();
  });

  it('حالة عدم وجود بيانات', () => {
    render(<StatCard label="س" />);
    expect(screen.getByText('لا توجد بيانات')).toBeInTheDocument();
  });

  it('التغيّر محايد اللون إلا عند تحديد اتجاه التحسّن', () => {
    const { container, rerender } = render(<StatCard label="س" item={latest()} />);
    const delta = () => container.querySelector('.stat-delta') as HTMLElement;
    expect(delta().style.color).toBe('var(--text-secondary)');
    rerender(<StatCard label="س" item={latest()} goodWhen="up" />);
    expect(delta().style.color).toBe('var(--good)');
    rerender(<StatCard label="س" item={latest()} goodWhen="down" />);
    expect(delta().style.color).toBe('var(--critical)');
  });
});

describe('الأرقام المختصرة', () => {
  it('الرقم داخل عنصر معزول والكلمة خارجه', () => {
    const { container } = render(<Compact v={21_208_630_443} />);
    expect(container.querySelector('.num')?.textContent).toBe('21.2');
    expect(container.textContent).toBe('21.2 مليار');
  });
});

describe('RankedBars', () => {
  it('يعرض الترتيب والنسبة نصًا، ويُخفت البند غير الدولي', () => {
    render(
      <RankedBars
        items={[
          { key: 'CHN', rank: 1, label: 'الصين', value: 7_674_048_261, share: 0.36, color: 'var(--series-1)' },
          { key: 'ZZZ', rank: 2, label: 'غير محدد / غير موزّع', value: 4_089_510_352, share: 0.19, muted: true },
        ]}
      />,
    );
    expect(screen.getByText('الصين')).toBeInTheDocument();
    expect(screen.getByText('36%')).toBeInTheDocument();
    expect(screen.getByText('غير محدد / غير موزّع')).toHaveStyle({ color: 'var(--text-muted)' });
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
  });
});

describe('إصدار منافذ', () => {
  it('يستخرج آخر سنة وشهر منشورين', () => {
    expect(parseManafethVersion('منافذ — حتى 2026-07')).toEqual({ year: 2026, month: 7 });
    expect(parseManafethVersion('منافذ — حتى 2025-12')).toEqual({ year: 2025, month: 12 });
    expect(parseManafethVersion(null)).toBeNull();
    expect(parseManafethVersion('بلا تاريخ')).toBeNull();
  });
});
