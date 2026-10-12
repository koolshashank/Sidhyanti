import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/api/app_version_api.dart';
import '../../core/auth/auth_state.dart';
import '../../core/auth/pin_service.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/theme_controller.dart';
import '../../core/update/update_checker.dart';
import '../auth/pin_setup_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool _pinEnabled = false;
  String? _versionLabel;

  @override
  void initState() {
    super.initState();
    _loadPinStatus();
    _loadVersionLabel();
  }

  Future<void> _loadVersionLabel() async {
    final info = await PackageInfo.fromPlatform();
    if (mounted) setState(() => _versionLabel = 'Version ${info.version} (${info.buildNumber})');
  }

  Future<void> _loadPinStatus() async {
    final has = await PinService.hasPin();
    if (mounted) setState(() => _pinEnabled = has);
  }

  Future<void> _onAppLockTap(BuildContext context) async {
    if (_pinEnabled) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Disable App Lock?'),
          content: const Text('You will no longer need a PIN to open the app.'),
          actions: [
            TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: const Text('Cancel')),
            TextButton(onPressed: () => Navigator.of(dialogContext).pop(true), child: const Text('Disable')),
          ],
        ),
      );
      if (confirmed == true) {
        await PinService.clearPin();
        if (mounted) setState(() => _pinEnabled = false);
      }
    } else {
      final created = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const PinSetupScreen()));
      if (created == true) await _loadPinStatus();
    }
  }

  Future<void> _onChangePinTap(BuildContext context) async {
    await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const PinSetupScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final staff = context.watch<AuthState>().currentStaff;
    final themeMode = context.watch<ThemeController>().mode;

    return Scaffold(
      backgroundColor: AppColors.scaffoldBackground(context),
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Container(
              padding: EdgeInsets.fromLTRB(20, MediaQuery.of(context).padding.top + 8, 20, 28),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFFBF360C), Color(0xFFE65100)],
                ),
                borderRadius: BorderRadius.only(bottomLeft: Radius.circular(28), bottomRight: Radius.circular(28)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: () => Navigator.of(context).maybePop(),
                        icon: const Icon(Icons.arrow_back, color: Colors.white),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                      const SizedBox(width: 4),
                      const Text('Account', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 30,
                        backgroundColor: Colors.white.withValues(alpha: 0.18),
                        child: Text(
                          _initials(staff?.fullName),
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              staff?.fullName ?? 'Unknown',
                              style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800),
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [staff?.role, staff?.designation].where((s) => s != null && s.isNotEmpty).join(' · '),
                              style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
            sliver: SliverList.list(
              children: [
                _SectionCard(
                  children: [
                    _AccountTile(
                      icon: Icons.dark_mode_outlined,
                      iconColor: const Color(0xFFBF360C),
                      title: 'Theme',
                      subtitle: _themeLabel(themeMode),
                      onTap: () => _showThemeSheet(context),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _SectionCard(
                  children: [
                    _AccountTile(
                      icon: Icons.lock_outline_rounded,
                      iconColor: const Color(0xFFE65100),
                      title: 'App Lock',
                      subtitle: _pinEnabled ? 'Enabled' : 'Disabled',
                      onTap: () => _onAppLockTap(context),
                    ),
                    if (_pinEnabled)
                      _AccountTile(
                        icon: Icons.password_rounded,
                        iconColor: const Color(0xFFE65100),
                        title: 'Change PIN',
                        onTap: () => _onChangePinTap(context),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                _SectionCard(
                  children: [
                    _AccountTile(
                      icon: Icons.system_update_rounded,
                      iconColor: const Color(0xFF2FBF87),
                      title: 'Check for Update',
                      subtitle: 'Install the latest version of the app',
                      onTap: () => UpdateChecker.checkManually(context, context.read<AppVersionApi>()),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _SectionCard(
                  children: [
                    _AccountTile(
                      icon: Icons.logout_rounded,
                      iconColor: const Color(0xFFDC3545),
                      title: 'Log Out',
                      titleColor: const Color(0xFFDC3545),
                      showChevron: false,
                      onTap: () => _confirmLogout(context),
                    ),
                  ],
                ),
                if (_versionLabel != null) ...[
                  const SizedBox(height: 20),
                  Center(
                    child: Text(
                      _versionLabel!,
                      style: TextStyle(fontSize: 12, color: AppColors.textFaint(context)),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _initials(String? fullName) {
    final parts = (fullName ?? '').trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    final first = parts.first[0];
    final last = parts.length > 1 ? parts.last[0] : '';
    return (first + last).toUpperCase();
  }

  String _themeLabel(ThemeMode mode) => switch (mode) {
        ThemeMode.system => 'System Default',
        ThemeMode.light => 'Light',
        ThemeMode.dark => 'Dark',
      };

  void _showThemeSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Consumer<ThemeController>(
          builder: (context, controller, _) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text('App Theme', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context))),
                ),
              ),
              ...ThemeMode.values.map((mode) {
                return RadioListTile<ThemeMode>(
                  value: mode,
                  groupValue: controller.mode,
                  title: Text(_themeLabel(mode)),
                  onChanged: (value) {
                    if (value != null) controller.setMode(value);
                    Navigator.of(sheetContext).pop();
                  },
                );
              }),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  void _confirmLogout(BuildContext context) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Log Out'),
        content: const Text('Are you sure you want to log out?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              context.read<AuthState>().logout();
            },
            child: const Text('Log Out', style: TextStyle(color: Color(0xFFDC3545))),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final List<Widget> children;

  const _SectionCard({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(children: children),
    );
  }
}

class _AccountTile extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String title;
  final Color? titleColor;
  final String? subtitle;
  final bool showChevron;
  final VoidCallback onTap;

  const _AccountTile({
    required this.icon,
    required this.iconColor,
    required this.title,
    this.titleColor,
    this.subtitle,
    this.showChevron = true,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          child: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, color: iconColor, size: 18),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: titleColor ?? AppColors.textPrimary(context)),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(subtitle!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                    ],
                  ],
                ),
              ),
              if (showChevron) Icon(Icons.chevron_right_rounded, color: AppColors.textFaint(context)),
            ],
          ),
        ),
      ),
    );
  }
}
