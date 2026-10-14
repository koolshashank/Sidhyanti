import 'package:go_router/go_router.dart';

/// Set once by [buildAppRouter] -- lets code with no BuildContext (a push
/// notification tap while the app is backgrounded/terminated) still
/// navigate, the same way [resolveNotificationRoute] lets the in-app
/// Notifications list navigate via `context.push`.
GoRouter? rootRouter;

/// Maps a notification (same shape whether it came from the in-app list or
/// a push payload) to the specific screen it's about -- a loan proposal
/// opens its full review screen, while preclose/partial-payment notices
/// open the BM Approval hub on the right tab with that loan's card
/// highlighted (both share one pending-list per loan, so there's no
/// single-record screen to deep-link straight into). Returns null when
/// there's nowhere sensible to go (not a Loan-related notification).
String? resolveNotificationRoute({required String? relatedEntityType, required int? relatedEntityId, required String title}) {
  if (relatedEntityType != 'Loan' || relatedEntityId == null) return null;
  final lower = title.toLowerCase();
  if (lower.contains('proposal')) {
    return '/loans/$relatedEntityId/review';
  } else if (lower.contains('preclose')) {
    return '/bm-approval?tab=preclose&loan_id=$relatedEntityId';
  } else if (lower.contains('partial payment')) {
    return '/bm-approval?tab=partial_payments&loan_id=$relatedEntityId';
  }
  return '/loans/$relatedEntityId';
}

/// Navigates via the global router -- used where there's no BuildContext
/// (push notification tapped while backgrounded/terminated).
void openNotificationRouteGlobally({required String? relatedEntityType, required int? relatedEntityId, required String title}) {
  final path = resolveNotificationRoute(relatedEntityType: relatedEntityType, relatedEntityId: relatedEntityId, title: title);
  if (path != null) rootRouter?.push(path);
}
