import 'package:flutter/material.dart';

import '../../l10n/generated/app_localizations.dart';

/// Static lookup for every mobile-menu key the CRM admin can grant a
/// designation (see mobile_menu_access.php / Designation_Mobile_Menu on the
/// backend). Keys here MUST match `MOBILE_MENU_DEFS` in
/// includes/mobile_menu_helper.php exactly -- this is the client-side half
/// of that catalog (icon/gradient/route), kept in sync by hand the same way
/// Endpoints' path constants already are.
class MobileMenuEntry {
  final IconData icon;
  final List<Color> gradient;
  final String title;
  final String subtitle;
  final String route;

  const MobileMenuEntry({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.subtitle,
    required this.route,
  });
}

const Map<String, MobileMenuEntry> mobileMenuCatalog = {
  'create_center': MobileMenuEntry(
    icon: Icons.add_business_rounded,
    gradient: [Color(0xFFE65100), Color(0xFFBF360C)],
    title: 'Create New Center',
    subtitle: 'Add a center in your branch',
    route: '/create-center',
  ),
  'client_sourcing': MobileMenuEntry(
    icon: Icons.person_add_alt_1_rounded,
    gradient: [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
    title: 'Client Sourcing',
    subtitle: 'Capture a new lead',
    route: '/client-sourcing',
  ),
  'flo_collection': MobileMenuEntry(
    icon: Icons.payments_rounded,
    gradient: [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
    title: 'FLO Collection',
    subtitle: 'Record field collections',
    route: '/collections',
  ),
  'client_details': MobileMenuEntry(
    icon: Icons.groups_rounded,
    gradient: [Color(0xFF4A9FFF), Color(0xFF3A6FD9)],
    title: 'Client Details',
    subtitle: 'View your members',
    route: '/members',
  ),
  'dormant_clients': MobileMenuEntry(
    icon: Icons.bedtime_rounded,
    gradient: [Color(0xFF8D6E63), Color(0xFF6D4C41)],
    title: 'Dormant Client',
    subtitle: 'Closed loan, no current loan',
    route: '/members/dormant',
  ),
  'bm_approval': MobileMenuEntry(
    icon: Icons.fact_check_rounded,
    gradient: [Color(0xFFE65100), Color(0xFFBF360C)],
    title: 'BM Approval',
    subtitle: 'Proposals, preclose, leave, payments',
    route: '/bm-approval',
  ),
  'disbursement': MobileMenuEntry(
    icon: Icons.account_balance_wallet_rounded,
    gradient: [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
    title: 'Disbursement',
    subtitle: 'Approved loans awaiting disbursal',
    route: '/disbursement',
  ),
  'dcr': MobileMenuEntry(
    icon: Icons.assignment_rounded,
    gradient: [Color(0xFF14A9AB), Color(0xFF0F8B8D)],
    title: 'DCR',
    subtitle: 'Day closing cash reconciliation',
    route: '/dcr',
  ),
  'center_approval': MobileMenuEntry(
    icon: Icons.location_city_rounded,
    gradient: [Color(0xFFE57373), Color(0xFFD32F2F)],
    title: 'Center Approval',
    subtitle: 'Review new centers',
    route: '/center-approval',
  ),
  'am_loan_approval': MobileMenuEntry(
    icon: Icons.fact_check_rounded,
    gradient: [Color(0xFFE65100), Color(0xFFBF360C)],
    title: 'Loan Approval',
    subtitle: 'Proposals above the BM limit',
    route: '/am-loan-approval',
  ),
  'am_center_transfer': MobileMenuEntry(
    icon: Icons.swap_horiz_rounded,
    gradient: [Color(0xFFC8952C), Color(0xFFA9781E)],
    title: 'Center Transfer',
    subtitle: 'Reassign a center to another employee',
    route: '/am-center-transfer',
  ),
  'am_tvr': MobileMenuEntry(
    icon: Icons.phone_in_talk_rounded,
    gradient: [Color(0xFF14A9AB), Color(0xFF0F8B8D)],
    title: 'TVR',
    subtitle: 'Telephonic verification calls',
    route: '/am-tvr',
  ),
  'operational_reports': MobileMenuEntry(
    icon: Icons.bar_chart_rounded,
    gradient: [Color(0xFF3A3D9F), Color(0xFF20223B)],
    title: 'Reports',
    subtitle: 'Collection, disbursement, arrears, defaults',
    route: '/reports/operational',
  ),
  // Duty Tracking / Staff Tracking -- temporarily disabled. Commented out
  // (not deleted) so this entry is omitted from the menu everywhere it's
  // surfaced (mobileMenuCatalog[key] lookups in dashboard_screen.dart
  // safely skip a missing key), without losing the config. Uncomment both
  // this and the matching localizedMenuLabel case below to re-enable.
  // 'duty_tracking': MobileMenuEntry(
  //   icon: Icons.my_location_rounded,
  //   gradient: [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
  //   title: 'Duty Tracking',
  //   subtitle: 'Start/End Duty',
  //   route: '/duty-tracking',
  // ),
  // 'staff_tracking': MobileMenuEntry(
  //   icon: Icons.map_rounded,
  //   gradient: [Color(0xFF3A3D9F), Color(0xFF20223B)],
  //   title: 'Staff Tracking',
  //   subtitle: 'Live map and route history',
  //   route: '/staff-tracking',
  // ),
  'announcements': MobileMenuEntry(
    icon: Icons.campaign_rounded,
    gradient: [Color(0xFFBF360C), Color(0xFFE65100)],
    title: 'Announcements',
    subtitle: 'Notices from your Admin',
    route: '/announcements',
  ),
  'leaderboard': MobileMenuEntry(
    icon: Icons.emoji_events_rounded,
    gradient: [Color(0xFFC8952C), Color(0xFFA9781E)],
    title: 'Leaderboard',
    subtitle: 'Top collectors and attendance',
    route: '/leaderboard',
  ),
};

/// Localized title/subtitle for a dashboard menu tile -- MobileMenuEntry
/// itself has to stay a compile-time const map (no BuildContext available
/// there), so the English strings on it are just the fallback for any menu
/// key not yet covered here.
(String, String) localizedMenuLabel(AppLocalizations l10n, String menuKey, MobileMenuEntry entry) {
  return switch (menuKey) {
    'create_center' => (l10n.menuCreateCenterTitle, l10n.menuCreateCenterSubtitle),
    'client_sourcing' => (l10n.menuClientSourcingTitle, l10n.menuClientSourcingSubtitle),
    'flo_collection' => (l10n.menuFloCollectionTitle, l10n.menuFloCollectionSubtitle),
    'client_details' => (l10n.menuClientDetailsTitle, l10n.menuClientDetailsSubtitle),
    'dormant_clients' => (l10n.menuDormantClientsTitle, l10n.menuDormantClientsSubtitle),
    'bm_approval' => (l10n.menuBmApprovalTitle, l10n.menuBmApprovalSubtitle),
    'disbursement' => (l10n.menuDisbursementTitle, l10n.menuDisbursementSubtitle),
    'dcr' => (l10n.menuDcrTitle, l10n.menuDcrSubtitle),
    'center_approval' => (l10n.menuCenterApprovalTitle, l10n.menuCenterApprovalSubtitle),
    'am_loan_approval' => (l10n.menuAmLoanApprovalTitle, l10n.menuAmLoanApprovalSubtitle),
    'am_center_transfer' => (l10n.menuAmCenterTransferTitle, l10n.menuAmCenterTransferSubtitle),
    'am_tvr' => (l10n.menuAmTvrTitle, l10n.menuAmTvrSubtitle),
    'operational_reports' => (l10n.menuOperationalReportsTitle, l10n.menuOperationalReportsSubtitle),
    // 'duty_tracking' => (l10n.menuDutyTrackingTitle, l10n.menuDutyTrackingSubtitle),
    // 'staff_tracking' => (l10n.menuStaffTrackingTitle, l10n.menuStaffTrackingSubtitle),
    'announcements' => (l10n.menuAnnouncementsTitle, l10n.menuAnnouncementsSubtitle),
    'leaderboard' => (l10n.menuLeaderboardTitle, l10n.menuLeaderboardSubtitle),
    _ => (entry.title, entry.subtitle),
  };
}
