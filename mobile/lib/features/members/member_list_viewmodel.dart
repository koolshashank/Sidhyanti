import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/models/member.dart';

class MemberListViewModel extends ChangeNotifier {
  final MembersApi _membersApi;

  MemberListViewModel(this._membersApi) {
    loadInitial();
  }

  final List<MemberSummary> items = [];
  bool loading = false;
  bool loadingMore = false;
  bool hasMore = true;
  String? error;
  String query = '';
  int? stageId;
  int _page = 1;
  Timer? _debounce;

  Future<void> loadInitial() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await _membersApi.list(page: 1, q: query, stageId: stageId);
      items
        ..clear()
        ..addAll(result.items);
      _page = result.page;
      hasMore = result.hasMore;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> loadMore() async {
    if (loadingMore || !hasMore) return;
    loadingMore = true;
    notifyListeners();
    try {
      final result = await _membersApi.list(page: _page + 1, q: query, stageId: stageId);
      items.addAll(result.items);
      _page = result.page;
      hasMore = result.hasMore;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      loadingMore = false;
      notifyListeners();
    }
  }

  void onSearchChanged(String value) {
    query = value;
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), loadInitial);
  }

  void onStageChanged(int? value) {
    if (stageId == value) return;
    stageId = value;
    loadInitial();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }
}
