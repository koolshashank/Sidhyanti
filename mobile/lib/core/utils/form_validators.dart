/// Shared field-format validators for the field officer's data-entry forms
/// (client sourcing, co-borrower, loan proposal bank details). All of these
/// treat an empty value as valid -- only Mobile No. is actually required at
/// the Member table level (see MembersApi.create's docstring); the rest
/// (Aadhar/PAN/Voter ID/pincode/bank fields) are optional columns that just
/// need to be well-formed *if* the FO fills them in, so a blank field never
/// blocks submission on its own.
library;

String? validateRequired(String? value) => (value == null || value.trim().isEmpty) ? 'Required' : null;

String? validateMobile(String? value, {bool required = true}) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return required ? 'Mobile number is required' : null;
  if (!RegExp(r'^[6-9]\d{9}$').hasMatch(v)) return 'Enter a valid 10-digit mobile number';
  return null;
}

String? validateAadhar(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (!RegExp(r'^\d{12}$').hasMatch(v)) return 'Aadhar number must be exactly 12 digits';
  return null;
}

String? validatePan(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (!RegExp(r'^[A-Z]{5}[0-9]{4}[A-Z]$').hasMatch(v)) return 'Enter a valid PAN (e.g. ABCDE1234F)';
  return null;
}

String? validateVoterId(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (v.length > 15) return 'Voter ID can be at most 15 characters';
  if (!RegExp(r'^[A-Z0-9]+$').hasMatch(v)) return 'Voter ID can only contain letters and numbers';
  return null;
}

String? validatePincode(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (!RegExp(r'^\d{6}$').hasMatch(v)) return 'Enter a valid 6-digit pincode';
  return null;
}

String? validateIfsc(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (!RegExp(r'^[A-Z]{4}0[A-Z0-9]{6}$').hasMatch(v)) return 'Enter a valid 11-character IFSC code';
  return null;
}

String? validateBankAccountNumber(String? value) {
  final v = value?.trim() ?? '';
  if (v.isEmpty) return null;
  if (!RegExp(r'^\d{9,18}$').hasMatch(v)) return 'Enter a valid bank account number (9-18 digits)';
  return null;
}
