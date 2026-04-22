import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/employee.dart';

/// Repository CRUD pour la gestion des employes (manager/RH).
///
/// Les endpoints pointent vers /api/v1/employees et /api/v1/invitations.
/// Les autorisations sont verifiees cote API (policy EmployeePolicy).
class EmployeeRepository {
  final ApiClient apiClient;

  EmployeeRepository(this.apiClient);

  Future<List<Employee>> list({int page = 1, int perPage = 50}) async {
    final response = await apiClient.dio.get('/employees', queryParameters: {
      'page': page,
      'per_page': perPage,
    });
    final items = response.data['data'] as List;
    return items
        .map((e) => Employee.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Employee> show(int employeeId) async {
    final response = await apiClient.dio.get('/employees/$employeeId');
    return Employee.fromJson((response.data['data'] as Map).cast<String, dynamic>());
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

    final response = await apiClient.dio.post('/employees', data: data);
    return Employee.fromJson((response.data['data'] as Map).cast<String, dynamic>());
  }

  Future<Employee> update(int employeeId, Map<String, dynamic> patch) async {
    final response = await apiClient.dio.patch('/employees/$employeeId', data: patch);
    return Employee.fromJson((response.data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> archive(int employeeId, {String? reason}) async {
    await apiClient.dio.post('/employees/$employeeId/archive', data: {
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    });
  }

  Future<List<Invitation>> listInvitations() async {
    final response = await apiClient.dio.get('/invitations');
    final items = response.data['data'] as List;
    return items
        .map((e) => Invitation.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> resendInvitation(String invitationId) async {
    await apiClient.dio.post('/invitations/$invitationId/resend');
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
      expiresAt: json['expires_at'] != null
          ? DateTime.tryParse(json['expires_at'].toString())
          : null,
      sentAt: json['last_sent_at'] != null
          ? DateTime.tryParse(json['last_sent_at'].toString())
          : (json['sent_at'] != null
              ? DateTime.tryParse(json['sent_at'].toString())
              : null),
      employeeId: json['employee_id'] is num
          ? (json['employee_id'] as num).toInt()
          : null,
    );
  }
}
