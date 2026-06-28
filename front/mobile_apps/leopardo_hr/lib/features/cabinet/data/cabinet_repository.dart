import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/cabinet_document.dart';
import 'package:leopardo_core/models/cabinet_folder.dart';

class CabinetRepository {
  final ApiClient apiClient;

  CabinetRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);
  static const _uploadTimeout = Duration(seconds: 30);

  Future<List<CabinetFolder>> getFolders({int? parentId}) async {
    final params = <String, dynamic>{};
    if (parentId != null) params['parent_id'] = parentId;
    final response = await apiClient.requestWithRetry(
      '/cabinet/folders',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: params,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((entry) => CabinetFolder.fromJson(entry.cast<String, dynamic>()))
        .toList();
  }

  Future<CabinetFolder> createFolder({
    required String name,
    int? parentId,
    String? color,
    String? icon,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/cabinet/folders',
      method: 'POST',
      data: {
        'name': name,
        if (parentId != null) 'parent_id': parentId,
        if (color != null) 'color': color,
        if (icon != null) 'icon': icon,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return CabinetFolder.fromJson(extractDataMap(response.data));
  }

  Future<CabinetFolder> updateFolder(
    int id, {
    String? name,
    int? parentId,
  }) async {
    final data = <String, dynamic>{};
    if (name != null) data['name'] = name;
    if (parentId != null) data['parent_id'] = parentId;
    final response = await apiClient.requestWithRetry(
      '/cabinet/folders/$id',
      method: 'PUT',
      data: data,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return CabinetFolder.fromJson(extractDataMap(response.data));
  }

  Future<void> deleteFolder(int id) async {
    await apiClient.requestWithRetry(
      '/cabinet/folders/$id',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<List<CabinetDocument>> getDocuments({int? folderId}) async {
    final params = <String, dynamic>{};
    if (folderId != null) params['folder_id'] = folderId;
    final response = await apiClient.requestWithRetry(
      '/cabinet/documents',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: params,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((entry) => CabinetDocument.fromJson(entry.cast<String, dynamic>()))
        .toList();
  }

  Future<CabinetDocument> uploadDocument({
    required String filePath,
    required String fileName,
    int? folderId,
    String? name,
    String? notes,
  }) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: fileName),
      if (folderId != null) 'folder_id': folderId,
      if (name != null) 'name': name,
      if (notes != null) 'notes': notes,
    });
    final response = await apiClient.requestWithRetry(
      '/cabinet/documents',
      method: 'POST',
      data: formData,
      maxRetriesOverride: 0,
      timeoutOverride: _uploadTimeout,
    );
    return CabinetDocument.fromJson(extractDataMap(response.data));
  }

  Future<void> deleteDocument(int id) async {
    await apiClient.requestWithRetry(
      '/cabinet/documents/$id',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> shareViaEmail({
    required String shareableType,
    required int shareableId,
    required String email,
    String? expiresAt,
  }) async {
    await apiClient.requestWithRetry(
      '/cabinet/shares',
      method: 'POST',
      data: {
        'shareable_type': shareableType,
        'shareable_id': shareableId,
        'shared_via': 'email',
        'shared_with_email': email,
        if (expiresAt != null) 'expires_at': expiresAt,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<Map<String, dynamic>> shareViaLink({
    required String shareableType,
    required int shareableId,
    String? expiresAt,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/cabinet/shares',
      method: 'POST',
      data: {
        'shareable_type': shareableType,
        'shareable_id': shareableId,
        'shared_via': 'link',
        if (expiresAt != null) 'expires_at': expiresAt,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return extractDataMap(response.data);
  }

  Future<Map<String, dynamic>> getStats() async {
    final response = await apiClient.requestWithRetry(
      '/cabinet/stats',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return extractDataMap(response.data);
  }
}
