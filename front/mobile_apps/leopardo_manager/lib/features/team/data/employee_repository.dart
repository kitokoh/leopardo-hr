import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/employee.dart';

/// Repository CRUD pour la gestion des employes (manager/RH).
///
/// Les endpoints pointent vers /api/v1/employees et /api/v1/invitations.
/// Les autorisations sont verifiees cote API (policy EmployeePolicy).
class EmployeeRepository {
  final ApiClient apiClient;

  EmployeeRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 12);

  Future<List<Employee>> list({int page = 1, int perPage = 50}) async {
    final response = await apiClient.requestWithRetry(
      '/employees',
      queryParameters: {'page': page, 'per_page': perPage},
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = response.data['data'] as List;
    return items
        .map((e) => Employee.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Employee> show(int employeeId) async {
    final response = await apiClient.requestWithRetry(
      '/employees/$employeeId',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    return Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<Employee> create({
    required String firstName,
    required String lastName,
    required String email,
    String? phone,
    String? personalEmail,
    String role = 'employee',
    String? managerRole,
    String? password,
    String? matricule,
    String? contractStart,
    int? scheduleId,
    String? salaryType,
    double? salaryBase,
    double? hourlyRate,
    String? department,
    String? jobTitle,
    String? workLocation,
    bool sendInvitation = true,
  }) async {
    final data = <String, dynamic>{
      'first_name': firstName.trim(),
      'last_name': lastName.trim(),
      'email': email.trim(),
      'role': role,
      'send_invitation': sendInvitation,
    };
    if (phone != null && phone.trim().isNotEmpty) data['phone'] = phone.trim();
    if (personalEmail != null && personalEmail.trim().isNotEmpty) {
      data['personal_email'] = personalEmail.trim();
    }
    if (managerRole != null && managerRole.isNotEmpty) {
      data['manager_role'] = managerRole;
    }
    if (password != null && password.isNotEmpty) {
      data['password'] = password;
    }
    if (matricule != null && matricule.trim().isNotEmpty) {
      data['matricule'] = matricule.trim();
    }
    if (contractStart != null && contractStart.trim().isNotEmpty) {
      data['contract_start'] = contractStart.trim();
    }
    if (scheduleId != null) data['schedule_id'] = scheduleId;
    if (salaryType != null && salaryType.trim().isNotEmpty) {
      data['salary_type'] = salaryType.trim();
    }
    if (salaryBase != null) data['salary_base'] = salaryBase;
    if (hourlyRate != null) data['hourly_rate'] = hourlyRate;

    final extraData = <String, dynamic>{};
    if (department != null && department.trim().isNotEmpty) {
      extraData['department'] = department.trim();
    }
    if (jobTitle != null && jobTitle.trim().isNotEmpty) {
      extraData['job_title'] = jobTitle.trim();
    }
    if (workLocation != null && workLocation.trim().isNotEmpty) {
      extraData['work_location'] = workLocation.trim();
    }
    if (extraData.isNotEmpty) data['extra_data'] = extraData;

    final response = await apiClient.requestWithRetry(
      '/employees',
      method: 'POST',
      data: data,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<CompanyQrPayload> getCompanyQrPayload() async {
    final response = await apiClient.requestWithRetry(
      '/company/qr-onboarding',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    return CompanyQrPayload.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<EmployeeQrPrefill> scanEmployeeQr(String token) async {
    final response = await apiClient.requestWithRetry(
      '/company/qr-onboarding/scan-employee',
      method: 'POST',
      data: {'qr_token': token.trim()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    final data = (response.data['data'] as Map).cast<String, dynamic>();
    return EmployeeQrPrefill.fromJson(
      (data['prefill'] as Map).cast<String, dynamic>(),
      token.trim(),
    );
  }

  Future<Employee> createFromQr({
    required String qrToken,
    required String email,
    String? matricule,
    String? contractStart,
    int? scheduleId,
    String? salaryType,
    double? salaryBase,
    double? hourlyRate,
    String? department,
    String? jobTitle,
    String? workLocation,
    bool sendInvitation = true,
  }) async {
    final data = <String, dynamic>{
      'qr_token': qrToken.trim(),
      'email': email.trim(),
      'send_invitation': sendInvitation,
    };
    if (matricule != null && matricule.trim().isNotEmpty) {
      data['matricule'] = matricule.trim();
    }
    if (contractStart != null && contractStart.trim().isNotEmpty) {
      data['contract_start'] = contractStart.trim();
    }
    if (scheduleId != null) data['schedule_id'] = scheduleId;
    if (salaryType != null && salaryType.trim().isNotEmpty) {
      data['salary_type'] = salaryType.trim();
    }
    if (salaryBase != null) data['salary_base'] = salaryBase;
    if (hourlyRate != null) data['hourly_rate'] = hourlyRate;

    final extraData = <String, dynamic>{};
    if (department != null && department.trim().isNotEmpty) {
      extraData['department'] = department.trim();
    }
    if (jobTitle != null && jobTitle.trim().isNotEmpty) {
      extraData['job_title'] = jobTitle.trim();
    }
    if (workLocation != null && workLocation.trim().isNotEmpty) {
      extraData['work_location'] = workLocation.trim();
    }
    if (extraData.isNotEmpty) data['extra_data'] = extraData;

    final response = await apiClient.requestWithRetry(
      '/company/qr-onboarding/create-employee',
      method: 'POST',
      data: data,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<Employee> update(int employeeId, Map<String, dynamic> patch) async {
    final response = await apiClient.requestWithRetry(
      '/employees/$employeeId',
      method: 'PATCH',
      data: patch,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> archive(int employeeId, {String? reason}) async {
    await apiClient.requestWithRetry(
      '/employees/$employeeId/archive',
      method: 'POST',
      data: {
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<List<Invitation>> listInvitations() async {
    final response = await apiClient.requestWithRetry(
      '/invitations',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = response.data['data'] as List;
    return items
        .map((e) => Invitation.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> resendInvitation(String invitationId) async {
    await apiClient.requestWithRetry(
      '/invitations/$invitationId/resend',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}

class EmployeeQrPrefill {
  final String token;
  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String? personalEmail;

  EmployeeQrPrefill({
    required this.token,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    this.personalEmail,
  });

  factory EmployeeQrPrefill.fromJson(Map<String, dynamic> json, String token) {
    return EmployeeQrPrefill(
      token: token,
      firstName: (json['first_name'] ?? '').toString(),
      lastName: (json['last_name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      phone: json['phone']?.toString(),
      personalEmail: json['personal_email']?.toString(),
    );
  }
}

class CompanyQrPayload {
  final String token;
  final String companyName;
  final String? expiresAt;

  CompanyQrPayload({
    required this.token,
    required this.companyName,
    this.expiresAt,
  });

  factory CompanyQrPayload.fromJson(Map<String, dynamic> json) {
    final company = (json['company'] as Map?)?.cast<String, dynamic>() ?? {};
    return CompanyQrPayload(
      token: (json['token'] ?? '').toString(),
      companyName: (company['name'] ?? 'Entreprise').toString(),
      expiresAt: json['expires_at']?.toString(),
    );
  }
}

class Invitation {
  final String id;
  final String email;
  final String status;
  final DateTime? expiresAt;
  final DateTime? sentAt;
  final int? employeeId;

  Invitation({
    required this.id,
    required this.email,
    required this.status,
    this.expiresAt,
    this.sentAt,
    this.employeeId,
  });

  factory Invitation.fromJson(Map<String, dynamic> json) {
    return Invitation(
      id: json['id'].toString(),
      email: (json['email'] ?? '') as String,
      status: (json['status'] ?? 'pending') as String,
      expiresAt:
          json['expires_at'] != null
              ? DateTime.tryParse(json['expires_at'].toString())
              : null,
      sentAt:
          json['last_sent_at'] != null
              ? DateTime.tryParse(json['last_sent_at'].toString())
              : (json['sent_at'] != null
                  ? DateTime.tryParse(json['sent_at'].toString())
                  : null),
      employeeId:
          json['employee_id'] is num
              ? (json['employee_id'] as num).toInt()
              : null,
    );
  }
}
