import type { Dataset, LatestItem } from '@/core/api';

export const dataset = (over: Partial<Dataset> = {}): Dataset => ({
  key: 'imf.weo',
  name_ar: 'آفاق الاقتصاد العالمي',
  name_en: 'WEO',
  section: 'macro',
  status: 'fresh',
  status_label: 'محدّثة',
  reliability: 'official',
  source: { key: 'imf', name_ar: 'صندوق النقد الدولي', name_en: 'IMF', url: null, license: null },
  freshness: {
    source_version: 'World Economic Outlook (October 2026)',
    source_published_at: '2026-10-08T14:43:40+00:00',
    last_checked_at: new Date(Date.now() - 36 * 60_000).toISOString(),
    last_changed_at: new Date(Date.now() - 3 * 3_600_000).toISOString(),
    last_success_at: null,
    next_check_at: null,
    interval_minutes: 10080,
  },
  ...over,
});

export const latest = (over: Partial<LatestItem> = {}): LatestItem => ({
  indicator: { key: 'imf.NGDPD', name_ar: 'الناتج', name_en: 'GDP', unit: 'مليار دولار', frequency: 'A' },
  period: '2025',
  value: 109.432,
  previous: { period: '2024', value: 107.136 },
  rank: { position: 72, total: 195 },
  dataset: dataset(),
  ...over,
});
