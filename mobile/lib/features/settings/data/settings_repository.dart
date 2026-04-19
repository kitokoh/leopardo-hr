import 'dart:io';

import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/models/employee.dart';
import 'package:leopardo_rh/features/settings/data/biometric_enrollment.dart';

class SettingsRepository {
  SettingsRepository(this._apiClient, this._preferences);

  final ApiClient _apiClient;
  final AppPreferences _preferences;

  Future<Employee> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
  }) async {
    final response = await _apiClient.dio.patch('/auth/profile', data: {
      'first_name': firstName.trim(),
      'last_name': lastName.trim(),
      'email': email.trim(),
    });

    return Employee.fromJson((response.data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await _apiClient.dio.post('/auth/change-password', data: {
      'current_password': currentPassword,
      'new_password': newPassword,
      'new_password_confirmation': confirmation,
    });
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
      if (requestedFingerprintDeviceId != null && requestedFingerprintDeviceId.trim().isNotEmpty)
        'requested_fingerprint_device_id': requestedFingerprintDeviceId.trim(),
      if (faceImage != null)
        'face_image': await MultipartFile.fromFile(
          faceImage.path,
          filename: faceImage.uri.pathSegments.isNotEmpty ? faceImage.uri.pathSegments.last : 'face.jpg',
        ),
    });

    final response = await _apiClient.dio.post('/auth/biometric-enrollment', data: formData);
    return BiometricEnrollment.fromJson((response.data['data'] as Map).cast<String, dynamic>());
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
