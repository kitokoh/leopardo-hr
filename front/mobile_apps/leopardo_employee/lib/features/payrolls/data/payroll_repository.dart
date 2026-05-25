import 'dart:io';
import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/payroll.dart';
import 'package:path_provider/path_provider.dart';

class PayrollRepository {
  final ApiClient apiClient;

  PayrollRepository(this.apiClient);

  Future<List<Payroll>> getMyPayrolls() async {
    final response = await apiClient.dio.get('/payrolls');
    final items = response.data['data'] as List;
    return items.map((e) => Payroll.fromJson(e)).toList();
  }

  Future<String> downloadPayslipPdf(int payslipId) async {
    final dir = await getApplicationDocumentsDirectory();
    final filePath = '${dir.path}/payslip_$payslipId.pdf';
    final file = File(filePath);

    if (await file.exists()) {
      return filePath;
    }

    await apiClient.dio.download(
      '/pay-slips/$payslipId/pdf',
      filePath,
      options: Options(
        responseType: ResponseType.bytes,
        headers: {'Accept': 'application/pdf'},
      ),
    );

    return filePath;
  }

  Future<List<Payroll>> getMyPaySlips() async {
    final response = await apiClient.dio.get('/me/pay-slips');
    final items = response.data['data'] as List;
    return items.map((e) => Payroll.fromJson(e)).toList();
  }
}
