/** وسم قصير لكل مصدر (كما في شارات MTCIT)؛ المفتاح هو source.key من الـ API. */
export const SOURCE_TAGS: Record<string, string> = {
  imf: 'IMF',
  worldbank: 'WB',
  ilo: 'ILO',
  who: 'WHO',
  fao: 'FAO',
  unesco: 'UNESCO',
  undp: 'UNDP',
  owid: 'OWID',
  oec: 'OEC',
  us_treasury: 'UST',
  eia: 'EIA',
  un_comtrade: 'Comtrade',
  wits: 'WITS',
  ncsi: 'NCSI',
  gccstat: 'GCC-Stat',
  manafeth: 'منافذ',
  gleif: 'GLEIF',
};

export const sourceTag = (key: string): string => SOURCE_TAGS[key] ?? key.toUpperCase();
