import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class MemberDocumentsViewModel extends ChangeNotifier {
  final MembersApi _membersApi;
  final int memberId;

  MemberDocumentsViewModel(this._membersApi, this.memberId) {
    load();
  }

  List<MemberDocument> documents = [];
  bool loading = false;
  String? error;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      documents = await _membersApi.documents(memberId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
