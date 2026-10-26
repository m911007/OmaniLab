import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import type { Reading } from '@/core/api';
import { ReadingCard, changeTone } from '@/core/components/ReadingCard';
import { dataset } from './fixtures';

const reading = (over: Partial<Reading> = {}): Reading => ({
  key: 'imf.GGXWDG_NGDP',
  label: 'الدين الحكومي',
  unit: '% من الناتج',
  digits: 1,
  polarity: 'lower',
  frequency: 'A',
  period: '2025',
  period_label: '2025',
  value: 34.7,
  previous: { period: '2024', period_label: '2024', value: 35.5 },
  history: { years: 10, mean: 42.5, min: 15, max: 68, percentile: 80 },
  gcc: { rank: 4, of: 6, percentile: 40, mean: 53.4, items: [] },
  reference: null,
  verdict: { level: 'good', label: 'جيد', basis: ['history', 'gcc'] },
  sentence: 'بلغ الدين الحكومي 34.7% من الناتج في 2025.',
  spark: [['2023', 36], ['2024', 35.5], ['2025', 34.7]],
  dataset: dataset(),
  ...over,
});

describe('بطاقة القراءة', () => {
  it('اتجاه التغيّر يُقرأ بالقطبية', () => {
    expect(changeTone(35.5, 34.7, 'lower')).toBe('good');
    expect(changeTone(1, 2, 'higher')).toBe('good');
    expect(changeTone(3.4, 3.2, 'target:2')).toBe('good');
    expect(changeTone(1, 2, null)).toBe('flat');
  });

  it('الحكم بأيقونة ونص، والجملة والمراجع الثلاثة', () => {
    render(<MemoryRouter><ReadingCard reading={reading()} /></MemoryRouter>);
    expect(screen.getByText('جيد')).toBeInTheDocument();
    expect(document.querySelector('.reading-front-foot .chip.good')?.textContent).toBe('✓جيد');
    expect(screen.getByText('بلغ الدين الحكومي 34.7% من الناتج في 2025.')).toBeInTheDocument();
    expect(screen.getByText('أفضل من 80%')).toBeInTheDocument();
    expect(screen.getByText(/من 6/)).toBeInTheDocument();
    expect(document.querySelector('.reading-delta.good')).not.toBeNull();
  });

  it('بلا حكم يُعرض «للمعرفة»، وأسفل الوسط يُقال «أسوأ من»', () => {
    render(<MemoryRouter><ReadingCard reading={reading({ verdict: null, history: { years: 10, mean: 3, min: 1, max: 5, percentile: 20 } })} /></MemoryRouter>);
    expect(screen.getByText('للمعرفة')).toBeInTheDocument();
    expect(screen.getByText('أسوأ من 80%')).toBeInTheDocument();
  });
  it('زر «؟» يقلب البطاقة إلى التفاصيل، و«↩» يعيدها، والوجه المخفي خارج القراءة الآلية', () => {
    render(<MemoryRouter><ReadingCard reading={reading()} /></MemoryRouter>);
    const card = document.querySelector('.reading-flip')!;
    const back = document.querySelector('.reading-face.back')!;
    expect(card.classList.contains('flipped')).toBe(false);
    expect(back.getAttribute('aria-hidden')).toBe('true');
    fireEvent.click(screen.getByRole('button', { name: /تفاصيل قراءة/ }));
    expect(card.classList.contains('flipped')).toBe(true);
    expect(back.getAttribute('aria-hidden')).toBe('false');
    fireEvent.click(screen.getByRole('button', { name: 'رجوع إلى الرقم' }));
    expect(card.classList.contains('flipped')).toBe(false);
  });

  it('النسخة الكاملة (صفحة المؤشر) تعرض كل شيء دون قلب', () => {
    render(<MemoryRouter><ReadingCard reading={reading()} variant="full" /></MemoryRouter>);
    expect(document.querySelector('.reading-flip')).toBeNull();
    expect(screen.getByText('أفضل من 80%')).toBeInTheDocument();
  });
});
