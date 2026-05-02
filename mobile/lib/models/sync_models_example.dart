// Exemple d'utilisation des nouvelles classes de synchronisation
// Ce fichier démontre comment utiliser Feature, FeatureManifest, FormSchema, ListSchema, et DetailSchema

import 'feature.dart';
import 'feature_manifest.dart';
import 'form_schema.dart';
import 'list_schema.dart';
import 'detail_schema.dart';

/// Exemple de création et utilisation des modèles de synchronisation
class SyncModelsExample {

  /// Exemple de création d'une Feature
  static Feature createSampleFeature() {
    return Feature(
      key: 'employee_management',
      title: 'Gestion des Employés',
      description: 'Créer, modifier et gérer les employés',
      endpoint: '/api/v1/employees',
      methods: ['GET', 'POST', 'PUT', 'DELETE'],
      parameters: {
        'list': {
          'page': {'type': 'integer', 'required': false},
          'per_page': {'type': 'integer', 'required': false},
          'search': {'type': 'string', 'required': false}
        },
        'create': {
          'first_name': {'type': 'string', 'required': true},
          'last_name': {'type': 'string', 'required': true},
          'email': {'type': 'email', 'required': true}
        }
      },
      responseSchema: {
        'employee': {
          'id': 'integer',
          'first_name': 'string',
          'last_name': 'string',
          'email': 'string',
          'created_at': 'datetime'
        }
      },
      permissions: ['employees.view', 'employees.create'],
      minimumMobileVersion: '1.0.0',
      maximumMobileVersion: null,
      type: FeatureType.list,
      formSchema: _createSampleFormSchema(),
      listSchema: _createSampleListSchema(),
      detailSchema: _createSampleDetailSchema(),
    );
  }

  /// Exemple de création d'un FeatureManifest
  static FeatureManifest createSampleManifest() {
    return FeatureManifest(
      version: '1.2.0',
      generatedAt: DateTime.now(),
      mobileVersionMin: '1.0.0',
      signature: 'sha256:abc123def456...',
      features: [
        createSampleFeature(),
        // Autres fonctionnalités...
      ],
    );
  }

  /// Exemple de création d'un FormSchema
  static FormSchema _createSampleFormSchema() {
    return FormSchema(
      fields: [
        FormField(
          name: 'first_name',
          type: FormFieldType.text,
          label: 'Prénom',
          required: true,
          validation: {'min_length': 2, 'max_length': 50},
          placeholder: 'Entrez le prénom',
        ),
        FormField(
          name: 'last_name',
          type: FormFieldType.text,
          label: 'Nom',
          required: true,
          validation: {'min_length': 2, 'max_length': 50},
          placeholder: 'Entrez le nom',
        ),
        FormField(
          name: 'email',
          type: FormFieldType.email,
          label: 'Email',
          required: true,
          placeholder: 'exemple@domaine.com',
        ),
        FormField(
          name: 'phone',
          type: FormFieldType.phone,
          label: 'Téléphone',
          required: false,
          placeholder: '+33 1 23 45 67 89',
        ),
        FormField(
          name: 'department',
          type: FormFieldType.select,
          label: 'Département',
          required: true,
          options: [
            FormFieldOption(value: 'hr', label: 'Ressources Humaines'),
            FormFieldOption(value: 'it', label: 'Informatique'),
            FormFieldOption(value: 'sales', label: 'Ventes'),
          ],
        ),
      ],
      submitEndpoint: '/api/v1/employees',
      submitMethod: 'POST',
    );
  }

  /// Exemple de création d'un ListSchema
  static ListSchema _createSampleListSchema() {
    return ListSchema(
      columns: [
        ListColumn(
          name: 'id',
          label: 'ID',
          type: ListColumnType.number,
          width: 80,
          alignment: ListColumnAlignment.center,
        ),
        ListColumn(
          name: 'full_name',
          label: 'Nom complet',
          type: ListColumnType.text,
          sortable: true,
        ),
        ListColumn(
          name: 'email',
          label: 'Email',
          type: ListColumnType.text,
          sortable: true,
        ),
        ListColumn(
          name: 'created_at',
          label: 'Date de création',
          type: ListColumnType.date,
          sortable: true,
          alignment: ListColumnAlignment.center,
        ),
        ListColumn(
          name: 'status',
          label: 'Statut',
          type: ListColumnType.badge,
          alignment: ListColumnAlignment.center,
        ),
      ],
      pagination: ListPagination(
        pageSize: 20,
        enabled: true,
        pageSizeOptions: '10,20,50,100',
      ),
      sorting: ListSorting(
        defaultColumn: 'created_at',
        defaultDirection: ListSortDirection.desc,
        multiColumn: false,
      ),
      filtering: ListFiltering(
        filters: [
          ListFilter(
            name: 'search',
            label: 'Rechercher',
            type: ListFilterType.text,
            placeholder: 'Nom, email...',
          ),
          ListFilter(
            name: 'department',
            label: 'Département',
            type: ListFilterType.select,
            options: ['hr', 'it', 'sales'],
          ),
        ],
        quickFilters: true,
      ),
      actions: [
        ListAction(
          name: 'view',
          label: 'Voir',
          type: ListActionType.view,
          icon: 'visibility',
        ),
        ListAction(
          name: 'edit',
          label: 'Modifier',
          type: ListActionType.edit,
          icon: 'edit',
          condition: 'status == "active"',
        ),
        ListAction(
          name: 'delete',
          label: 'Supprimer',
          type: ListActionType.delete,
          icon: 'delete',
          confirmRequired: true,
          condition: 'role != "admin"',
        ),
      ],
      searchPlaceholder: 'Rechercher un employé...',
      enableSearch: true,
      enableRefresh: true,
    );
  }

  /// Exemple de création d'un DetailSchema
  static DetailSchema _createSampleDetailSchema() {
    return DetailSchema(
      title: 'Détails de l\'employé',
      layout: DetailLayout.vertical,
      sections: [
        DetailSection(
          name: 'personal_info',
          title: 'Informations personnelles',
          icon: 'person',
          fields: [
            DetailField(
              name: 'first_name',
              label: 'Prénom',
              type: DetailFieldType.text,
              size: DetailFieldSize.half,
            ),
            DetailField(
              name: 'last_name',
              label: 'Nom',
              type: DetailFieldType.text,
              size: DetailFieldSize.half,
            ),
            DetailField(
              name: 'email',
              label: 'Email',
              type: DetailFieldType.email,
              icon: 'email',
            ),
            DetailField(
              name: 'phone',
              label: 'Téléphone',
              type: DetailFieldType.phone,
              icon: 'phone',
            ),
          ],
        ),
        DetailSection(
          name: 'work_info',
          title: 'Informations professionnelles',
          icon: 'work',
          fields: [
            DetailField(
              name: 'department',
              label: 'Département',
              type: DetailFieldType.text,
            ),
            DetailField(
              name: 'position',
              label: 'Poste',
              type: DetailFieldType.text,
            ),
            DetailField(
              name: 'salary',
              label: 'Salaire',
              type: DetailFieldType.currency,
              size: DetailFieldSize.half,
            ),
            DetailField(
              name: 'start_date',
              label: 'Date d\'embauche',
              type: DetailFieldType.date,
              size: DetailFieldSize.half,
            ),
          ],
        ),
        DetailSection(
          name: 'system_info',
          title: 'Informations système',
          icon: 'settings',
          collapsible: true,
          collapsed: true,
          fields: [
            DetailField(
              name: 'created_at',
              label: 'Créé le',
              type: DetailFieldType.datetime,
            ),
            DetailField(
              name: 'updated_at',
              label: 'Modifié le',
              type: DetailFieldType.datetime,
            ),
            DetailField(
              name: 'is_active',
              label: 'Actif',
              type: DetailFieldType.boolean,
            ),
          ],
        ),
      ],
      actions: [
        DetailAction(
          name: 'edit',
          label: 'Modifier',
          type: DetailActionType.edit,
          icon: 'edit',
          endpoint: '/api/v1/employees/{id}',
          method: 'PUT',
        ),
        DetailAction(
          name: 'delete',
          label: 'Supprimer',
          type: DetailActionType.delete,
          icon: 'delete',
          endpoint: '/api/v1/employees/{id}',
          method: 'DELETE',
          confirmRequired: true,
          confirmMessage: 'Êtes-vous sûr de vouloir supprimer cet employé ?',
        ),
      ],
    );
  }

  /// Exemple d'utilisation des méthodes de validation et de formatage
  static void demonstrateUsage() {
    final feature = createSampleFeature();
    final manifest = createSampleManifest();

    // Test de compatibilité de version
    print('Feature compatible avec v1.1.0: ${feature.isCompatibleWith('1.1.0')}');

    // Test de permissions
    final userPermissions = ['employees.view', 'employees.create'];
    print('Utilisateur autorisé: ${feature.hasRequiredPermissions(userPermissions)}');

    // Filtrage des fonctionnalités du manifeste
    final compatibleFeatures = manifest.getCompatibleFeatures('1.1.0');
    final authorizedFeatures = manifest.getAuthorizedFeatures(userPermissions);
    final availableFeatures = manifest.getAvailableFeatures('1.1.0', userPermissions);

    print('Fonctionnalités compatibles: ${compatibleFeatures.length}');
    print('Fonctionnalités autorisées: ${authorizedFeatures.length}');
    print('Fonctionnalités disponibles: ${availableFeatures.length}');

    // Test de validation de formulaire
    if (feature.formSchema != null) {
      final formData = {
        'first_name': 'Jean',
        'last_name': 'Dupont',
        'email': 'jean.dupont@exemple.com',
        'department': 'it',
      };

      final validationResult = feature.formSchema!.validate(formData);
      print('Formulaire valide: ${validationResult.isValid}');
      if (!validationResult.isValid) {
        print('Erreurs: ${validationResult.errors}');
      }
    }

    // Test de formatage des colonnes de liste
    if (feature.listSchema != null) {
      final dateColumn = feature.listSchema!.getColumn('created_at');
      if (dateColumn != null) {
        final formattedDate = dateColumn.formatValue(DateTime.now());
        print('Date formatée: $formattedDate');
      }
    }

    // Sérialisation JSON
    final featureJson = feature.toJson();
    final manifestJson = manifest.toJson();

    print('Feature sérialisée: ${featureJson.keys.length} clés');
    print('Manifest sérialisé: ${manifestJson.keys.length} clés');

    // Désérialisation JSON
    final featureFromJson = Feature.fromJson(featureJson);
    final manifestFromJson = FeatureManifest.fromJson(manifestJson);

    print('Feature désérialisée: ${featureFromJson.key}');
    print('Manifest désérialisé: ${manifestFromJson.version}');
  }
}