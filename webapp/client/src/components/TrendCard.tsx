import { ArrowDownOutlined, ArrowUpOutlined } from '@ant-design/icons';

interface TrendCardProps {
  title: string;
  currentLabel: string;
  current: number;
  previousLabel: string;
  previous: number;
  format: (n: number) => string;
}

/** Current-vs-previous comparison with an up/down % badge. */
export function TrendCard({ title, currentLabel, current, previousLabel, previous, format }: TrendCardProps) {
  const delta = previous > 0 ? ((current - previous) / previous) * 100 : current > 0 ? 100 : 0;
  const isUp = delta >= 0;

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <span className="text-sm font-semibold text-slate-600">{title}</span>
        <span
          className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold ${
            isUp ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'
          }`}
        >
          {isUp ? <ArrowUpOutlined /> : <ArrowDownOutlined />}
          {Math.abs(delta).toFixed(1)}%
        </span>
      </div>
      <div className="flex items-end justify-between">
        <div>
          <div className="text-xs text-slate-400">{currentLabel}</div>
          <div className="text-xl font-bold text-slate-800">{format(current)}</div>
        </div>
        <div className="text-right">
          <div className="text-xs text-slate-400">{previousLabel}</div>
          <div className="text-sm font-semibold text-slate-400">{format(previous)}</div>
        </div>
      </div>
    </div>
  );
}
