import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/models/crm_models.dart';
import 'package:leopardo_manager/features/crm/data/crm_repository.dart';

/// Providers CRM (issue #5730) — manager uniquement ; l'app employee ne
/// déclare aucune route/provider CRM (pas d'accès par défaut).

final crmRepositoryProvider = Provider<CrmRepository>((ref) {
  return CrmRepository(ref.watch(apiClientProvider));
});

final crmAccountsProvider = FutureProvider<List<CrmAccount>>((ref) async {
  return ref.watch(crmRepositoryProvider).getAccounts();
});

final crmLeadsProvider = FutureProvider<List<CrmLead>>((ref) async {
  return ref.watch(crmRepositoryProvider).getLeads();
});

final crmOpportunitiesProvider = FutureProvider<List<CrmOpportunity>>((ref) async {
  return ref.watch(crmRepositoryProvider).getOpportunities();
});

/// Détail d'un compte + contacts + activités + tâches (timeline).
final crmAccountDetailProvider =
    FutureProvider.family<({CrmAccount account, List<CrmContact> contacts, List<CrmActivity> activities, List<CrmTask> tasks}), int>(
  (ref, accountId) async {
    final repo = ref.watch(crmRepositoryProvider);
    final results = await Future.wait([
      repo.getAccount(accountId),
      repo.getAccountContacts(accountId),
      repo.getAccountActivities(accountId),
      repo.getAccountTasks(accountId),
    ]);
    return (
      account: results[0] as CrmAccount,
      contacts: results[1] as List<CrmContact>,
      activities: results[2] as List<CrmActivity>,
      tasks: results[3] as List<CrmTask>,
    );
  },
);
