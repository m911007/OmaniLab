/** تسميات عربية لقيم GLEIF. ما لا تسمية له يُعرض بقيمته الأصلية. */
export const STATUS_AR: Record<string, string> = { ACTIVE: 'نشط', INACTIVE: 'غير نشط', NULL: 'غير محدد' };

export const CATEGORY_AR: Record<string, string> = {
  GENERAL: 'شركة أو كيان عام',
  SOLE_PROPRIETOR: 'مؤسسة فردية',
  BRANCH: 'فرع',
  FUND: 'صندوق',
  INTERNATIONAL_ORGANIZATION: 'منظمة دولية',
  RESIDENT_GOVERNMENT_ENTITY: 'جهة حكومية',
  NON_FINANCIAL: 'كيان غير مالي',
};

export const REGISTRATION_AR: Record<string, string> = {
  ISSUED: 'ساري',
  LAPSED: 'منتهي (لم يُجدَّد)',
  PENDING_VALIDATION: 'قيد التحقق',
  PENDING_TRANSFER: 'قيد النقل',
  RETIRED: 'مُنهى',
  ANNULLED: 'ملغى',
  CANCELLED: 'مُلغى',
  MERGED: 'مندمج',
  DUPLICATE: 'مكرر',
  TRANSFERRED: 'منقول',
};

export const ATTRIBUTE_LABELS: Record<string, string> = {
  status: 'حالة الكيان',
  category: 'الفئة',
  legal_form: 'رمز الشكل القانوني (ELF)',
  governorate: 'المحافظة',
  city: 'المدينة',
  region: 'رمز المنطقة (ISO 3166-2)',
  country: 'الدولة',
  address: 'العنوان',
  postal_code: 'الرمز البريدي',
  creation_date: 'تاريخ التأسيس',
  registered_as: 'رقم السجل التجاري',
  registration_authority: 'جهة التسجيل',
  registration_status: 'حالة تسجيل LEI',
  last_update: 'آخر تحديث لسجل LEI',
  next_renewal: 'موعد تجديد LEI',
  other_names: 'أسماء أخرى',
  jurisdiction: 'الاختصاص القضائي',
};

export const label = (map: Record<string, string>, value: string | undefined | null): string => (value ? (map[value] ?? value) : '—');
