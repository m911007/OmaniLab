import { describe, expect, it } from 'vitest';
import { foodRows } from '@/modules/agriculture/FoodPricesTable';

describe('جدول أسعار السلع الغذائية', () => {
  it('يجمع مؤشرات العبوات وكلفة الكيلو في صف لكل صنف ويرتّب بالأغلى كيلوً', () => {
    const inds = [
      { key: 'mafwr.food.sugar.p50', name_ar: 'السكر: عبوة 50 كغم' }, { key: 'mafwr.food.sugar.perkg', name_ar: 'السكر: سعر الكيلو' },
      { key: 'mafwr.food.rice_thai_white.p20', name_ar: 'أرز تايلندي أبيض: عبوة 20 كغم' }, { key: 'mafwr.food.rice_thai_white.p5', name_ar: 'أرز تايلندي أبيض: عبوة 5 كغم' },
      { key: 'mafwr.food.rice_thai_white.perkg', name_ar: 'أرز تايلندي أبيض: سعر الكيلو' },
      { key: 'mafwr.food.lentils_red.p10', name_ar: 'العدس: عبوة 10 كغم' }, // بلا قيمة: يُهمل
    ];
    const rows = foodRows(inds, new Map([['mafwr.food.sugar.p50', 14.15], ['mafwr.food.sugar.perkg', 0.283], ['mafwr.food.rice_thai_white.p20', 6.7], ['mafwr.food.rice_thai_white.p5', 1.875], ['mafwr.food.rice_thai_white.perkg', 0.3425]]));

    expect(rows.map((r) => r.slug)).toEqual(['rice_thai_white', 'sugar']);
    expect(rows[0].name).toBe('أرز تايلندي أبيض');
    expect(rows[0].packs.map((p) => p.kg)).toEqual([5, 20]);
    expect(rows[1].perKg).toBe(0.283);
  });
});
