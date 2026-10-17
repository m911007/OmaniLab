import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ChartCard } from '@/core/components/ChartCard';
import type { Explanation } from '@/core/explain';
import { dataset } from './fixtures';

const explanation: Explanation = {
  meaning: 'معنى القياس',
  shows: 'ما يوضحه الرسم',
  read: ['نقطة قراءة أولى', 'نقطة قراءة ثانية'],
  why: 'لماذا يهم',
  terms: [{ term: 'M2', definition: 'تعريف M2' }],
  caution: 'تنبيه مهم',
};

describe('شرح الرسم', () => {
  it('لا يظهر زر الشرح حين لا يوجد شرح', () => {
    render(<ChartCard title="ر" datasets={[dataset()]}><div>الرسم</div></ChartCard>);

    expect(screen.queryByRole('button', { name: 'شرح الرسم' })).toBeNull();
  });

  it('الزر يُظهر الشرح بدل الرسم، وزر العودة يعيد الرسم', () => {
    render(<ChartCard title="ر" datasets={[dataset()]} explain={explanation}><div>محتوى الرسم</div></ChartCard>);
    expect(screen.getByText('محتوى الرسم')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'شرح الرسم' }));

    expect(screen.queryByText('محتوى الرسم')).toBeNull();
    expect(screen.getByText('معنى القياس')).toBeInTheDocument();
    expect(screen.getByText('ما يوضحه الرسم')).toBeInTheDocument();
    expect(screen.getByText('نقطة قراءة ثانية')).toBeInTheDocument();
    expect(screen.getByText('تعريف M2')).toBeInTheDocument();
    expect(screen.getByText('تنبيه مهم')).toBeInTheDocument();

    fireEvent.click(screen.getAllByRole('button', { name: 'العودة إلى الرسم' })[0]);

    expect(screen.getByText('محتوى الرسم')).toBeInTheDocument();
    expect(screen.queryByText('معنى القياس')).toBeNull();
  });

  it('المصدر يبقى ظاهرًا أثناء الشرح', () => {
    render(<ChartCard title="ر" datasets={[dataset()]} explain={explanation}><div>الرسم</div></ChartCard>);

    fireEvent.click(screen.getByRole('button', { name: 'شرح الرسم' }));

    expect(screen.getByLabelText('مصدر البيانات')).toBeInTheDocument();
  });
});
