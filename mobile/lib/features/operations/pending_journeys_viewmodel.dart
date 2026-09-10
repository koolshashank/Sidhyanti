import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class PendingJourneysViewModel extends ChangeNotifier {
  final MembersApi _membersApi;

  PendingJourneysViewModel(this._membersApi) {
    load();
  }

  bool loading = true;
  String? error;
  List<IncompleteJourney> journeys = [];
  int? discardingMemberId;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      journeys = await _membersApi.pendingJourneys();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> discard(int memberId) async {
    discardingMemberId = memberId;
    notifyListeners();
    try {
      await _membersApi.discardJourney(memberId);
      journeys = journeys.where((j) => j.memberId != memberId).toList();
    } on ApiException {
      // Leave the card as-is; the user can just retry the Discard tap.
    } finally {
      discardingMemberId = null;
      notifyListeners();
    }
  }
}
