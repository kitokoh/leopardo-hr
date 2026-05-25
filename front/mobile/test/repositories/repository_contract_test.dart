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
import 'package:leopardo_rh/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_rh/features/team/data/employee_repository.dart';
import 'package:leopardo_rh/features/training/data/training_repository.dart';

import '../helpers/mobile_test_harness.dart';

class RecordingInterceptor extends Interceptor {
  final requests = <String>[];
  final options = <RequestOptions>[];

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    this.options.add(options);
    requests.add('${options.method} ${options.path}');
    final responseData =
        options.method == 'POST' && options.path == '/employees'
            ? {
              'data': {
                'id': 44,
                'first_name': 'Amina',
                'last_name': 'Benali',
                'email': 'amina@example.test',
                'status': 'active',
              },
            }
            : options.method == 'POST' && options.path == '/salary-advances'
            ? {
              'data': {
                'id': 12,
                'employee_id': 44,
                'status': 'pending',
                'amount': 30000,
                'reason': 'Besoin familial',
                'repayment_months': 3,
              },
            }
            : options.method == 'DELETE' &&
                options.path == '/salary-advances/12'
            ? {
              'data': {
                'id': 12,
                'employee_id': 44,
                'status': 'cancelled',
                'amount': 30000,
              },
            }
            : options.method == 'DELETE' && options.path == '/absences/33'
            ? {
              'data': {
                'id': 33,
                'employee_id': 44,
                'absence_type_id': 2,
                'start_date': '2026-05-26',
                'end_date': '2026-05-26',
                'days_count': 1,
                'status': 'cancelled',
              },
            }
            : {'data': <dynamic>[]};
    handler.resolve(
      Response(requestOptions: options, statusCode: 200, data: responseData),
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

  test('employee creation sends HR contract and salary fields', () async {
    final recorder = RecordingInterceptor();
    final client = recordingClient(recorder);

    await EmployeeRepository(client).create(
      firstName: 'Amina',
      lastName: 'Benali',
      email: 'amina@example.test',
      phone: '+213555000111',
      matricule: 'EMP-2026-01',
      contractStart: '2026-05-25',
      salaryType: 'fixed',
      salaryBase: 85000,
      department: 'Operations',
      jobTitle: 'Responsable RH',
      workLocation: 'Alger',
      sendInvitation: true,
    );

    final request = recorder.options.single;
    final data = (request.data as Map).cast<String, dynamic>();
    final extra = (data['extra_data'] as Map).cast<String, dynamic>();

    expect(recorder.requests, ['POST /employees']);
    expect(data['contract_start'], '2026-05-25');
    expect(data['salary_type'], 'fixed');
    expect(data['salary_base'], 85000);
    expect(data['matricule'], 'EMP-2026-01');
    expect(extra['department'], 'Operations');
    expect(extra['job_title'], 'Responsable RH');
    expect(extra['work_location'], 'Alger');
  });

  test('salary advance request posts repayment plan fields', () async {
    final recorder = RecordingInterceptor();
    final client = recordingClient(recorder);

    await SalaryAdvanceRepository(client).requestAdvance(
      amount: 30000,
      repaymentMonths: 3,
      reason: 'Besoin familial',
    );

    final data = (recorder.options.single.data as Map).cast<String, dynamic>();
    expect(recorder.requests, ['POST /salary-advances']);
    expect(data['amount'], 30000);
    expect(data['repayment_months'], 3);
    expect(data['reason'], 'Besoin familial');
  });

  test('employee self-service cancellation routes stay explicit', () async {
    final recorder = RecordingInterceptor();
    final client = recordingClient(recorder);

    await AbsenceRepository(client).cancelAbsence(33);
    await SalaryAdvanceRepository(client).cancelAdvance(12);

    expect(recorder.requests, [
      'DELETE /absences/33',
      'DELETE /salary-advances/12',
    ]);
  });
}
