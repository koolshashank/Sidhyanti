import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/auth/auth_state.dart';
import '../../core/location/location_service.dart';
import '../../core/api/members_api.dart';
import '../../core/models/staff.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../shell/account_menu_button.dart';
import '../notifications/notifications_viewmodel.dart';
import 'dashboard_viewmodel.dart';
import 'widgets/dashboard_card.dart';
import 'widgets/festival_banner.dart';
import 'widgets/location_gate.dart';
import 'widgets/section_heading.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => DashboardViewModel(
        context.read<LocationService>(),
        context.read<MembersApi>(),
        context.read<CollectionsApi>(),
      ),
      child: const _DashboardView(),
    );
  }
}

class _DashboardView extends StatefulWidget {
  const _DashboardView();

  @override
  State<_DashboardView> createState() => _DashboardViewState();
}

class _DashboardViewState extends State<_DashboardView> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => context.read<NotificationsViewModel>().load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Re-check when the user comes back from Settings (turning location on,
    // or granting the permission) instead of making them tap Retry.
    if (state == AppLifecycleState.resumed) {
      context.read<DashboardViewModel>().checkLocation();
      context.read<NotificationsViewModel>().load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<DashboardViewModel>();
    final staff = context.watch<AuthState>().currentStaff;

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Container(
              padding: EdgeInsets.fromLTRB(20, MediaQuery.of(context).padding.top + 16, 20, 28),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF3A3D9F), Color(0xFF6A4FC7)],
                ),
                borderRadius: BorderRadius.only(bottomLeft: Radius.circular(28), bottomRight: Radius.circular(28)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: Colors.white.withValues(alpha: 0.18),
                    child: Text(
                      _initials(staff?.fullName),
                      style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _greeting(),
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 13),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          staff?.fullName ?? 'there',
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800),
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [staff?.role, staff?.designation].where((s) => s != null && s.isNotEmpty).join(' · '),
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                  const _NotificationBell(),
                  const AccountMenuButton(),
                ],
              ),
            ),
          ),
          if (vm.locationStatus == null || vm.locationStatus != LocationStatus.granted)
            SliverFillRemaining(
              hasScrollBody: false,
              child: vm.locationStatus == null
                  ? const Center(child: CircularProgressIndicator())
                  : LocationGate(
                      status: vm.locationStatus!,
                      checking: vm.locationChecking,
                      onRetry: () => context.read<DashboardViewModel>().checkLocation(),
                      onOpenLocationSettings: () => context.read<DashboardViewModel>().openLocationSettings(),
                      onOpenAppSettings: () => context.read<DashboardViewModel>().openAppSettings(),
                    ),
            )
          else if (staff?.designation == 'CM') ...[
            if (currentFestival() != null)
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
                sliver: SliverToBoxAdapter(child: FestivalBannerCard(festival: currentFestival()!)),
              ),
            const SliverPadding(
              padding: EdgeInsets.fromLTRB(20, 20, 20, 0),
              sliver: SliverToBoxAdapter(child: SectionHeading('Operation')),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              sliver: SliverGrid.count(
                crossAxisCount: 2,
                mainAxisSpacing: 16,
                crossAxisSpacing: 16,
                childAspectRatio: 0.98,
                children: [
                  DashboardCard(
                    icon: Icons.add_business_rounded,
                    gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
                    title: 'Create New Center',
                    subtitle: 'Add a center in your branch',
                    onTap: () => context.push('/create-center'),
                  ),
                  DashboardCard(
                    icon: Icons.person_add_alt_1_rounded,
                    gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
                    title: 'Client Sourcing',
                    subtitle: 'Capture a new lead',
                    onTap: () => context.push('/client-sourcing'),
                  ),
                  DashboardCard(
                    icon: Icons.payments_rounded,
                    gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
                    title: 'FLO Collection',
                    subtitle: 'Record field collections',
                    onTap: () => context.push('/collections'),
                  ),
                  DashboardCard(
                    icon: Icons.groups_rounded,
                    gradient: const [Color(0xFF4A9FFF), Color(0xFF3A6FD9)],
                    title: 'Client Details',
                    subtitle: 'View your members',
                    onTap: () => context.push('/members'),
                  ),
                ],
              ),
            ),
          ] else if (staff?.role == 'Branch Manager') ...[
            const SliverPadding(
              padding: EdgeInsets.fromLTRB(20, 20, 20, 0),
              sliver: SliverToBoxAdapter(child: SectionHeading('Operation')),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              sliver: SliverToBoxAdapter(
                child: DashboardCard(
                  icon: Icons.fact_check_rounded,
                  gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
                  title: 'Operations',
                  subtitle: 'Approvals, disbursement, reports',
                  onTap: () => context.push('/operations'),
                ),
              ),
            ),
          ] else
            SliverPadding(
              padding: const EdgeInsets.all(20),
              sliver: SliverGrid.count(
                crossAxisCount: 2,
                mainAxisSpacing: 16,
                crossAxisSpacing: 16,
                childAspectRatio: 0.98,
                children: [
                  DashboardCard(
                    icon: Icons.people_alt_rounded,
                    gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
                    title: 'My Members',
                    value: vm.memberCount?.toString() ?? '-',
                    loading: vm.statsLoading,
                    onTap: () => context.push('/members'),
                  ),
                  DashboardCard(
                    icon: Icons.payments_rounded,
                    gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
                    title: 'Due Today',
                    value: vm.todaysCollections != null ? money(vm.todaysCollections!.dueAmount) : '-',
                    subtitle: vm.todaysCollections != null ? '${vm.todaysCollections!.clientCount} clients' : null,
                    loading: vm.statsLoading,
                    onTap: () => context.push('/collections'),
                  ),
                  DashboardCard(
                    icon: Icons.trending_up_rounded,
                    gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
                    title: 'Collected Today',
                    value: vm.todaysCollections != null ? money(vm.todaysCollections!.collectedAmount) : '-',
                    loading: vm.statsLoading,
                    onTap: () => context.push('/collections'),
                  ),
                  DashboardCard(
                    icon: Icons.badge_rounded,
                    gradient: const [Color(0xFF4A9FFF), Color(0xFF3A6FD9)],
                    title: 'My Profile',
                    value: staff?.designation ?? staff?.role ?? '-',
                    subtitle: 'Tap to view details',
                    onTap: () => _showProfileSheet(context, staff),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  }

  String _initials(String? fullName) {
    if (fullName == null || fullName.trim().isEmpty) return '?';
    final parts = fullName.trim().split(RegExp(r'\s+'));
    final first = parts.first.isNotEmpty ? parts.first[0] : '';
    final second = parts.length > 1 && parts.last.isNotEmpty ? parts.last[0] : '';
    return (first + second).toUpperCase();
  }

  void _showProfileSheet(BuildContext context, Staff? staff) {
    showModalBottomSheet(
      context: context,
      builder: (context) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(staff?.fullName ?? '-', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            _ProfileRow('Role', staff?.role),
            _ProfileRow('Designation', staff?.designation),
            _ProfileRow('Branch ID', staff?.branchId?.toString()),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

class _NotificationBell extends StatelessWidget {
  const _NotificationBell();

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<NotificationsViewModel>().unreadCount;
    return IconButton(
      onPressed: () => context.push('/notifications'),
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 24),
          if (unread > 0)
            Positioned(
              top: -2,
              right: -2,
              child: Container(
                padding: const EdgeInsets.all(3),
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                decoration: const BoxDecoration(color: Color(0xFFDC3545), shape: BoxShape.circle),
                child: Text(
                  unread > 9 ? '9+' : '$unread',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ProfileRow extends StatelessWidget {
  final String label;
  final String? value;

  const _ProfileRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SizedBox(width: 110, child: Text(label, style: TextStyle(color: AppColors.textMuted(context)))),
          Expanded(child: Text(value ?? '-', style: const TextStyle(fontWeight: FontWeight.w600))),
        ],
      ),
    );
  }
}
