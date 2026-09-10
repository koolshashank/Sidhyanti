import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'core/api/api_client.dart';
import 'core/api/app_version_api.dart';
import 'core/api/centers_api.dart';
import 'core/api/coborrowers_api.dart';
import 'core/api/collections_api.dart';
import 'core/api/death_entry_api.dart';
import 'core/api/hr_api.dart';
import 'core/api/loans_api.dart';
import 'core/api/members_api.dart';
import 'core/api/notifications_api.dart';
import 'core/api/reports_api.dart';
import 'core/api/sos_api.dart';
import 'core/auth/auth_repository.dart';
import 'core/auth/auth_state.dart';
import 'core/auth/token_storage.dart';
import 'core/location/location_service.dart';
import 'core/theme/theme_controller.dart';
import 'features/notifications/notifications_viewmodel.dart';

void main() {
  final tokenStorage = TokenStorage();
  final apiClient = ApiClient(tokenStorage);
  final authRepository = AuthRepository(apiClient, tokenStorage);

  runApp(
    MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: apiClient),
        Provider<MembersApi>(create: (_) => MembersApi(apiClient)),
        Provider<LoansApi>(create: (_) => LoansApi(apiClient)),
        Provider<CollectionsApi>(create: (_) => CollectionsApi(apiClient)),
        Provider<CentersApi>(create: (_) => CentersApi(apiClient)),
        Provider<CoborrowersApi>(create: (_) => CoborrowersApi(apiClient)),
        Provider<HrApi>(create: (_) => HrApi(apiClient)),
        Provider<DeathEntryApi>(create: (_) => DeathEntryApi(apiClient)),
        Provider<ReportsApi>(create: (_) => ReportsApi(apiClient)),
        Provider<NotificationsApi>(create: (_) => NotificationsApi(apiClient)),
        Provider<SosApi>(create: (_) => SosApi(apiClient)),
        Provider<AppVersionApi>(create: (_) => AppVersionApi(apiClient)),
        Provider<LocationService>(create: (_) => LocationService()),
        ChangeNotifierProvider<AuthState>(create: (_) => AuthState(authRepository, apiClient)),
        // Not auto-loaded here -- fetching before login would hit the API
        // unauthenticated. Dashboard/Notifications screens trigger load().
        ChangeNotifierProvider<NotificationsViewModel>(
          create: (context) => NotificationsViewModel(context.read<NotificationsApi>()),
        ),
        ChangeNotifierProvider<ThemeController>(create: (_) => ThemeController()),
      ],
      child: const App(),
    ),
  );
}
