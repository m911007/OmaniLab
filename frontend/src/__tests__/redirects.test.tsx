import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { Moved } from '@/app/App';

function Where() {
  const l = useLocation();
  return <p>{`${l.pathname}${l.hash}`}</p>;
}

const at = (url: string) =>
  render(
    <MemoryRouter initialEntries={[url]}>
      <Routes>
        <Route path="finance" element={<Moved to="/money/banks" />} />
        <Route path="markets/:code?" element={<Moved to="/world/markets/:code" />} />
        <Route path="*" element={<Where />} />
      </Routes>
    </MemoryRouter>,
  );

describe('تحويل الروابط القديمة', () => {
  it('يحافظ على قسم الصفحة (#)', () => {
    at('/finance#banks');
    expect(screen.getByText('/money/banks#banks')).toBeInTheDocument();
  });
  it('ينقل معامل الدولة، ويحذف الشرطة الأخيرة إن غاب', () => {
    at('/markets/SAU');
    expect(screen.getByText('/world/markets/SAU')).toBeInTheDocument();
  });
  it('بلا دولة', () => {
    at('/markets');
    expect(screen.getByText('/world/markets')).toBeInTheDocument();
  });
});
