import 'dart:io';
import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/payment_document.dart';
import 'package:leopardo_core/models/payroll.dart';
import 'package:leopardo_core/models/payroll_balance.dart';
import 'package:path_provider/path_provider.dart';

class PayrollRepository {
  final ApiClient apiClient;

  PayrollRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);

  Future<List<Payroll>> getMyPayrolls() async {
    final response = await apiClient.requestWithRetry(
      '/payrolls',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Payroll.fromJson(e)).toList();
  }

  Future<PayrollBalance> getMyBalance() async {
    final response = await apiClient.requestWithRetry(
      '/me/balance',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return PayrollBalance.fromJson(extractDataMap(response.data));
  }

  Future<String> downloadPayslipPdf(int payslipId) async {
    final dir = await getApplicationDocumentsDirectory();
    final filePath = '${dir.path}/payslip_$payslipId.pdf';
    final file = File(filePath);

    if (await file.exists()) {
      return filePath;
    }

    await apiClient.dio.download(
      '/me/pay-slips/$payslipId/pdf',
      filePath,
      options: Options(
        responseType: ResponseType.bytes,
        headers: {'Accept': 'application/pdf'},
      ),
    );

    return filePath;
  }

  Future<List<PaymentDocument>> getPaymentDocuments() async {
    final response = await apiClient.requestWithRetry(
      '/me/payment-documents',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (e) => PaymentDocument.fromJson((e as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<String> downloadPaymentDocument(PaymentDocument document) async {
    final dir = await getApplicationDocumentsDirectory();
    final filename = _safeFilename(
      document.filename ?? 'payment_document_${document.id}.pdf',
    );
    final filePath = '${dir.path}/$filename';
    final file = File(filePath);

    if (await file.exists()) {
      return filePath;
    }

    await apiClient.dio.download(
      '/me/payment-documents/${document.id}/download',
      filePath,
      options: Options(
        responseType: ResponseType.bytes,
        headers: {'Accept': 'application/pdf'},
      ),
    );

    return filePath;
  }

  Future<List<Payroll>> getMyPaySlips() async {
    final response = await apiClient.requestWithRetry(
      '/me/pay-slips',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Payroll.fromJson(e)).toList();
  }

  static String _safeFilename(String value) {
    final sanitized = value.replaceAll(RegExp(r'[^a-zA-Z0-9._-]'), '_');
    return sanitized.endsWith('.pdf') ? sanitized : '$sanitized.pdf';
  }
}
