interface RankedBarItem {
  label: string;
  value: number;
  displayValue: string;
  color: string;
}

/**
 * A ranked, proportional horizontal-bar list -- used instead of a generic
 * bar/column chart wherever the point is "compare these categories and
 * read the exact number clearly" (Loan Pipeline, Branch Portfolio). Reads
 * better than axis-based charts at this scale (5-8 categories).
 */
export function RankedBarList({ items }: { items: RankedBarItem[] }) {
  const max = Math.max(...items.map((i) => i.value), 1);

  return (
    <div className="flex flex-col gap-4">
      {items.map((item) => (
        <div key={item.label}>
          <div className="mb-1.5 flex items-baseline justify-between">
            <span className="text-sm font-semibold text-slate-700">{item.label}</span>
            <span className="text-sm font-bold text-slate-900">{item.displayValue}</span>
          </div>
          <div className="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div
              className="h-full rounded-full transition-all"
              style={{
                width: `${Math.max((item.value / max) * 100, 2)}%`,
                background: item.color,
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}
