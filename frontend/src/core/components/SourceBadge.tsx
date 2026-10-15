import { sourceTag } from '../sources';

interface Props {
  sourceKey: string;
  name: string;
  url?: string | null;
}

/** شارة مصدر: وسم ملوّن قصير يفتح المصدر الأصلي في نافذة جديدة (على نمط MTCIT). */
export function SourceBadge({ sourceKey, name, url }: Props) {
  const content = (
    <>
      <span>{sourceTag(sourceKey)}</span>
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M7 17L17 7M8 7h9v9" />
      </svg>
    </>
  );
  return url ? (
    <a className={`src-badge src-${sourceKey}`} href={url} target="_blank" rel="noreferrer" title={`المصدر الأصلي: ${name}`}>
      {content}
    </a>
  ) : (
    <span className={`src-badge src-${sourceKey}`} title={name}>
      {content}
    </span>
  );
}
