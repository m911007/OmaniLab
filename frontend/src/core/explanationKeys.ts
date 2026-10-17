import { MACRO_AND_MONEY } from './explanations';
import { TRADE_OIL_LABOUR_ENERGY } from './explanations2';
import { RICH_OVERRIDES } from './explanationsRich';
import { SOCIETY_GOVERNANCE_DEV } from './explanations3';

/** مفاتيح المؤشرات التي لها شرح مكتوب يدويًا (للاختبارات والتغطية). */
export const EXPLANATION_KEYS: string[] = [...Object.keys(MACRO_AND_MONEY), ...Object.keys(TRADE_OIL_LABOUR_ENERGY), ...Object.keys(SOCIETY_GOVERNANCE_DEV), ...Object.keys(RICH_OVERRIDES)];
