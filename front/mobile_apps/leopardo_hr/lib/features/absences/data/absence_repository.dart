import 'dart:io';

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/absence.dart';
import 'package:path_provider/path_provider.dart';

class AbsenceRepository {
  final ApiClient apiClient;

  AbsenceRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 10);
  static const _uploadTimeout = Duration(seconds: 20);

  Future<List<Absence>> getMyAbsences() async {
    final response = await apiClient.requestWithRetry(
      '/absences',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => Absence.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<Map<String, dynamic>>> getLeaveBalances() async {
    final response = await apiClient.requestWithRetry(
      '/me/leave-balances',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.cast<Map<String, dynamic>>();
  }

  // PA2-MOB-006: optionally attach a supporting document (medical note,
  // justification letter, etc.) captured or picked on the device.
  Future<Absence> requestAbsence({
    required int absenceTypeId,
    required DateTime startDate,
    required DateTime endDate,
    String? reason,
    String? proofFilePath,
  }) async {
    final startStr = startDate.toIso8601String().split('T')[0];
    final endStr = endDate.toIso8601String().split('T')[0];

    final data = proofFilePath == null
        ? {
            'absence_type_id': absenceTypeId,
            'start_date': startStr,
            'end_date': endStr,
            'reason': reason,
          }
        : FormData.fromMap({
            'absence_type_id': absenceTypeId,
            'start_date': startStr,
            'end_date': endStr,
            if (reason != null) 'reason': reason,
            'proof': await MultipartFile.fromFile(
              proofFilePath,
              filename: proofFilePath.split('/').last,
            ),
          });

    final response = await apiClient.requestWithRetry(
      '/absences',
      method: 'POST',
      data: data,
      maxRetriesOverride: 0,
      timeoutOverride: proofFilePath == null ? _actionTimeout : _uploadTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  /// PA2-MOB-006: downloads the supporting document attached to an
  /// absence request and returns the local file path so callers can open
  /// it with the platform viewer.
  Future<String> downloadProof(int absenceId) async {
    final dir = await getApplicationDocumentsDirectory();
    final filePath = '${dir.path}/absence_proof_$absenceId';
    final file = File(filePath);

    if (await file.exists()) {
      return filePath;
    }

    await apiClient.dio.download(
      '/absences/$absenceId/proof',
      filePath,
      options: Options(responseType: ResponseType.bytes),
    );

    return filePath;
  }

  Future<Absence> cancelAbsence(int absenceId) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  Future<Absence> approveAbsence(int absenceId) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId/approve',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  Future<Absence> rejectAbsence({
    required int absenceId,
    required String reason,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId/reject',
      method: 'PUT',
      data: {'rejected_reason': reason.trim()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }
}
