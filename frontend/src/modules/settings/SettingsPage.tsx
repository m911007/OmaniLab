import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, apiSend } from '@/core/api';
import { PageHead } from '@/core/components/PageHead';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import { formatDateTime } from '@/core/format';

export interface SecretStatus {
  name: string;
  label: string;
  group: string;
  /** secret: تُخفى ولا تعود للمتصفح، text: قيمة غير حساسة تُعرض */
  kind: 'secret' | 'text';
  help: string;
  enables: string;
  url: string | null;
  example: string;
  configured: boolean;
  source: 'db' | 'env' | null;
  hint: string | null;
  value: string | null;
  updated_at: string | null;
}

export const secretsQueryKey = ['secrets'] as const;

/** يجمع المفاتيح في مجموعاتها بترتيب ظهورها. */
export function groupSecrets(items: readonly SecretStatus[]): { group: string; items: SecretStatus[] }[] {
  const groups = new Map<string, SecretStatus[]>();
  for (const s of items) groups.set(s.group, [...(groups.get(s.group) ?? []), s]);
  return [...groups].map(([group, list]) => ({ group, items: list }));
}

/**
 * مفاتيح الخدمات والإعدادات: يضعها المشرف هنا بعد تسجيل الدخول (الأسرار مشفَّرة في القاعدة ولا تعود للمتصفح بعد الحفظ)
 * بدل ملف .env، وتعمل الميزة فور الحفظ. لكل مفتاح ما يفعّله ورابط الحصول عليه وصيغته المتوقعة.
 */
export default function SettingsPage() {
  const { user, ready } = useAuth();
  const qc = useQueryClient();
  const status = useQuery({ queryKey: secretsQueryKey, queryFn: ({ signal }) => api<{ data: SecretStatus[] }>('settings/secrets', {}, signal), enabled: !!user?.is_admin });
  const [values, setValues] = useState<Record<string, string>>({});
  const [shown, setShown] = useState<Record<string, boolean>>({});
  const [failed, setFailed] = useState<{ name: string; message: string } | null>(null);
  const [saved, setSaved] = useState<string | null>(null);

  const refresh = (res: { data: SecretStatus[] }) => {
    qc.setQueryData(secretsQueryKey, res);
    void qc.invalidateQueries({ queryKey: ['mi-capabilities'] });
  };
  const save = useMutation({
    mutationFn: ({ name, value }: { name: string; value: string }) => apiSend<{ data: SecretStatus[] }>('PUT', `settings/secrets/${name}`, { value }),
    onSuccess: (res, v) => {
      refresh(res);
      // يُمحى ما كُتب: السر يعود حقلًا فارغًا، والقيمة النصية تعود إلى المحفوظ
      setValues((s) => Object.fromEntries(Object.entries(s).filter(([k]) => k !== v.name)));
      setFailed(null);
      setSaved(v.name);
    },
    onError: (err, v) => {
      setSaved(null);
      setFailed({ name: v.name, message: err instanceof Error ? err.message : 'تعذّر الحفظ' });
    },
  });
  const remove = useMutation({
    mutationFn: (name: string) => apiSend<{ data: SecretStatus[] }>('DELETE', `settings/secrets/${name}`),
    onSuccess: (res) => {
      refresh(res);
      setSaved(null);
      setFailed(null);
    },
    onError: (err, name) => setFailed({ name, message: err instanceof Error ? err.message : 'تعذّر الحذف' }),
  });

  const items = status.data?.data ?? [];
  const configured = items.filter((s) => s.configured).length;

  return (
    <>
      <PageHead title="مفاتيح الخدمات والإعدادات" lead="كل ما يحتاجه الخادم ليعمل بكامل طاقته: مفاتيح الذكاء الاصطناعي والمتاجر، وتنبيهات تيليغرام، ومفاتيح المصادر التي ترفع حدود الجلب. تُحفظ مشفَّرة في قاعدة البيانات ولا تُعرض بعد الحفظ، ويتقدّم المحفوظ هنا على ما في ملف .env." />
      {ready && !user && <div className="card tone-info"><Link to="/login">سجّل الدخول</Link> بحساب مشرف لإدارة المفاتيح.</div>}
      {user && !user.is_admin && <div className="card tone-info">هذه الصفحة للمشرف فقط.</div>}
      {user?.is_admin && (
        <>
          {status.isLoading && <Loading height={160} />}
          {status.error && <ErrorState error={status.error as Error} onRetry={() => void status.refetch()} />}
          {items.length > 0 && (
            <p className="settings-summary" role="status">
              <span className={`chip ${configured === items.length ? 'good' : 'warning'}`}>{configured} من {items.length} مضبوطة</span>
              <span className="muted"> — المفاتيح غير المضبوطة لا تعطّل المنصة؛ تعطّل الميزة المرتبطة بها فقط.</span>
            </p>
          )}
          {groupSecrets(items).map(({ group, items: list }) => (
            <section key={group} className="section" aria-label={group}>
              <h2>{group}</h2>
              <div className="grid cols-2">
                {list.map((s) => {
                  const typed = values[s.name] ?? '';
                  const dirty = s.kind === 'text' ? typed.trim() !== '' && typed.trim() !== (s.value ?? '') : typed.trim() !== '';
                  const error = failed?.name === s.name ? failed.message : null;
                  return (
                    <form
                      key={s.name}
                      className="card intel-search secret-card"
                      onSubmit={(e) => {
                        e.preventDefault();
                        if (dirty) save.mutate({ name: s.name, value: typed.trim() });
                      }}
                    >
                      <header className="secret-head">
                        <h3>{s.label}</h3>
                        <span className={`chip ${s.configured ? 'good' : 'warning'}`}><span aria-hidden="true">{s.configured ? '✓' : '!'}</span> {s.configured ? 'مضبوط' : 'غير مضبوط'}</span>
                      </header>
                      <p className="muted">{s.help}</p>
                      <p className="secret-enables"><b>يفعّل:</b> {s.enables}</p>
                      <p className="muted secret-state">
                        {s.source === 'db' && <>محفوظ هنا{s.kind === 'secret' && s.hint ? <> — ينتهي بـ <b dir="ltr">…{s.hint}</b></> : null}{s.updated_at ? ` · ${formatDateTime(s.updated_at)}` : ''}</>}
                        {s.source === 'env' && 'قادم من ملف .env (احتياطي)'}
                        {s.source === null && 'لم يُضبط بعد'}
                      </p>
                      <label className="intel-field">
                        <span>{s.kind === 'text' ? 'القيمة' : s.configured ? 'استبدال المفتاح' : 'المفتاح'}</span>
                        <span className="secret-input">
                          <input
                            type={s.kind === 'secret' && !shown[s.name] ? 'password' : 'text'}
                            autoComplete="off"
                            spellCheck={false}
                            dir="ltr"
                            value={s.kind === 'text' && values[s.name] === undefined ? (s.value ?? '') : typed}
                            onChange={(e) => { setValues((v) => ({ ...v, [s.name]: e.target.value })); setSaved(null); }}
                            placeholder={s.example}
                            aria-label={`قيمة ${s.label}`}
                            aria-invalid={error ? true : undefined}
                          />
                          {s.kind === 'secret' && <button type="button" className="btn secret-eye" onClick={() => setShown((v) => ({ ...v, [s.name]: !v[s.name] }))} aria-pressed={!!shown[s.name]} title="إظهار ما كتبته للتأكد منه">{shown[s.name] ? 'إخفاء' : 'إظهار'}</button>}
                        </span>
                      </label>
                      {error && <p role="alert" className="secret-error">{error}</p>}
                      {saved === s.name && !error && <p role="status" className="secret-ok">✓ حُفظ وسيعمل فورًا.</p>}
                      <div className="intel-chips">
                        <button className="btn" type="submit" disabled={save.isPending || !dirty}>حفظ</button>
                        {s.source === 'db' && <button className="btn" type="button" disabled={remove.isPending} onClick={() => remove.mutate(s.name)}>حذف المحفوظ</button>}
                        {s.url && <a className="btn secret-get" href={s.url} target="_blank" rel="noreferrer">احصل على المفتاح ↗</a>}
                      </div>
                    </form>
                  );
                })}
              </div>
            </section>
          ))}
          <p className="source-note">بعد الحفظ تعمل الميزة فورًا دون إعادة تشغيل. من يملك حساب مشرف يستطيع استبدال المفتاح أو حذفه؛ لا أحد يستطيع قراءته من الواجهة (يظهر آخر 4 أحرف فقط للتحقق). مفاتيح مصادر البيانات تُطبَّق في أول جلب تالٍ لكل مصدر.</p>
        </>
      )}
    </>
  );
}
