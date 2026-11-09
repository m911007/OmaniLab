/** زر تشغيل/إخفاء خط سعر النفط. */
export function OilToggle({ on, onChange }: { on: boolean; onChange: (v: boolean) => void }) {
  return <button type="button" className={`btn oil-toggle${on ? ' on' : ''}`} aria-pressed={on} onClick={() => onChange(!on)} title="إظهار سعر النفط أو إخفاؤه">{on ? '◉' : '○'} سعر النفط</button>;
}

