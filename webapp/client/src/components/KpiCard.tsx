import type { ReactNode } from 'react';

interface KpiCardProps {
  icon: ReactNode;
  label: string;
  value: string;
  subValue?: string;
  gradient: [string, string];
}

/** A single bold, gradient-tiled KPI card -- the dashboard/list pages' top row. */
export function KpiCard({ icon, label, value, subValue, gradient }: KpiCardProps) {
  return (
    <div
      className="relative flex items-center gap-4 overflow-hidden rounded-2xl p-5 text-white shadow-lg"
      style={{ background: `linear-gradient(135deg, ${gradient[0]}, ${gradient[1]})` }}
    >
      <div className="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-white/10" />
      <div className="pointer-events-none absolute -bottom-10 right-10 h-20 w-20 rounded-full bg-white/5" />
      <div className="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl backdrop-blur-sm">
        {icon}
      </div>
      <div className="relative">
        <div className="text-xs font-medium uppercase tracking-wide text-white/75">{label}</div>
        <div className="mt-1 text-2xl font-bold leading-tight">{value}</div>
        {subValue && <div className="mt-1 text-xs text-white/70">{subValue}</div>}
      </div>
    </div>
  );
}
