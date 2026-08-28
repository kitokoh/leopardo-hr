import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_manager/features/crm/data/crm_repository.dart';

import '../helpers/mobile_test_harness.dart';

/// Issue #5730 — le repository CRM mobile appelle exactement les endpoints
/// `/api/v1/crm/*` documentés (mêmes contrats que le web, #5712) et parse
/// les payloads Laravel (enveloppe `data`).
class CrmRecordingInterceptor extends Interceptor {
  final requests = <String>[];

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add('${options.method} ${options.path}');
    final path = options.path;

    final Map<String, dynamic> data;
    if (path == '/crm/accounts' && options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 1,
            'name': 'Acme Algérie',
            'status': 'active',
            'email': null,
            'phone': null,
            'owner_name': 'Karim Benali',
          },
        ],
      };
    } else if (path == '/crm/accounts/1' && options.method == 'GET') {
      data = {
        'data': {'id': 1, 'name': 'Acme Algérie', 'status': 'active'},
      };
    } else if (path == '/crm/accounts/1/contacts' && options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 10,
            'account_id': 1,
            'first_name': 'Karim',
            'last_name': 'Benali',
            'email': null,
            'phone': null,
            'is_primary': true,
          },
        ],
      };
    } else if (path == '/crm/activities?related_type=account&related_id=1' &&
        options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 100,
            'subject': 'Lead converti',
            'activity_type': 'note',
            'related_type': 'lead',
            'related_id': 5,
            'happened_at': '2026-08-28T10:00:00Z',
          },
        ],
      };
    } else if (path == '/crm/tasks?related_type=account&related_id=1' &&
        options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 200,
            'subject': 'Relancer le client',
            'status': 'todo',
            'priority': 'high',
            'due_at': null,
          },
        ],
      };
    } else if (path == '/crm/leads' && options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 5,
            'first_name': 'Sofia',
            'last_name': 'Merabet',
            'company_name': 'Startup X',
            'email': null,
            'phone': null,
            'source': 'manual',
            'status': 'new',
          },
        ],
      };
    } else if (path == '/crm/opportunities' && options.method == 'GET') {
      data = {
        'data': [
          {
            'id': 7,
            'pipeline_id': 1,
            'name': 'Acme Algérie',
            'stage': 'prospection',
            'status': 'open',
            'amount': 500000,
            'expected_close_date': '2026-10-01',
          },
        ],
      };
    } else if (path == '/crm/opportunities/7' && options.method == 'PUT') {
      data = {
        'data': {
          'id': 7,
          'pipeline_id': 1,
          'name': 'Acme Algérie',
          'stage': 'won',
          'status': 'open',
        },
      };
    } else {
      data = {'data': <dynamic>[]};
    }

    handler.resolve(
      Response(requestOptions: options, statusCode: 200, data: data),
    );
  }
}

void main() {
  ApiClient recordingClient(CrmRecordingInterceptor recorder) {
    final client = ApiClient(FakeSecureStorage(), FakeAppPreferences());
    client.dio.interceptors.insert(0, recorder);
    return client;
  }

  test('crm read repositories call the documented /api/v1/crm endpoints',
      () async {
    final recorder = CrmRecordingInterceptor();
    final repo = CrmRepository(recordingClient(recorder));

    final accounts = await repo.getAccounts();
    expect(accounts, hasLength(1));
    expect(accounts.first.name, 'Acme Algérie');

    final account = await repo.getAccount(1);
    expect(account.id, 1);

    final contacts = await repo.getAccountContacts(1);
    expect(contacts, hasLength(1));
    expect(contacts.first.fullName, 'Karim Benali');
    expect(contacts.first.isPrimary, isTrue);

    final activities = await repo.getAccountActivities(1);
    expect(activities, hasLength(1));
    expect(activities.first.subject, 'Lead converti');

    final tasks = await repo.getAccountTasks(1);
    expect(tasks, hasLength(1));
    expect(tasks.first.subject, 'Relancer le client');

    final leads = await repo.getLeads();
    expect(leads, hasLength(1));
    expect(leads.first.displayName, 'Sofia Merabet');

    final opportunities = await repo.getOpportunities();
    expect(opportunities, hasLength(1));
    expect(opportunities.first.stage, 'prospection');

    expect(recorder.requests, [
      'GET /crm/accounts',
      'GET /crm/accounts/1',
      'GET /crm/accounts/1/contacts',
      'GET /crm/activities?related_type=account&related_id=1',
      'GET /crm/tasks?related_type=account&related_id=1',
      'GET /crm/leads',
      'GET /crm/opportunities',
    ]);
  });

  test('crm write repositories send explicit mutations', () async {
    final recorder = CrmRecordingInterceptor();
    final repo = CrmRepository(recordingClient(recorder));

    final won = await repo.transitionOpportunity(7, 'won');
    expect(won.stage, 'won');

    final activity = await repo.createActivity(
      subject: 'Appel de relance',
      relatedType: 'account',
      relatedId: 1,
    );
    expect(activity.subject, ''); // mock par défaut — le contrat POST est vérifié ci-dessous.

    final task = await repo.createTask(
      subject: 'Préparer la proposition',
      relatedType: 'account',
      relatedId: 1,
    );

    await repo.completeTask(200);

    // Les mutations passent par PUT/POST explicites sur /crm/*.
    expect(
      recorder.requests.where((r) => r != 'GET /crm/accounts/1/contacts'),
      containsAll([
        'PUT /crm/opportunities/7',
        'POST /crm/activities',
        'POST /crm/tasks',
        'PUT /crm/tasks/200',
      ]),
    );
  });
}
