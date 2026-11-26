import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/data/careers_gateway.dart';
import 'package:leopardo_public/features/journeys/domain/careers_models.dart';

/// Contrat de la verticale Emploi (#9944).
///
/// ## Ce que ces tests protègent
///
/// 1. **Le consentement RGPD.** `PublicApplyRequest` exige `consent:
///    accepted` ; sans lui le serveur répond 422. Envoyer les données
///    personnelles d'un candidat pour rien est le pire des deux mondes : on
///    refuse localement, avec un message.
/// 2. **La distinction interne / agrégée.** Une offre `origin: external` se
///    postule chez le partenaire. La confondre avec une offre interne
///    enverrait le candidat vers un formulaire qui répondra 404.
void main() {
  group('annuaire des entreprises', () {
    test('une entrée sans slug ou sans nom est écartée', () {
      expect(CareerCompany.tryParse({'name': 'Atlas'}), isNull);
      expect(CareerCompany.tryParse({'slug': 'atlas'}), isNull);
      expect(CareerCompany.tryParse(null), isNull);
    });

    test('slug, nom et pays sont lus', () {
      final company = CareerCompany.tryParse({
        'slug': 'atlas',
        'name': 'Atlas Industries',
        'country': 'MA',
        'last_published_at': '2026-11-20T10:00:00Z',
      });

      expect(company!.slug, 'atlas');
      expect(company.name, 'Atlas Industries');
      expect(company.country, 'MA');
    });
  });

  group('offres', () {
    test('une offre interne est candidatable sur le portail', () {
      final job = CareerJob.tryParse({
        'id': 12,
        'title': 'Développeur Flutter',
        'location': 'Casablanca',
        'contract_type': 'CDI',
        'salary_range_min': 12000,
        'salary_range_max': 18000,
        'currency': 'MAD',
        'skills_required': ['Dart', 'Flutter'],
        'department': {'name': 'Ingénierie'},
      });

      expect(job, isNotNull);
      expect(job!.isExternal, isFalse);
      expect(job.departmentName, 'Ingénierie');
      expect(job.skillsRequired, ['Dart', 'Flutter']);
      expect(job.salaryLabel, '12000 – 18000 MAD');
    });

    test('une offre agrégée porte son origine et son URL de candidature', () {
      final job = CareerJob.tryParse({
        'id': 99,
        'title': 'Data Analyst',
        'origin': 'external',
        'apply_url': 'https://partner.example/jobs/99',
        'company_name': 'Partner Corp',
        'salary_min': 9000,
        'salary_max': 11000,
        'currency': 'MAD',
        'source': {'provider': 'indeed', 'label': 'Indeed'},
      });

      expect(job!.isExternal, isTrue);
      expect(job.applyUrl, 'https://partner.example/jobs/99');
      expect(job.companyName, 'Partner Corp');
      expect(job.sourceLabel, 'Indeed');
      // Les offres agrégées nomment leurs salaires `salary_min/max`.
      expect(job.salaryLabel, '9000 – 11000 MAD');
    });

    test('une offre sans id ou sans titre est écartée', () {
      expect(CareerJob.tryParse({'title': 'Sans id'}), isNull);
      expect(CareerJob.tryParse({'id': 1}), isNull);
    });

    test('une offre clôturée hier n’est plus ouverte', () {
      final yesterday = DateTime.now().subtract(const Duration(days: 1));
      final job = CareerJob.tryParse({
        'id': 1,
        'title': 'Stage',
        'closes_at':
            '${yesterday.year.toString().padLeft(4, '0')}-${yesterday.month.toString().padLeft(2, '0')}-${yesterday.day.toString().padLeft(2, '0')}',
      });

      expect(job!.isOpen, isFalse);
    });

    test('une offre sans date de clôture reste ouverte', () {
      final job = CareerJob.tryParse({'id': 1, 'title': 'CDI'});
      expect(job!.isOpen, isTrue);
    });
  });

  group('portail', () {
    test('les deux familles d’offres sont séparées', () {
      final portal = CareerPortal.tryParse({
        'data': [
          {'id': 1, 'title': 'Interne', 'origin': 'company'},
          {'id': 2, 'title': 'Externe', 'origin': 'external'},
        ],
        'meta': {
          'company': {'slug': 'atlas', 'name': 'Atlas'},
          'counts': {'company': 1, 'external': 1, 'total': 2},
        },
      }, fallbackSlug: 'atlas');

      expect(portal, isNotNull);
      expect(portal!.internalJobs, hasLength(1));
      expect(portal.externalJobs, hasLength(1));
      expect(portal.company.name, 'Atlas');
      expect(portal.companyCount, 1);
    });

    test('sans meta.company, le slug sert de nom', () {
      final portal = CareerPortal.tryParse({
        'data': <Object?>[],
      }, fallbackSlug: 'atlas');
      expect(portal!.company.name, 'atlas');
    });
  });

  group('corps de candidature', () {
    CareerApplicationDraft draft({
      String firstName = 'Amina',
      String lastName = 'Benali',
      String email = 'amina@example.com',
      String? phone,
      String? coverLetter,
      String? resumeUrl,
      bool consent = true,
    }) =>
        CareerApplicationDraft(
          firstName: firstName,
          lastName: lastName,
          email: email,
          phone: phone,
          coverLetter: coverLetter,
          resumeUrl: resumeUrl,
          consent: consent,
        );

    test('le corps porte les clés exactes de PublicApplyRequest', () {
      final payload = CareersGateway.buildApplicationPayload(
        draft(phone: '0600000000', coverLetter: 'Bonjour,'),
      );

      expect(payload['first_name'], 'Amina');
      expect(payload['last_name'], 'Benali');
      expect(payload['email'], 'amina@example.com');
      expect(payload['phone'], '0600000000');
      expect(payload['cover_letter'], 'Bonjour,');
      expect(payload['consent'], isTrue);
      // Le champ piège anti-bot n'est JAMAIS envoyé par un humain.
      expect(payload.containsKey('website'), isFalse);
    });

    test('sans consentement, la candidature est refusée localement', () {
      expect(
        () => CareersGateway.buildApplicationPayload(draft(consent: false)),
        throwsArgumentError,
      );
    });

    test('les champs facultatifs vides sont omis', () {
      final payload = CareersGateway.buildApplicationPayload(
        draft(phone: '', coverLetter: '   ', resumeUrl: ''),
      );

      expect(payload.containsKey('phone'), isFalse);
      expect(payload.containsKey('cover_letter'), isFalse);
      expect(payload.containsKey('resume_url'), isFalse);
    });

    test('un prénom ou un nom vide est refusé', () {
      expect(
        () => CareersGateway.buildApplicationPayload(draft(firstName: '  ')),
        throwsArgumentError,
      );
      expect(
        () => CareersGateway.buildApplicationPayload(draft(lastName: '')),
        throwsArgumentError,
      );
    });

    test('un e-mail invalide est refusé', () {
      expect(
        () => CareersGateway.buildApplicationPayload(draft(email: 'amina')),
        throwsArgumentError,
      );
    });

    test('une lettre de plus de 5000 caractères est refusée', () {
      expect(
        () => CareersGateway.buildApplicationPayload(
          draft(coverLetter: 'a' * 5001),
        ),
        throwsArgumentError,
      );
    });
  });

  group('suivi', () {
    test('les candidatures et la durée de conservation sont lues', () {
      final result = CareerTrackingResult.tryParse({
        'data': [
          {
            'reference': 'APP-1',
            'job_title': 'Développeur Flutter',
            'job_location': 'Casablanca',
            'status': 'screening',
            'applied_at': '2026-11-01T09:00:00Z',
          },
        ],
        'meta': {'count': 1, 'retention_days': 730},
      });

      expect(result.applications, hasLength(1));
      expect(result.applications.first.reference, 'APP-1');
      expect(result.applications.first.status, 'screening');
      expect(result.retentionDays, 730);
    });

    test('une réponse vide rend une liste vide, pas une erreur', () {
      final result = CareerTrackingResult.tryParse({'data': <Object?>[]});
      expect(result.applications, isEmpty);
    });
  });

  group('accusé de réception', () {
    test('la ressource est lue sous data', () {
      final receipt = CareerApplicationReceipt.tryParse({
        'data': {
          'id': 55,
          'status': 'new',
          'full_name': 'Amina Benali',
          'job_posting': {'id': 12, 'title': 'Développeur Flutter'},
          'applied_at': '2026-11-26T10:00:00Z',
        },
      });

      expect(receipt!.id, 55);
      expect(receipt.status, 'new');
      expect(receipt.jobTitle, 'Développeur Flutter');
    });
  });
}
