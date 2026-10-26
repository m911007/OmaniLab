import type { Capabilities } from './useMarketIntel';

/** اختيار الدول بشرائح قابلة للتبديل (حتى 8 دول، لأن كل دولة تستهلك طلب بحث مدفوعًا). */
export function CountryPicker({ countries, value, onChange, max = 8 }: { countries: Capabilities['countries']; value: string[]; onChange: (v: string[]) => void; max?: number }) {
  const toggle = (code: string) => onChange(value.includes(code) ? value.filter((c) => c !== code) : value.length >= max ? value : [...value, code]);
  return (
    <div className="intel-chips" role="group" aria-label="الدول">
      {countries.map((c) => (
        <button key={c.code} type="button" className={`chip${value.includes(c.code) ? ' good' : ''}`} aria-pressed={value.includes(c.code)} onClick={() => toggle(c.code)} title={c.currency}>
          {c.name_ar}
        </button>
      ))}
      <span className="muted">{value.length}/{max}</span>
    </div>
  );
}
