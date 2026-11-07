/**
 * Same realignment includes/functions.php's shift_due_date_to_weekday() does:
 * move a due date to the same week, but on newDow (1=Monday..7=Sunday,
 * Center.MeetingDay's own numbering) -- then roll forward a week if that
 * lands in the past.
 */
export function shiftDueDateToWeekday(dueDate: Date, newDow: number): string {
  const d = new Date(Date.UTC(dueDate.getUTCFullYear(), dueDate.getUTCMonth(), dueDate.getUTCDate()));
  const oldDow = d.getUTCDay() === 0 ? 7 : d.getUTCDay(); // JS: 0=Sunday..6=Saturday -> ISO 1=Monday..7=Sunday
  const weekStart = new Date(d);
  weekStart.setUTCDate(d.getUTCDate() - (oldDow - 1));
  const newDue = new Date(weekStart);
  newDue.setUTCDate(weekStart.getUTCDate() + (newDow - 1));

  const today = new Date();
  const todayUtc = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
  if (newDue < todayUtc) {
    newDue.setUTCDate(newDue.getUTCDate() + 7);
  }
  return newDue.toISOString().slice(0, 10);
}
