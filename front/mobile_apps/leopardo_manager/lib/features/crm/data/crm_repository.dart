import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/crm_models.dart';

/// Repository CRM client (tenant) — issue #5730 (CRM-V1-14, mobile terrain).
///
/// Consomme la même API que le web (`/api/v1/crm/*`, contrats #5712) avec
/// les mêmes Policies : les routes sont réservées aux managers
/// principal/rh côté serveur ; l'app `leopardo_employee` n'expose aucune
/// route CRM (pas d'accès par défaut).
///
/// Patterns mobiles obligatoires : `requestWithRetry` + timeouts courts +
/// `extractDataMap`/`extractDataList` (jamais de cast direct de
/// `response.data['data']`).
class CrmRepository {
  final ApiClient apiClient;

  CrmRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 10);
  static const _writeTimeout = Duration(seconds: 15);

  // ── Accounts ──────────────────────────────────────────────────────────────

  Future<List<CrmAccount>> getAccounts() async {
    final response = await apiClient.requestWithRetry(
      '/crm/accounts',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmAccount.fromJson(e))
        .toList();
  }

  Future<CrmAccount> getAccount(int id) async {
    final response = await apiClient.requestWithRetry(
      '/crm/accounts/$id',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return CrmAccount.fromJson(extractDataMap(response.data));
  }

  Future<List<CrmContact>> getAccountContacts(int accountId) async {
    final response = await apiClient.requestWithRetry(
      '/crm/accounts/$accountId/contacts',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmContact.fromJson(e))
        .toList();
  }

  Future<List<CrmActivity>> getAccountActivities(int accountId) async {
    final response = await apiClient.requestWithRetry(
      '/crm/activities?related_type=account&related_id=$accountId',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmActivity.fromJson(e))
        .toList();
  }

  Future<List<CrmTask>> getAccountTasks(int accountId) async {
    final response = await apiClient.requestWithRetry(
      '/crm/tasks?related_type=account&related_id=$accountId',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmTask.fromJson(e))
        .toList();
  }

  // ── Leads & Opportunités ──────────────────────────────────────────────────

  Future<List<CrmLead>> getLeads() async {
    final response = await apiClient.requestWithRetry(
      '/crm/leads',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmLead.fromJson(e))
        .toList();
  }

  Future<List<CrmOpportunity>> getOpportunities() async {
    final response = await apiClient.requestWithRetry(
      '/crm/opportunities',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    return (extractDataList(response.data))
        .map((e) => CrmOpportunity.fromJson(e))
        .toList();
  }

  /// Transition d'étape d'une opportunité (même API/Policies que le web).
  Future<CrmOpportunity> transitionOpportunity(
    int opportunityId,
    String stage,
  ) async {
    final response = await apiClient.requestWithRetry(
      '/crm/opportunities/$opportunityId',
      method: 'PUT',
      data: {'stage': stage},
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
    );
    return CrmOpportunity.fromJson(extractDataMap(response.data));
  }

  // ── Activités & tâches (timeline) ─────────────────────────────────────────

  Future<CrmActivity> createActivity({
    required String subject,
    String activityType = 'note',
    String? relatedType,
    int? relatedId,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/crm/activities',
      method: 'POST',
      data: {
        'subject': subject,
        'activity_type': activityType,
        if (relatedType != null) 'related_type': relatedType,
        if (relatedId != null) 'related_id': relatedId,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
    );
    return CrmActivity.fromJson(extractDataMap(response.data));
  }

  Future<CrmTask> createTask({
    required String subject,
    String? relatedType,
    int? relatedId,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/crm/tasks',
      method: 'POST',
      data: {
        'subject': subject,
        if (relatedType != null) 'related_type': relatedType,
        if (relatedId != null) 'related_id': relatedId,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
    );
    return CrmTask.fromJson(extractDataMap(response.data));
  }

  Future<CrmTask> completeTask(int taskId) async {
    final response = await apiClient.requestWithRetry(
      '/crm/tasks/$taskId',
      method: 'PUT',
      data: {'status': 'done'},
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
    );
    return CrmTask.fromJson(extractDataMap(response.data));
  }
}
