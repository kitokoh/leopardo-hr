# Modèles de Synchronisation Mobile-API

Ce document décrit les nouvelles classes Dart créées pour la synchronisation automatique entre l'API Laravel et l'application mobile Flutter.

## Vue d'ensemble

Les classes suivantes ont été créées pour supporter la synchronisation automatique des fonctionnalités :

- **Feature** : Représente une fonctionnalité API avec ses métadonnées
- **FeatureManifest** : Contient la liste complète des fonctionnalités disponibles
- **FormSchema** : Définit la structure d'un formulaire dynamique
- **ListSchema** : Définit la structure d'une liste dynamique
- **DetailSchema** : Définit la structure d'un écran de détail dynamique

## Classes principales

### Feature

Représente une fonctionnalité API individuelle avec toutes ses métadonnées.

```dart
final feature = Feature(
  key: 'employee_management',
  title: 'Gestion des Employés',
  description: 'Créer, modifier et gérer les employés',
  endpoint: '/api/v1/employees',
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  parameters: {...},
  responseSchema: {...},
  permissions: ['employees.view', 'employees.create'],
  minimumMobileVersion: '1.0.0',
  type: FeatureType.list,
  formSchema: formSchema,
  listSchema: listSchema,
  detailSchema: detailSchema,
);
```

**Méthodes utiles :**
- `isCompatibleWith(String version)` : Vérifie la compatibilité avec une version mobile
- `hasRequiredPermissions(List<String> permissions)` : Vérifie les permissions utilisateur
- `toJson()` / `fromJson()` : Sérialisation JSON automatique

### FeatureManifest

Contient la liste complète des fonctionnalités avec métadonnées de version et signature.

```dart
final manifest = FeatureManifest(
  version: '1.2.0',
  generatedAt: DateTime.now(),
  mobileVersionMin: '1.0.0',
  signature: 'sha256:abc123...',
  features: [feature1, feature2, ...],
);
```

**Méthodes utiles :**
- `getCompatibleFeatures(String version)` : Filtre par compatibilité
- `getAuthorizedFeatures(List<String> permissions)` : Filtre par permissions
- `getAvailableFeatures(String version, List<String> permissions)` : Filtre combiné
- `compareWith(FeatureManifest other)` : Compare deux manifestes
- `validateSignature(String publicKey)` : Valide la signature cryptographique

### FormSchema

Définit la structure d'un formulaire avec validation automatique.

```dart
final formSchema = FormSchema(
  fields: [
    FormField(
      name: 'email',
      type: FormFieldType.email,
      label: 'Email',
      required: true,
      validation: {'pattern': r'^[^@]+@[^@]+\.[^@]+$'},
    ),
    // Autres champs...
  ],
  submitEndpoint: '/api/v1/employees',
  submitMethod: 'POST',
);
```

**Types de champs supportés :**
- `text`, `email`, `password`, `number`, `phone`, `url`
- `textarea`, `select`, `multiselect`, `checkbox`, `radio`
- `date`, `datetime`, `time`, `file`, `image`, `hidden`

**Méthodes utiles :**
- `validate(Map<String, dynamic> data)` : Valide les données du formulaire
- `getField(String name)` : Obtient un champ par nom
- `requiredFields` : Liste des champs requis

### ListSchema

Définit la structure d'une liste avec colonnes, pagination, tri et filtres.

```dart
final listSchema = ListSchema(
  columns: [
    ListColumn(
      name: 'name',
      label: 'Nom',
      type: ListColumnType.text,
      sortable: true,
    ),
    ListColumn(
      name: 'created_at',
      label: 'Date',
      type: ListColumnType.date,
      sortable: true,
    ),
  ],
  pagination: ListPagination(pageSize: 20),
  sorting: ListSorting(defaultColumn: 'name'),
  filtering: ListFiltering(filters: [...]),
  actions: [
    ListAction(
      name: 'edit',
      label: 'Modifier',
      type: ListActionType.edit,
      condition: 'status == "active"',
    ),
  ],
);
```

**Types de colonnes supportés :**
- `text`, `number`, `date`, `datetime`, `boolean`
- `currency`, `percentage`, `badge`, `image`

**Méthodes utiles :**
- `getColumn(String name)` : Obtient une colonne par nom
- `visibleColumns` : Liste des colonnes visibles
- `getActionsForItem(Map<String, dynamic> item)` : Actions pour un élément

### DetailSchema

Définit la structure d'un écran de détail avec sections et actions.

```dart
final detailSchema = DetailSchema(
  title: 'Détails de l\'employé',
  layout: DetailLayout.vertical,
  sections: [
    DetailSection(
      name: 'personal_info',
      title: 'Informations personnelles',
      fields: [
        DetailField(
          name: 'email',
          label: 'Email',
          type: DetailFieldType.email,
          size: DetailFieldSize.full,
        ),
      ],
    ),
  ],
  actions: [
    DetailAction(
      name: 'edit',
      label: 'Modifier',
      type: DetailActionType.edit,
    ),
  ],
);
```

**Layouts supportés :**
- `vertical`, `horizontal`, `grid`, `tabs`

**Types de champs supportés :**
- Tous les types de FormField plus `list` et `json`

## Sérialisation JSON

Toutes les classes supportent la sérialisation JSON automatique via `json_annotation` :

```dart
// Sérialisation
final json = feature.toJson();

// Désérialisation
final feature = Feature.fromJson(json);
```

## Validation et Formatage

### Validation de formulaire

```dart
final result = formSchema.validate({
  'email': 'test@example.com',
  'name': 'John Doe',
});

if (result.isValid) {
  // Données valides
} else {
  // Afficher les erreurs
  print(result.errors);
}
```

### Formatage des valeurs

```dart
// Formatage automatique selon le type
final column = listSchema.getColumn('price');
final formatted = column.formatValue(1234.56); // "1234.56 €"

final dateColumn = listSchema.getColumn('created_at');
final formattedDate = dateColumn.formatValue(DateTime.now()); // "15/01/2024"
```

## Gestion des versions

```dart
// Vérification de compatibilité
final isCompatible = feature.isCompatibleWith('1.2.0');

// Comparaison de versions
final version1 = Version.parse('1.2.0');
final version2 = Version.parse('1.1.5');
final isNewer = version1 > version2; // true
```

## Exemple d'utilisation complète

Voir le fichier `sync_models_example.dart` pour un exemple complet d'utilisation de toutes les classes.

## Génération du code

Pour régénérer les fichiers de sérialisation JSON après modification :

```bash
cd mobile
dart run build_runner build
```

## Dépendances requises

```yaml
dependencies:
  json_annotation: ^4.8.1

dev_dependencies:
  build_runner: ^2.4.7
  json_serializable: ^6.7.1
```

## Notes importantes

1. **Compatibilité des versions** : Utilisez la classe `Version` pour les comparaisons sémantiques
2. **Permissions** : Toujours vérifier les permissions avant d'afficher une fonctionnalité
3. **Validation** : Utilisez la validation côté client mais toujours valider côté serveur
4. **Sécurité** : La signature du manifeste doit être validée avant utilisation
5. **Performance** : Utilisez le cache local pour éviter les appels réseau répétés