import 'package:permission_handler/permission_handler.dart';

/// Where the user stands in the permission flow Duty Tracking needs:
/// "while in use" location first, then "always" (background) location,
/// then (Android 13+) the notification permission for the persistent duty
/// notification. Each is requested in that order; a denial anywhere stops
/// the flow and tells the caller exactly which step failed so the UI can
/// explain it and, if permanently denied, offer to open Settings.
enum TrackingPermissionResult { granted, whileInUseDenied, backgroundDenied, notificationDenied }

class TrackingPermissionService {
  /// Runs the full step-by-step request flow. Safe to call repeatedly --
  /// if everything is already granted it resolves immediately.
  Future<TrackingPermissionResult> requestAll() async {
    var whileInUse = await Permission.locationWhenInUse.status;
    if (!whileInUse.isGranted) {
      whileInUse = await Permission.locationWhenInUse.request();
    }
    if (!whileInUse.isGranted) {
      return TrackingPermissionResult.whileInUseDenied;
    }

    var always = await Permission.locationAlways.status;
    if (!always.isGranted) {
      always = await Permission.locationAlways.request();
    }
    if (!always.isGranted) {
      return TrackingPermissionResult.backgroundDenied;
    }

    final notification = await Permission.notification.request();
    if (!notification.isGranted) {
      return TrackingPermissionResult.notificationDenied;
    }

    return TrackingPermissionResult.granted;
  }

  /// True if the denial for [result]'s step is permanent (user must go to
  /// Settings -- requesting again from the dialog won't prompt at all).
  Future<bool> isPermanentlyDenied(TrackingPermissionResult result) async {
    switch (result) {
      case TrackingPermissionResult.whileInUseDenied:
        return (await Permission.locationWhenInUse.status).isPermanentlyDenied;
      case TrackingPermissionResult.backgroundDenied:
        return (await Permission.locationAlways.status).isPermanentlyDenied;
      case TrackingPermissionResult.notificationDenied:
        return (await Permission.notification.status).isPermanentlyDenied;
      case TrackingPermissionResult.granted:
        return false;
    }
  }

  Future<void> openSettings() => openAppSettings();
}
