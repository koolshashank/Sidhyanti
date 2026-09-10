import 'package:flutter/foundation.dart';

/// App-wide, admin-toggleable settings (see app_settings.php on the web
/// CRM) -- populated once per launch from api/v1/app/version.php's
/// response (checked from the splash screen alongside the update check,
/// so this never needs its own network round trip).
class AppSettingsState extends ChangeNotifier {
  bool showFestivalBanner = true;

  void update({required bool showFestivalBanner}) {
    this.showFestivalBanner = showFestivalBanner;
    notifyListeners();
  }
}
