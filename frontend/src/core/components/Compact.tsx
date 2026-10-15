import { compactParts } from '../format';

/** رقم مختصر بترتيب عربي صحيح: الرقم (لاتيني، اتجاه يسار-يمين معزول) ثم كلمة المقياس. */
export function Compact({ v, digits = 1 }: { v: number; digits?: number }) {
  const { num, word } = compactParts(v, digits);
  return (
    <>
      <span className="num">{num}</span>
      {word && <> {word}</>}
    </>
  );
}
