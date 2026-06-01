import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_platform_admin/src/features/platform/platform_models.dart';

void main() {
  test('maps platform company from provisioning response', () {
    final company = PlatformCompany.fromProvisioningResponse({
      'data': {
        'company': {
          'id': '9b2f5b8e-tenant',
          'name': 'Client Terrain',
          'status': 'active',
          'country': 'DZ',
          'plan_id': 7,
          'plan_name': 'Business',
          'created_at': '2026-06-01T07:00:00Z',
        },
        'manager': {'email': 'manager@client.test'},
      },
    });

    expect(company.id, '9b2f5b8e-tenant');
    expect(company.name, 'Client Terrain');
    expect(company.status, 'active');
    expect(company.country, 'DZ');
    expect(company.plan, 'Business');
    expect(company.createdAt, '2026-06-01T07:00:00Z');
  });

  test('keeps uuid ids as strings for company detail routing', () {
    final company = PlatformCompany.fromJson({
      'id': 'company-uuid-123',
      'name': 'Leopardo Client',
      'country': 'TR',
      'plan': {'name': 'Scale'},
    });

    expect(company.id, 'company-uuid-123');
    expect(company.plan, 'Scale');
  });
}
