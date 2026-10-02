import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_state.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_controller.dart';
import 'routing/app_router.dart';
import 'shell/sos_button.dart';

class App extends StatefulWidget {
  const App({super.key});

  @override
  State<App> createState() => _AppState();
}

class _AppState extends State<App> with WidgetsBindingObserver {
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final authState = context.read<AuthState>();
    _router = buildAppRouter(authState);
    authState.restoreSession();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Catches the day rolling over to "not checked in yet" while the app
    // sat open (foreground or backgrounded) overnight.
    if (state == AppLifecycleState.resumed) {
      context.read<AuthState>().recheckAttendanceOnResume();
    }
  }

  @override
  Widget build(BuildContext context) {
    final themeMode = context.watch<ThemeController>().mode;
    final isAuthenticated = context.watch<AuthState>().status == AuthStatus.authenticated;
    return MaterialApp.router(
      title: 'Sidhyanti CRM',
      debugShowCheckedModeBanner: false,
      theme: buildLightTheme(),
      darkTheme: buildDarkTheme(),
      themeMode: themeMode,
      routerConfig: _router,
      builder: (context, child) => Stack(
        children: [
          ?child,
          if (isAuthenticated) const SosButton(),
        ],
      ),
    );
  }
}
