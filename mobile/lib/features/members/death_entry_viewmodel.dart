import 'dart:io';

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/death_entry_api.dart';
import '../../core/models/death_entry.dart';

class DeathEntryViewModel extends ChangeNotifier {
  final DeathEntryApi _api;
  final int memberId;

  DeathEntryViewModel(this._api, this.memberId) {
    load();
  }

  bool loading = true;
  String? error;
  List<DeathEntryItem> entries = [];

  bool submitting = false;
  String? submitError;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      entries = await _api.list(memberId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<bool> submit({
    required DateTime dateOfDeath,
    String? cause,
    String? informantName,
    String? informantRelation,
    File? proofFile,
  }) async {
    submitting = true;
    submitError = null;
    notifyListeners();
    try {
      await _api.create(
        memberId: memberId,
        dateOfDeath: dateOfDeath,
        cause: cause,
        informantName: informantName,
        informantRelation: informantRelation,
        proofFile: proofFile,
      );
      await load();
      return true;
    } on ApiException catch (e) {
      submitError = e.message;
      return false;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }
}
