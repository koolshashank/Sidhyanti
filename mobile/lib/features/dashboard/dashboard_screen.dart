import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/api/collections_api.dart';
import '../../core/api/hr_api.dart';
import '../../core/api/loans_api.dart';
import '../../core/api/members_api.dart';
import '../../core/auth/auth_state.dart';
import '../../core/location/location_service.dart';
import '../../core/menu/mobile_menu_catalog.dart';
import '../../core/update/app_settings_state.dart';
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
        context.read<LoansApi>(),
        context.read<CentersApi>(),
        context.read<HrApi>(),
        context.read<CollectionsApi>(),
        context.read<MembersApi>(),
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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationsViewModel>().load();
      _loadPendingCounts();
    });
  }

  Future<void> _loadPendingCounts() {
    final menuItems = context.read<AuthState>().currentStaff?.menuItems ?? const [];
    return context.read<DashboardViewModel>().loadPendingCounts(menuItems);
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
      _loadPendingCounts();
    }
  }

  /// Pull-to-refresh also re-fetches the staff's own profile/menu_items, so
  /// an Admin granting or revoking a mobile menu item on
  /// mobile_menu_access.php shows up here immediately -- no logout/login
  /// needed.
  Future<void> _refresh(BuildContext context) async {
    await context.read<AuthState>().refreshProfile();
    if (!context.mounted) return;
    await Future.wait([
      context.read<DashboardViewModel>().checkLocation(),
      _loadPendingCounts(),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<DashboardViewModel>();
    final staff = context.watch<AuthState>().currentStaff;
    final showFestivalBanner = context.watch<AppSettingsState>().showFestivalBanner;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => _refresh(context),
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Container(
                padding: EdgeInsets.fromLTRB(20, MediaQuery.of(context).padding.top + 16, 20, 28),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFBF360C), Color(0xFFE65100)],
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
            else if (staff != null && staff.menuItems.isNotEmpty) ...[
              if (showFestivalBanner && currentFestival() != null)
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
                  sliver: SliverToBoxAdapter(child: FestivalBannerCard(festival: currentFestival()!)),
                ),
              const SliverPadding(
                padding: EdgeInsets.fromLTRB(20, 20, 20, 0),
                sliver: SliverToBoxAdapter(child: SectionHeading('Menu')),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
                sliver: SliverGrid.count(
                  crossAxisCount: 2,
                  mainAxisSpacing: 16,
                  crossAxisSpacing: 16,
                  childAspectRatio: 0.98,
                  children: [
                    for (final key in staff.menuItems)
                      if (mobileMenuCatalog[key] case final entry?)
                        DashboardCard(
                          icon: entry.icon,
                          gradient: entry.gradient,
                          title: entry.title,
                          subtitle: entry.subtitle,
                          onTap: () => context.push(entry.route),
                          badgeCount: vm.pendingCounts[key] ?? 0,
                        ),
                  ],
                ),
              ),
            ] else
              SliverFillRemaining(
                hasScrollBody: false,
                child: _NotAuthorized(onRefresh: () => _refresh(context)),
              ),
          ],
        ),
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
}

class _NotAuthorized extends StatelessWidget {
  final VoidCallback onRefresh;

  const _NotAuthorized({required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(color: const Color(0xFFE65100).withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.lock_outline_rounded, size: 34, color: Color(0xFFE65100)),
            ),
            const SizedBox(height: 20),
            const Text(
              'You are not authorized to access any service',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(
              'Please contact your administrator to get access to the screens you need.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 20),
            OutlinedButton.icon(
              onPressed: onRefresh,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Check Again'),
            ),
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
