import 'package:flutter/services.dart';

/// Forces every keystroke to upper case -- used across the field officer's
/// data-entry forms (client sourcing, co-borrower, create center, loan
/// proposal, KYC, death entry) so the whole form is always filled in
/// capitals, regardless of the device's own keyboard casing.
final upperCaseInputFormatter = TextInputFormatter.withFunction(
  (oldValue, newValue) => newValue.copyWith(text: newValue.text.toUpperCase()),
);
