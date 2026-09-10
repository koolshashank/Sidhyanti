import 'dart:io';

import 'package:dio/dio.dart';

import '../models/death_entry.dart';
import 'api_client.dart';
import 'endpoints.dart';

class DeathEntryApi {
  final ApiClient _client;

  DeathEntryApi(this._client);

  Future<List<DeathEntryItem>> list(int memberId) async {
    final data = await _client.get(Endpoints.membersDeathEntries, query: {'member_id': memberId});
    return (data['items'] as List<dynamic>).map((e) => DeathEntryItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> create({
    required int memberId,
    required DateTime dateOfDeath,
    String? cause,
    String? informantName,
    String? informantRelation,
    File? proofFile,
  }) async {
    final iso = '${dateOfDeath.year.toString().padLeft(4, '0')}-${dateOfDeath.month.toString().padLeft(2, '0')}-${dateOfDeath.day.toString().padLeft(2, '0')}';
    final formData = FormData.fromMap({
      'member_id': memberId,
      'date_of_death': iso,
      if (cause != null && cause.isNotEmpty) 'cause': cause,
      if (informantName != null && informantName.isNotEmpty) 'informant_name': informantName,
      if (informantRelation != null && informantRelation.isNotEmpty) 'informant_relation': informantRelation,
      if (proofFile != null) 'proof': await MultipartFile.fromFile(proofFile.path, filename: proofFile.path.split('/').last),
    });
    await _client.postMultipart(Endpoints.membersDeathEntryCreate, formData);
  }
}
