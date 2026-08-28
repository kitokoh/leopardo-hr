# Spécification — Module CRM Client (interne tenant)

- **Statut :** actif — socle V0 (#5705 → #5716), extension V1 (#5717 → #5731)
- **Date :** 2026-08-28
- **Plan :** `docs/specifications/PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`

---

## 1. Positionnement

Module DDD `App\Modules\CRM` — CRM commercial **de l'entreprise cliente**
(tenant). Il est distinct du CRM commercial Leopardo (`Platform`/`Marketing`)
et n'importe jamais ses agrégats (garde d'isolation #5584, ADR-CRM-001/002).

## 2. Structure du module (DDD — constitution §VI)

```
api/app/Modules/CRM/
├── Application/
│   ├── Actions/            # orchestration par cas d'usage
│   └── DTOs/               # données d'entrée validées
├── Domain/
│   ├── Contracts/          # interfaces repos/services (ports)
│   ├── Enums/              # statuts, sources, types (whitelists)
│   ├── Exceptions/         # domain exceptions (404/422 métier)
│   └── Models/             # Eloquent tenant-scoped (AbstractTenantModel)
├── Infrastructure/
│   ├── Repositories/       # implémentations Eloquent des contrats
│   └── Services/           # services techniques (dédup, recherche, PII)
├── Interfaces/
│   └── Api/V1/
│       ├── Controllers/
│       ├── Requests/       # validation stricte (ADR-CRM-005)
│       └── Resources/      # sérialisation (PII masquée)
└── Providers/
    └── CrmServiceProvider.php
```

Contraintes :

- Aucune dépendance directe vers `Platform` ou `Marketing` (ni aucun module métier).
- `Domain/` ne dépend pas de `Infrastructure/` (ports & adapters).
- Provider enregistré dans `api/bootstrap/providers.php` (ordre : après `PlatformServiceProvider`).
- Routes dans `api/routes/modules/crm.php` (groupe `api.manager` + Policies).

## 3. Modèle de données V0 (schéma tenant)

| Table | Entité | Notes clés |
|---|---|---|
| `crm_accounts` | Compte (organisation cliente du tenant) | `company_id` NOT NULL, `name`, `status`, `owner_id`, PII chiffrée, archive |
| `crm_contacts` | Contact | `company_id` NOT NULL, `account_id` FK, `is_primary` unique par account, PII chiffrée, opt-ins |
| `crm_leads` | Prospect | `company_id` NOT NULL, `source`, `status`, `owner_id` |
| `crm_pipelines` | Pipeline | `company_id` NOT NULL, `name`, `stages` (JSON ordonné) |
| `crm_opportunities` | Opportunité | `company_id` NOT NULL, `pipeline_id`, `stage`, `amount`, `expected_close_at` |
| `crm_activities` | Activité (appel, email, note…) | `company_id` NOT NULL, entité cible polymorphe, `type`, `done_at` |
| `crm_tasks` | Tâche/relance | `company_id` NOT NULL, `due_at`, `assignee_id`, `done` |

Règles :

- `company_id` uuid **non nullable** partout, indexé systématiquement.
- Colonnes PII (email, téléphone, notes privées…) chiffrées au repos
  (`SensitiveDataEncryptor`) et non exposées en clair dans les Resources
  sans autorisation (#5713).
- Archivage **soft** : `archived_at` nullable, jamais de DELETE destructif
  côté API (sauf purge RGPD encadrée).
- Index composites : `(company_id, status)`, `(company_id, owner_id)`,
  `(company_id, account_id)` sur contacts, `(company_id, pipeline_id, stage)`
  sur opportunités.

## 4. Règles métier V0

1. **Tenant strict** : toute requête scopée `company_id` (ou `search_path`).
   Accès cross-tenant = 404 (test obligatoire par endpoint sensible).
2. **Propriété** : `owner_id` nullable (non assigné) ; seuls les membres du
   tenant avec le rôle CRM peuvent créer/modifier ; Policies par entité.
3. **Contact primaire** : au plus un `is_primary = true` par `account_id`
   (contrainte DB partielle + logique d'application).
4. **Statuts contrôlés** : whitelists via `Domain/Enums` + `Rule\In` dans les
   Requests. Statut inconnu = 422.
5. **Filtres/tris/tailles** : whitelist de colonnes triables, pagination
   plafonnée (max 100/page), filtres par statut/source/owner validés.
6. **Audit** : toute mutation sensible (PII, suppression, changement de
   propriétaire) → `AuditLog::create()` (préfixe `crm.*`).
7. **N+1** : index avec eager loading (`account.primaryContact`,
   `opportunity.pipeline`, `owner`).

## 5. API V0 (périmètre #5712, détails dans l'OpenAPI)

```
GET    /api/v1/crm/accounts               — liste (filtres, tri, pagination)
POST   /api/v1/crm/accounts               — créer
GET    /api/v1/crm/accounts/{account}     — détail
PUT    /api/v1/crm/accounts/{account}     — modifier
DELETE /api/v1/crm/accounts/{account}     — archiver
GET    /api/v1/crm/accounts/{account}/contacts
POST   /api/v1/crm/accounts/{account}/contacts
…      (contacts CRUD)
GET/POST /api/v1/crm/leads                — leads V0
GET/POST /api/v1/crm/pipelines            — pipelines V0
GET/POST /api/v1/crm/opportunities        — opportunités V0
GET/POST /api/v1/crm/tasks                — tâches V0
GET/POST /api/v1/crm/activities           — activités V0
```

Réponses : enveloppe `data`/`meta` (pagination), erreurs localisées ×4
(fr/en/tr/ar) avec codes métier (`CRM_ACCOUNT_NOT_FOUND`, …).

## 6. Extension V1 (aperçu — détails par issue)

- Conversion lead → account/contact/opportunity (#5717), transactionnelle.
- Déduplication (similarité nom/email) + fusion supervisée (#5718).
- Recherche plein-texte tenant-scoped (#5719).
- Timeline et relances (#5720), dashboard KPIs (#5721).
- Consentements (#5722), segments (#5723), campagnes (#5724), canaux
  WhatsApp (#5725), email (#5726), SMS (#5727), automatisations (#5728),
  exports/read models (#5729), mobile (#5730), hardening (#5731).

## 7. Définition of Done

Identique au plan (§6) : tests avant code, PHPStan strict 0, tenant + entrées
strictes, OpenAPI/CHANGELOG à jour, CI verte, `Closes #N`, branche supprimée.
