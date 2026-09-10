import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/api/members_api.dart';
import '../../core/models/center_summary.dart';
import '../../core/models/member.dart';

class MyCentersViewModel extends ChangeNotifier {
  final CentersApi _centersApi;
  final MembersApi _membersApi;

  MyCentersViewModel(this._centersApi, this._membersApi) {
    load();
  }

  bool loading = true;
  String? error;
  List<CenterSummary> centers = [];

  IncompleteJourney? incompleteJourney;
  bool discarding = false;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([_centersApi.myCenters(), _membersApi.incompleteJourney()]);
      centers = results[0] as List<CenterSummary>;
      incompleteJourney = results[1] as IncompleteJourney?;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> discardIncompleteJourney() async {
    final journey = incompleteJourney;
    if (journey == null) return;
    discarding = true;
    notifyListeners();
    try {
      await _membersApi.discardJourney(journey.memberId);
      incompleteJourney = null;
    } on ApiException {
      // Leave the banner as-is; the user can just retry the Discard tap.
    } finally {
      discarding = false;
      notifyListeners();
    }
  }
}
