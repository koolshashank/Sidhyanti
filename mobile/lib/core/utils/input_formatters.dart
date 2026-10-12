import 'package:flutter/services.dart';

/// Forces every keystroke to upper case -- used across the field officer's
/// data-entry forms (client sourcing, co-borrower, create center, loan
/// proposal, KYC, death entry) so the whole form is always filled in
/// capitals, regardless of the device's own keyboard casing.
final upperCaseInputFormatter = TextInputFormatter.withFunction(
  (oldValue, newValue) => newValue.copyWith(text: newValue.text.toUpperCase()),
);

/// Upper-cases and strips anything but letters/digits -- used on PAN and
/// Voter ID fields so a pasted value with spaces/hyphens still matches the
/// validators in form_validators.dart instead of silently failing them.
final alphaNumericUpperCaseInputFormatter = TextInputFormatter.withFunction(
  (oldValue, newValue) => newValue.copyWith(
    text: newValue.text.toUpperCase().replaceAll(RegExp(r'[^A-Z0-9]'), ''),
  ),
);
