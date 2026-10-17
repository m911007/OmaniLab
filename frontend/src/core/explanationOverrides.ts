import { useSyncExternalStore } from 'react';
import { api, apiSend } from './api';
import type { Explanation } from './explain';

type Overrides = Record<string, Explanation>;

/** الشروحات المعدَّلة من المستخدمين/الوكيل: تُحمَّل مرة وتحل محل الشرح الافتراضي. مخزن بسيط خارج React كي لا يتطلب مزوّدًا. */
let state: Overrides = {};
let loaded = false;
const listeners = new Set<() => void>();

const emit = () => listeners.forEach((l) => l());

function load(): void {
  if (loaded || typeof fetch === 'undefined') {
    return;
  }
  loaded = true;
  api<{ data: Overrides }>('chart-explanations')
    .then((r) => {
      state = r.data ?? {};
      emit();
    })
    .catch(() => {
      loaded = false;
    });
}

export function useExplanationOverride(key: string): Explanation | undefined {
  load();
  return useSyncExternalStore(
    (l) => {
      listeners.add(l);
      return () => listeners.delete(l);
    },
    () => state[key],
    () => undefined,
  );
}

export async function saveExplanation(key: string, e: Explanation): Promise<void> {
  await apiSend('PUT', 'chart-explanations', { chart_key: key, ...e });
  state = { ...state, [key]: e };
  emit();
}

export async function resetExplanation(key: string): Promise<void> {
  await apiSend('DELETE', 'chart-explanations', { chart_key: key });
  const { [key]: _removed, ...rest } = state;
  state = rest;
  emit();
}

export const chartKeyOf = (title: string): string => `${window.location.pathname}::${title}`;
