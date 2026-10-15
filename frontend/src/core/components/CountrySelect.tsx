import { useMemo, useState } from 'react';
import type { EntityLite } from '../api';
import { entityName } from '../format';

interface Props {
  entities: EntityLite[];
  value: string;
  onChange: (code: string) => void;
  label: string;
  exclude?: string[];
}

/** اختيار دولة مع بحث نصي (عربي أو إنجليزي). الترتيب هجائي عربي. */
export function CountrySelect({ entities, value, onChange, label, exclude = [] }: Props) {
  const [q, setQ] = useState('');
  const list = useMemo(() => {
    const needle = q.trim().toLowerCase();
    return entities
      .filter((e) => !exclude.includes(e.code))
      .filter((e) => !needle || (e.name_ar ?? '').includes(needle) || e.name_en.toLowerCase().includes(needle) || e.code.toLowerCase() === needle)
      .sort((a, b) => entityName(a).localeCompare(entityName(b), 'ar'));
  }, [entities, q, exclude]);

  return (
    <label className="field">
      {label}
      <input type="search" placeholder="ابحث…" value={q} onChange={(e) => setQ(e.target.value)} aria-label={`بحث في ${label}`} style={{ width: 120 }} />
      <select value={value} onChange={(e) => onChange(e.target.value)} aria-label={label} style={{ minWidth: 180 }}>
        {list.map((e) => (
          <option key={e.code} value={e.code}>
            {entityName(e)}
          </option>
        ))}
        {!list.some((e) => e.code === value) && <option value={value}>{value}</option>}
      </select>
    </label>
  );
}
