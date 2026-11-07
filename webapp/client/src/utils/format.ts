const inrFormatter = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });
const compactInrFormatter = new Intl.NumberFormat('en-IN', {
  style: 'currency',
  currency: 'INR',
  notation: 'compact',
  maximumFractionDigits: 1,
});

export function money(value: number): string {
  return inrFormatter.format(value || 0);
}

export function moneyCompact(value: number): string {
  return compactInrFormatter.format(value || 0);
}

export function number(value: number): string {
  return new Intl.NumberFormat('en-IN').format(value || 0);
}

const WEEKDAY_NAMES: Record<string, string> = {
  '1': 'Monday',
  '2': 'Tuesday',
  '3': 'Wednesday',
  '4': 'Thursday',
  '5': 'Friday',
  '6': 'Saturday',
  '7': 'Sunday',
};

/** Center.MeetingDay is stored as 1-7 (Monday-Sunday), same convention centers.php uses. */
export function weekdayName(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '-';
  return WEEKDAY_NAMES[String(value)] ?? String(value);
}

export function fdate(value: string | Date | null | undefined): string {
  if (!value) return '-';
  const d = typeof value === 'string' ? new Date(value) : value;
  if (Number.isNaN(d.getTime())) return '-';
  return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}
