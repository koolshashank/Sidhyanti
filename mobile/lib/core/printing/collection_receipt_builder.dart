import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';

/// Builds the ESC/POS byte sequence for a 58mm thermal receipt after a
/// collection is recorded. Plain-text layout (no logo/QR) -- field
/// printers in the kind of low-cost thermal units used here rarely have
/// enough buffer/speed to make a bitmap header worthwhile.
Future<List<int>> buildCollectionReceipt({
  required String borrowerName,
  required String loanNo,
  required int installmentNo,
  required String amount,
  required String collectionDate,
  String? branchName,
}) async {
  final profile = await CapabilityProfile.load();
  final generator = Generator(PaperSize.mm58, profile);
  final bytes = <int>[];

  bytes.addAll(generator.text(
    'Sidhyanti CRM',
    styles: const PosStyles(align: PosAlign.center, bold: true, height: PosTextSize.size2, width: PosTextSize.size2),
  ));
  if (branchName != null && branchName.isNotEmpty) {
    bytes.addAll(generator.text(branchName, styles: const PosStyles(align: PosAlign.center)));
  }
  bytes.addAll(generator.text('Payment Receipt', styles: const PosStyles(align: PosAlign.center, bold: true)));
  bytes.addAll(generator.hr());
  bytes.addAll(generator.text('Client: $borrowerName'));
  bytes.addAll(generator.text('Loan No: $loanNo'));
  bytes.addAll(generator.text('Installment #$installmentNo'));
  bytes.addAll(generator.text('Date: $collectionDate'));
  bytes.addAll(generator.hr());
  bytes.addAll(generator.text(
    'Amount: $amount',
    styles: const PosStyles(bold: true, height: PosTextSize.size2, width: PosTextSize.size2),
  ));
  bytes.addAll(generator.hr());
  bytes.addAll(generator.text('Thank you for your payment', styles: const PosStyles(align: PosAlign.center)));
  bytes.addAll(generator.feed(2));
  bytes.addAll(generator.cut());
  return bytes;
}
