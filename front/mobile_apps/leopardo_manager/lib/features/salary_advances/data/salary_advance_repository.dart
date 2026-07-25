import 'dart:io';

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/salary_advance.dart';
import 'package:path_provider/path_provider.dart';

class SalaryAdvanceRepository {
  final ApiClient apiClient;

  SalaryAdvanceRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 10);
  static const _uploadTimeout = Duration(seconds: 20);

  Future<List<SalaryAdvance>> getMySalaryAdvances() async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
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
      maxRetriesOverride: 0,
      timeoutOverride: proofFilePath == null ? _actionTimeout : _uploadTimeout,
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
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> approveAdvance({
    required int advanceId,
    String? comment,
    int? repaymentMonths,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/manager-approve',
      method: 'PUT',
      data: {
        if (comment != null && comment.trim().isNotEmpty)
          'decision_comment': comment.trim(),
        if (repaymentMonths != null) 'repayment_months': repaymentMonths,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> rejectAdvance({
    required int advanceId,
    required String comment,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/reject',
      method: 'PUT',
      data: {'decision_comment': comment.trim()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> markPaid({
    required int advanceId,
    String? paymentReference,
    String? paymentNote,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/mark-paid',
      method: 'PUT',
      data: {
        if (paymentReference != null && paymentReference.trim().isNotEmpty)
          'payment_reference': paymentReference.trim(),
        if (paymentNote != null && paymentNote.trim().isNotEmpty)
          'payment_note': paymentNote.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }
}
