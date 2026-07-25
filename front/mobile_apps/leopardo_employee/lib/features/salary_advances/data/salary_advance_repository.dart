import 'dart:io';

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/salary_advance.dart';
import 'package:path_provider/path_provider.dart';

class SalaryAdvanceRepository {
  final ApiClient apiClient;

  SalaryAdvanceRepository(this.apiClient);

  static const _uploadTimeout = Duration(seconds: 20);

  Future<List<SalaryAdvance>> getMySalaryAdvances() async {
    final response = await apiClient.requestWithRetry('/salary-advances');
    final items = extractDataList(response.data);
    return items
        .map((e) => SalaryAdvance.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  // PA2-MOB-006: optionally attach a supporting document (justification,
  // quote, invoice, etc.) captured or picked on the device.
  Future<SalaryAdvance> requestAdvance({
    required double amount,
    String? reason,
    int? repaymentMonths,
    String? proofFilePath,
  }) async {
    final data = proofFilePath == null
        ? {
            'amount': amount,
            if (reason != null && reason.trim().isNotEmpty)
              'reason': reason.trim(),
            if (repaymentMonths != null) 'repayment_months': repaymentMonths,
          }
        : FormData.fromMap({
            'amount': amount,
            if (reason != null && reason.trim().isNotEmpty)
              'reason': reason.trim(),
            if (repaymentMonths != null) 'repayment_months': repaymentMonths,
            'proof': await MultipartFile.fromFile(
              proofFilePath,
              filename: proofFilePath.split('/').last,
            ),
          });

    final response = await apiClient.requestWithRetry(
      '/salary-advances',
      method: 'POST',
      data: data,
      timeoutOverride: proofFilePath == null ? null : _uploadTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  /// PA2-MOB-006: downloads the supporting document attached to a salary
  /// advance request and returns the local file path so callers can open
  /// it with the platform viewer.
  Future<String> downloadProof(int advanceId) async {
    final dir = await getApplicationDocumentsDirectory();
    final filePath = '${dir.path}/salary_advance_proof_$advanceId';
    final file = File(filePath);

    if (await file.exists()) {
      return filePath;
    }

    await apiClient.dio.download(
      '/salary-advances/$advanceId/proof',
      filePath,
      options: Options(responseType: ResponseType.bytes),
    );

    return filePath;
  }

  Future<SalaryAdvance> cancelAdvance(int advanceId) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> confirmReceived(int advanceId) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/confirm-received',
      method: 'PUT',
      maxRetriesOverride: 0,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }
}
