import type { ReactNode } from 'react';

/** Icon badge + title + subtitle header for a form card, followed by a divider. */
export function FormCardHeader({ icon, title, subtitle }: { icon: ReactNode; title: string; subtitle: string }) {
  return (
    <div className="mb-6 border-b border-slate-100 pb-5">
      <div className="flex items-start gap-4">
        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-lg text-[#0b2545]">{icon}</div>
        <div>
          <h2 className="text-lg font-bold text-slate-800">{title}</h2>
          <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
        </div>
      </div>
    </div>
  );
}

/** Small icon badge + uppercase label marking a form section -- "BASIC INFORMATION" style. */
export function FormSectionLabel({ icon, label }: { icon: ReactNode; label: string }) {
  return (
    <div className="mb-4 mt-8 flex items-center gap-2 first:mt-0">
      <div className="flex h-6 w-6 items-center justify-center rounded-md bg-amber-50 text-xs text-[#c8952c]">{icon}</div>
      <span className="text-xs font-bold uppercase tracking-wider text-[#0b2545]">{label}</span>
    </div>
  );
}
