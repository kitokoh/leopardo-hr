# Catalogue d'événements versionnés (MAT-006)

- **Statut :** ratifié — référentiel des événements de domaine du monorepo
- **Date :** 2026-08-28
- **Fichier machine :** `docs/architecture/event-catalogue.yaml`
- **Garde CI :** `dev-hub/tools/check-event-catalogue.sh` (job Hygiene Guards,
  workflow `architecture-check.yml`)
- **Tests :** `api/tests/Feature/Events/EventCatalogueTest.php`

## Objectif

Chaque événement de domaine (publié dans l'outbox/les files ou dispatché
in-process) possède une **entrée de catalogue** : nom stable, version semver,
propriétaire (bounded context), classe PHP, **schema de payload testable** et
règles de compatibilité/dépréciation. Un consumer qui déclare consommer une
version incompatible avec celle publiée est **bloqué par le garde CI**.

## Règles

1. **Nom stable** : `snake_case` pointé (`bc.agregat.événement`), immuable une
   fois publié. Le nom ne change jamais — on incrémente la version.
2. **Versionnement semver** :
   - `major` : rupture de contrat (champ supprimé/renommé, sémantique changée) ;
   - `minor` : ajout compatible (champ optionnel ajouté) ;
   - `patch` : clarification documentaire, aucun impact payload.
3. **Compatibilité** : un consumer déclare la version minimale qu'il accepte
   (`consumes.from`). Le garde bloque tout consumer dont la version déclarée
   est inférieure à la version minimale compatible d'un événement.
4. **Dépréciation** : une version dépréciée reste publiée au moins deux
   majors ; `deprecated_since` + `removal_at` sont renseignés dans le
   catalogue avant toute suppression. Aucun événement n'est supprimé du
   catalogue sans cette période.
5. **Schema testable** : chaque entrée embarque un bloc `schema` (JSON Schema
   draft-07, sous-ensemble) et un `sample` (payload concret). Le test
   `EventCatalogueTest` valide que le sample satisfait le schema, que le
   schema est un JSON Schema valide et que le catalogue est en parité avec les
   classes d'événements réelles.
6. **PII** : aucun champ de payload ne contient de PII en clair ; les
   références à des personnes/entreprises passent par des identifiants
   (`employee_id`, `company_id`…). Le garde refuse les noms de champs PII
   dans les schémas (`email`, `phone`, `ssn`, `salary`… en clair).

## Fichier machine

`docs/architecture/event-catalogue.yaml` :

```yaml
version: "1.0"
owner: platform
events:
  - name: employee.created
    version: 1.0.0
    bc: hr
    class: App\Events\EmployeeCreated
    description: Un employé est créé dans un tenant.
    schema:
      type: object
      required: [event_name, version, occurred_at, company_id, data]
      properties:
        event_name: { type: string, enum: [employee.created] }
        version: { type: string }
        occurred_at: { type: string, format: date-time }
        company_id: { type: string, format: uuid }
        data:
          type: object
          required: [employee_id]
          properties:
            employee_id: { type: string, format: uuid }
    sample:
      event_name: employee.created
      version: "1.0.0"
      occurred_at: "2026-08-28T12:00:00+00:00"
      company_id: "11111111-1111-4111-8111-111111111111"
      data: { employee_id: "22222222-2222-4222-8222-222222222222" }
    deprecation: null
```

## Ajout / modification d'un événement

1. Créer/modifier la classe dans `api/app/Events/` (ou `Domain/Events` du
   module propriétaire).
2. Ajouter/mettre à jour l'entrée du catalogue (nom, version, schema, sample).
3. Lancer `bash dev-hub/tools/check-event-catalogue.sh` et le test
   `EventCatalogueTest` — le garde CI les exécute sur chaque PR.
4. Si un consumer existant devient incompatible, incrémenter la version de
   l'événement (major) et documenter la migration dans le CHANGELOG.
