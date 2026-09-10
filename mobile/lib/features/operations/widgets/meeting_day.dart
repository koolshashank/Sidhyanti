/// Center.MeetingDay is stored as free text -- some rows hold a weekday
/// number ("1"-"7", from older/seeded data), others a full name ("Monday",
/// from this app's own create-center form). Normalize both to a name.
String formatMeetingDay(String? day) {
  if (day == null || day.trim().isEmpty) return '-';
  final asInt = int.tryParse(day.trim());
  if (asInt != null && asInt >= 1 && asInt <= 7) {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return days[asInt - 1];
  }
  return day;
}
