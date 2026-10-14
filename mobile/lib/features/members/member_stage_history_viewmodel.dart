import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class MemberStageHistoryViewModel extends ChangeNotifier {
  final MembersApi _membersApi;
  final int memberId;

  MemberStageHistoryViewModel(this._membersApi, this.memberId) {
    load();
  }

  List<JourneyEvent> items = [];
  bool loading = false;
  String? error;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      items = await _membersApi.journey(memberId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
