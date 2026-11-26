import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_public/features/journeys/domain/pharmacy_models.dart';

/// Contrat de la verticale Pharmacie (#9945).
///
/// ## Ce que ces tests protègent
///
/// 1. **La garde n'est jamais inventée.** Le provider publie
///    `category: 'on_duty'` ou `null`. `null` ne veut pas dire « fermée » :
///    `onDuty` doit rester `null`, et l'écran n'affiche alors rien.
/// 2. **Les services ne sont pas inventés.** Le contrat public ne les expose
///    pas encore : la liste reste vide.
/// 3. **Panne ≠ annuaire vide.** `available: false` et « aucune officine
///    opt-in » sont deux états distincts, avec deux messages distincts.
void main() {
  group('parsing d’une officine', () {
    test('une officine sans slug ou sans nom est écartée', () {
      expect(Pharmacy.tryParse({'name': 'Pharmacie Atlas'}), isNull);
      expect(Pharmacy.tryParse({'slug': 'atlas'}), isNull);
      expect(Pharmacy.tryParse(null), isNull);
    });

    test('les champs publics sont lus', () {
      final pharmacy = Pharmacy.tryParse({
        'slug': 'pharmacie-atlas',
        'name': 'Pharmacie Atlas',
        'city': 'Casablanca',
        'country': 'MA',
        'description': 'Officine de quartier.',
        'category': 'on_duty',
      });

      expect(pharmacy, isNotNull);
      expect(pharmacy!.slug, 'pharmacie-atlas');
      expect(pharmacy.city, 'Casablanca');
      expect(pharmacy.country, 'MA');
      expect(pharmacy.onDuty, isTrue);
    });

    test('une catégorie absente laisse la garde INCONNUE, pas « fermée »', () {
      final pharmacy = Pharmacy.tryParse({
        'slug': 'pharmacie-atlas',
        'name': 'Pharmacie Atlas',
      });

      // `null` et non `false` : l'absence d'information n'est pas
      // l'information « pas de garde ».
      expect(pharmacy!.onDuty, isNull);
    });

    test('une catégorie inconnue est traitée comme inconnue', () {
      final pharmacy = Pharmacy.tryParse({
        'slug': 'pharmacie-atlas',
        'name': 'Pharmacie Atlas',
        'category': 'some_future_category',
      });

      expect(pharmacy!.onDuty, isNull);
    });

    test('les services restent vides quand le contrat ne les expose pas', () {
      final pharmacy = Pharmacy.tryParse({
        'slug': 'pharmacie-atlas',
        'name': 'Pharmacie Atlas',
      });

      expect(pharmacy!.services, isEmpty);
      expect(pharmacy.hasServices, isFalse);
    });

    test('des services publiés sont lus', () {
      final pharmacy = Pharmacy.tryParse({
        'slug': 'pharmacie-atlas',
        'name': 'Pharmacie Atlas',
        'services': ['Garde de nuit', 'Parapharmacie'],
      });

      expect(pharmacy!.services, ['Garde de nuit', 'Parapharmacie']);
      expect(pharmacy.hasServices, isTrue);
    });
  });

  group('annuaire', () {
    test('une panne API n’est pas un annuaire vide', () {
      const unavailable = PharmacyDirectory.unavailable;

      expect(unavailable.available, isFalse);
      expect(unavailable.isEmpty, isFalse);
    });

    test('un annuaire disponible et vide est un état honnête', () {
      const empty = PharmacyDirectory(available: true, pharmacies: []);

      expect(empty.available, isTrue);
      expect(empty.isEmpty, isTrue);
    });

    test('un annuaire peuplé n’est pas vide', () {
      const directory = PharmacyDirectory(
        available: true,
        pharmacies: [
          Pharmacy(slug: 'atlas', name: 'Pharmacie Atlas'),
        ],
      );

      expect(directory.isEmpty, isFalse);
      expect(directory.pharmacies, hasLength(1));
    });
  });
}
