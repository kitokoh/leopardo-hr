import 'dart:io';

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_employee/features/settings/data/biometric_enrollment.dart';

class SettingsRepository {
  SettingsRepository(this._apiClient, this._preferences);

  final ApiClient _apiClient;
  final AppPreferences _preferences;

  Future<Employee> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
    String? personalEmail,
    String? recoveryEmail,
    String? personalPhone,
  }) async {
    final response = await _apiClient.dio.patch(
      '/auth/profile',
      data: {
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'email': email.trim(),
        'personal_email': personalEmail?.trim(),
        'recovery_email': recoveryEmail?.trim(),
        'personal_phone': personalPhone?.trim(),
      },
    );

    return Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<EmployeeCareer> loadCareer() async {
    final response = await _apiClient.dio.get('/me/career');
    return EmployeeCareer.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<CabinetStats> loadCabinetStats() async {
    final response = await _apiClient.dio.get('/cabinet/stats');
    return CabinetStats.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await _apiClient.dio.post(
      '/auth/change-password',
      data: {
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': confirmation,
      },
    );
  }

  Future<LocalBiometricSettings> loadLocalBiometricSettings() async {
    return LocalBiometricSettings(
      biometricEnabled: _preferences.biometricEnabled,
      fingerprintEnabled: _preferences.fingerprintEnabled,
      faceEnabled: _preferences.faceEnabled,
      attendanceConsent: _preferences.attendanceConsent,
      biometricNote: _preferences.biometricNote,
    );
  }

  Future<void> saveLocalBiometricSettings(LocalBiometricSettings settings) {
    return _preferences.saveBiometricSettings(
      biometricEnabled: settings.biometricEnabled,
      fingerprintEnabled: settings.fingerprintEnabled,
      faceEnabled: settings.faceEnabled,
      attendanceConsent: settings.attendanceConsent,
      biometricNote: settings.biometricNote,
    );
  }

  Future<BiometricEnrollment?> loadBiometricEnrollment() async {
    final response = await _apiClient.dio.get('/auth/biometric-enrollment');
    final data = response.data['data'];
    if (data == null) {
      return null;
    }

    return BiometricEnrollment.fromJson((data as Map).cast<String, dynamic>());
  }

  Future<BiometricEnrollment> submitBiometricEnrollment({
    required bool requestedFaceEnabled,
    required bool requestedFingerprintEnabled,
    required String employeeNote,
    String? requestedFingerprintDeviceId,
    File? faceImage,
  }) async {
    final formData = FormData.fromMap({
      'requested_face_enabled': requestedFaceEnabled ? '1' : '0',
      'requested_fingerprint_enabled': requestedFingerprintEnabled ? '1' : '0',
      'employee_note': employeeNote.trim(),
      if (requestedFingerprintDeviceId != null &&
          requestedFingerprintDeviceId.trim().isNotEmpty)
        'requested_fingerprint_device_id': requestedFingerprintDeviceId.trim(),
      if (faceImage != null)
        'face_image': await MultipartFile.fromFile(
          faceImage.path,
          filename:
              faceImage.uri.pathSegments.isNotEmpty
                  ? faceImage.uri.pathSegments.last
                  : 'face.jpg',
        ),
    });

    final response = await _apiClient.dio.post(
      '/auth/biometric-enrollment',
      data: formData,
    );
    return BiometricEnrollment.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }
}

class LocalBiometricSettings {
  const LocalBiometricSettings({
    required this.biometricEnabled,
    required this.fingerprintEnabled,
    required this.faceEnabled,
    required this.attendanceConsent,
    required this.biometricNote,
  });

  final bool biometricEnabled;
  final bool fingerprintEnabled;
  final bool faceEnabled;
  final bool attendanceConsent;
  final String biometricNote;
}

class EmployeeCareer {
  const EmployeeCareer({
    required this.availableForNewCompany,
    required this.currentCompanyName,
    required this.timeline,
  });

  final bool availableForNewCompany;
  final String? currentCompanyName;
  final List<EmployeeCareerEntry> timeline;

  factory EmployeeCareer.fromJson(Map<String, dynamic> json) {
    final rawTimeline = json['timeline'];
    return EmployeeCareer(
      availableForNewCompany: json['available_for_new_company'] == true,
      currentCompanyName: json['current_company_name']?.toString(),
      timeline:
          rawTimeline is List
              ? rawTimeline
                  .whereType<Map>()
                  .map(
                    (entry) => EmployeeCareerEntry.fromJson(
                      entry.cast<String, dynamic>(),
                    ),
                  )
                  .toList()
              : const <EmployeeCareerEntry>[],
    );
  }
}

class EmployeeCareerEntry {
  const EmployeeCareerEntry({
    required this.companyName,
    required this.startDate,
    required this.endDate,
    required this.jobTitle,
    required this.status,
    required this.current,
  });

  final String? companyName;
  final String? startDate;
  final String? endDate;
  final String? jobTitle;
  final String? status;
  final bool current;

  factory EmployeeCareerEntry.fromJson(Map<String, dynamic> json) {
    return EmployeeCareerEntry(
      companyName: json['company_name']?.toString(),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      jobTitle: json['job_title']?.toString(),
      status: json['status']?.toString(),
      current: json['current'] == true,
    );
  }
}

class CabinetStats {
  const CabinetStats({
    required this.folders,
    required this.documents,
    required this.shared,
    required this.publicDocuments,
  });

  final int folders;
  final int documents;
  final int shared;
  final int publicDocuments;

  factory CabinetStats.fromJson(Map<String, dynamic> json) {
    return CabinetStats(
      folders: _parseInt(
        json['folders_count'] ?? json['total_folders'] ?? json['folders'],
      ),
      documents: _parseInt(
        json['documents_count'] ?? json['total_documents'] ?? json['documents'],
      ),
      shared: _parseInt(json['shared_count'] ?? json['shared']),
      publicDocuments: _parseInt(json['public_count'] ?? json['public']),
    );
  }

  static int _parseInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }
}
