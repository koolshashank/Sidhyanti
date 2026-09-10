import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class MemberDetailViewModel extends ChangeNotifier {
  final MembersApi _membersApi;
  final int memberId;

  MemberDetailViewModel(this._membersApi, this.memberId) {
    load();
  }

  MemberDetail? member;
  bool loading = false;
  String? error;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      member = await _membersApi.detail(memberId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
