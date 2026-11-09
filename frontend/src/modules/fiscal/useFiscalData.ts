import { useMemo } from 'react';
import { useLines, type LineSpec } from '@/core/hooks';
import { annualMean } from '@/core/stats';
import type { Yearly } from './metrics';

const KEYS = [
  'mof.rev_total', 'mof.rev_oil', 'mof.rev_gas', 'mof.rev_other', 'mof.exp_total', 'mof.balance',
  'mof.bud_rev_total', 'mof.bud_exp_total', 'mof.bud_balance',
  'mof.exp_defense', 'mof.exp_civil', 'mof.exp_other_current', 'mof.exp_investment', 'mof.exp_contributions',
  'mof.fin_external_net', 'mof.fin_domestic_net', 'mof.fin_reserves', 'mof.fin_other',
  'imf.GGXWDG_NGDP', 'imf.ie', 'imf.GGR_G01_GDP_PT', 'ncsi.OIL_PRICE',
] as const;
export type FiscalKey = (typeof KEYS)[number];

const specs = (keys: readonly FiscalKey[]): LineSpec[] => keys.map((indicator) => ({ indicator, entity: 'OMN' }));
const ALL = specs(KEYS);
const cache = new Map<string, LineSpec[]>();

/** مجموعات مفاتيح جاهزة (مرجع ثابت لكل مجموعة) كي لا يعرض كل رسم مصادر ما لا يستخدمه. */
export const MOF_KEYS = KEYS.filter((k) => k.startsWith('mof.'));
export const BREAKEVEN_KEYS: readonly FiscalKey[] = ['mof.balance', 'mof.rev_oil', 'ncsi.OIL_PRICE'];
export const INTEREST_KEYS: readonly FiscalKey[] = ['imf.ie', 'imf.GGR_G01_GDP_PT'];

/** سلاسل سنوية (مفتاح ← سنة ← قيمة) للحساب الختامي والدين وسعر النفط؛ سعر النفط متوسط سنوي. */
export function useFiscalData(keys: readonly FiscalKey[] = KEYS) {
  const id = keys.join(',');
  const lines = keys === KEYS ? ALL : (cache.get(id) ?? cache.set(id, specs(keys)).get(id)!);
  const { isLoading, error, series, datasets } = useLines(lines, { from: '2012' });
  const m = useMemo(() => {
    const out = {} as Record<FiscalKey, Yearly>;
    for (const k of KEYS) {
      const pts = series.find((s) => s.id.startsWith(`${k}|`))?.points ?? [];
      out[k] = new Map(k === 'ncsi.OIL_PRICE' ? annualMean(pts) : pts);
    }
    return out;
  }, [series]);
  return { isLoading, error, m, datasets };
}
