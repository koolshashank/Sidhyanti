import 'package:flutter/material.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

import '../../../core/printing/bluetooth_printer_service.dart';
import '../../../core/printing/printer_preference_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../l10n/generated/app_localizations.dart';

/// Lets the staff member pick one of their phone's already-paired Bluetooth
/// thermal printers -- the choice is remembered (PrinterPreferenceStore) so
/// this only needs to run once. Returns the picked (name, macAddress), or
/// null if cancelled/no printers found.
Future<(String name, String macAddress)?> showPrinterPickerSheet(BuildContext context) {
  return showModalBottomSheet<(String, String)>(
    context: context,
    isScrollControlled: true,
    builder: (context) => const _PrinterPickerSheet(),
  );
}

class _PrinterPickerSheet extends StatefulWidget {
  const _PrinterPickerSheet();

  @override
  State<_PrinterPickerSheet> createState() => _PrinterPickerSheetState();
}

class _PrinterPickerSheetState extends State<_PrinterPickerSheet> {
  final _service = BluetoothPrinterService();
  List<BluetoothInfo> _devices = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final granted = await _service.requestPermissions();
    if (!granted) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = AppLocalizations.of(context)!.printerBluetoothPermissionDenied;
        });
      }
      return;
    }
    try {
      final devices = await _service.pairedPrinters();
      if (!mounted) return;
      setState(() {
        _devices = devices;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.printerSelectTitle, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              l10n.printerSelectHint,
              style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: Center(child: CircularProgressIndicator()))
            else if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Column(
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton(onPressed: _load, child: Text(l10n.commonRetry)),
                  ],
                ),
              )
            else if (_devices.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(l10n.printerNonePaired, textAlign: TextAlign.center),
              )
            else
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 320),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: _devices.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 4),
                  itemBuilder: (context, index) {
                    final device = _devices[index];
                    return ListTile(
                      leading: const Icon(Icons.print_rounded),
                      title: Text(device.name),
                      subtitle: Text(device.macAdress, style: const TextStyle(fontSize: 11)),
                      onTap: () async {
                        await PrinterPreferenceStore.save(name: device.name, macAddress: device.macAdress);
                        if (context.mounted) Navigator.of(context).pop((device.name, device.macAdress));
                      },
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
