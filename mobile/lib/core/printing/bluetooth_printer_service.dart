import 'package:permission_handler/permission_handler.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

/// Thin wrapper around print_bluetooth_thermal -- a paired thermal printer
/// is connected to, written to, and disconnected from for each receipt
/// (rather than holding one connection open for the whole app session,
/// since a field officer's printer may go in and out of range between
/// collections).
class BluetoothPrinterService {
  Future<bool> requestPermissions() async {
    final statuses = await [Permission.bluetoothConnect, Permission.bluetoothScan].request();
    return statuses.values.every((s) => s.isGranted);
  }

  Future<List<BluetoothInfo>> pairedPrinters() => PrintBluetoothThermal.pairedBluetooths;

  Future<bool> printReceipt(String macAddress, List<int> bytes) async {
    final connected = await PrintBluetoothThermal.connect(macPrinterAddress: macAddress);
    if (!connected) return false;
    try {
      return await PrintBluetoothThermal.writeBytes(bytes);
    } finally {
      await PrintBluetoothThermal.disconnect;
    }
  }
}
