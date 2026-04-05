import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  return ApiClient(storage);
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthRepository(apiClient, storage);
});

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AttendanceRepository(apiClient);
});
