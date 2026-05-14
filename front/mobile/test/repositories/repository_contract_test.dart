import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/features/absences/data/absence_repository.dart';
import 'package:leopardo_rh/features/approvals/data/approval_repository.dart';
import 'package:leopardo_rh/features/contracts/data/contract_repository.dart';
import 'package:leopardo_rh/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_rh/features/expenses/data/expense_repository.dart';
import 'package:leopardo_rh/features/notifications/data/notification_repository.dart';
import 'package:leopardo_rh/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_rh/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_rh/features/training/data/training_repository.dart';

import '../helpers/mobile_test_harness.dart';

class RecordingInterceptor extends Interceptor {
  final requests = <String>[];

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add('${options.method} ${options.path}');
    handler.resolve(
      Response(
        requestOptions: options,
        statusCode: 200,
        data: {'data': <dynamic>[]},
      ),
    );
  }
}

ApiClient recordingClient(RecordingInterceptor recorder) {
  final client = ApiClient(FakeSecureStorage(), FakeAppPreferences());
  client.dio.interceptors.insert(0, recorder);
  return client;
}

void main() {
  test('read repositories call the documented mobile API endpoints', () async {
    final recorder = RecordingInterceptor();
    final client = recordingClient(recorder);

    await AbsenceRepository(client).getMyAbsences();
    await PayrollRepository(client).getMyPayrolls();
    await NotificationRepository(client).getMyNotifications();
    await EvaluationRepository(client).getMyEvaluations();
    await ContractRepository(client).getMyContracts();
    await TrainingRepository(client).getMyEnrollments();
    await ExpenseRepository(client).getMyClaims();
    await ApprovalRepository(client).getPending();
    await OnboardingRepository(client).getChecklist();

    expect(recorder.requests, [
      'GET /absences',
      'GET /payrolls',
      'GET /notifications',
      'GET /evaluations',
      'GET /me/contracts',
      'GET /me/training-enrollments',
      'GET /expense-claims',
      'GET /approvals/pending',
      'GET /onboarding-setup/checklist',
    ]);
  });

  test('write repositories keep mutation routes scoped and explicit', () async {
    final recorder = RecordingInterceptor();
    final client = recordingClient(recorder);

    await NotificationRepository(client).markAllAsRead();
    await NotificationRepository(client).markAsRead(7);
    await ApprovalRepository(client).approve(9, comment: 'ok');
    await ApprovalRepository(client).reject(10, comment: 'missing file');
    await OnboardingRepository(client).completeStep(11);
    await OnboardingRepository(client).skipStep(12);

    expect(recorder.requests, [
      'PUT /notifications/read-all',
      'PUT /notifications/7/read',
      'POST /approvals/9/approve',
      'POST /approvals/10/reject',
      'POST /onboarding-setup/11/complete',
      'POST /onboarding-setup/12/skip',
    ]);
  });
}
