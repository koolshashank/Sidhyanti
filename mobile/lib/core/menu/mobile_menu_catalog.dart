import 'package:flutter/material.dart';

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
    gradient: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
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
  'reloan': MobileMenuEntry(
    icon: Icons.autorenew_rounded,
    gradient: [Color(0xFF14A9AB), Color(0xFF0F8B8D)],
    title: 'Reloan',
    subtitle: 'Clients eligible for reloan',
    route: '/reloan',
  ),
  'bm_approval': MobileMenuEntry(
    icon: Icons.fact_check_rounded,
    gradient: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
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
    subtitle: 'Daily collection report',
    route: '/dcr',
  ),
  'center_approval': MobileMenuEntry(
    icon: Icons.location_city_rounded,
    gradient: [Color(0xFFE57373), Color(0xFFD32F2F)],
    title: 'Center Approval',
    subtitle: 'Review new centers',
    route: '/center-approval',
  ),
};
