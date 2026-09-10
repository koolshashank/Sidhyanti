import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

import '../api/app_version_api.dart';

/// Checks the server for a newer build than the one currently installed,
/// and if found, shows a one-tap "download + open installer" dialog.
/// Android never allows a normal (non-Play-Store) app to install itself
/// silently -- the user still taps "Install" once in the system installer
/// that pops up after the download completes.
class UpdateChecker {
  static Future<void> checkAndPrompt(BuildContext context, AppVersionApi api) async {
    try {
      final info = await api.latest();
      final packageInfo = await PackageInfo.fromPlatform();
      final currentBuild = int.tryParse(packageInfo.buildNumber) ?? 0;
      if (info.versionCode <= currentBuild) return;
      if (!context.mounted) return;

      await showDialog<void>(
        context: context,
        barrierDismissible: !info.forceUpdate,
        builder: (dialogContext) => PopScope(
          canPop: !info.forceUpdate,
          child: _UpdateDialog(info: info),
        ),
      );
    } catch (_) {
      // Best-effort -- a network hiccup here must never block using the app.
    }
  }
}

class _UpdateDialog extends StatefulWidget {
  final AppVersionInfo info;

  const _UpdateDialog({required this.info});

  @override
  State<_UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends State<_UpdateDialog> {
  bool _downloading = false;
  double _progress = 0;
  String? _error;

  Future<void> _updateNow() async {
    setState(() {
      _downloading = true;
      _progress = 0;
      _error = null;
    });
    try {
      final dir = await getExternalStorageDirectory() ?? await getTemporaryDirectory();
      final filePath = '${dir.path}/sidhyanti-crm-v2-update.apk';
      await Dio().download(
        widget.info.apkUrl,
        filePath,
        onReceiveProgress: (received, total) {
          if (total > 0 && mounted) setState(() => _progress = received / total);
        },
      );
      if (!mounted) return;
      await OpenFilex.open(filePath);
    } catch (e) {
      if (mounted) setState(() => _error = "Couldn't download the update. Check your connection and try again.");
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final info = widget.info;
    return AlertDialog(
      icon: const Icon(Icons.system_update_rounded, color: Color(0xFF6A4FC7), size: 32),
      title: Text('Update Available (v${info.versionName})'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (info.releaseNotes.isNotEmpty) Text(info.releaseNotes),
          if (_downloading) ...[
            const SizedBox(height: 16),
            LinearProgressIndicator(value: _progress > 0 ? _progress : null),
            const SizedBox(height: 6),
            Text(
              _progress > 0 ? '${(_progress * 100).toStringAsFixed(0)}%' : 'Starting download...',
              style: const TextStyle(fontSize: 12),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.red)),
          ],
        ],
      ),
      actions: [
        if (!info.forceUpdate && !_downloading)
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Later')),
        FilledButton(
          onPressed: _downloading ? null : _updateNow,
          child: Text(_downloading ? 'Downloading...' : 'Update Now'),
        ),
      ],
    );
  }
}
