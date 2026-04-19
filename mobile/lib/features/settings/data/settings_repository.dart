import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/models/employee.dart';

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
