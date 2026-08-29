# Registre RGPD — Données personnelles du CRM client

- **Statut :** actif — cycle de vie RGPD des données CRM client (issue #5739)
- **Version :** 1.0 | 2026-08-28
- **Manifeste exécutable :** `api/config/crm-rgpd.php` (versionné — source de
  vérité pour les commandes)
- **Complète :** `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` (registre RH, #5713)

> Ce registre documente la conservation, l'anonymisation, la suppression,
> l'accès et l'opposition pour les données du CRM client (accounts, contacts,
> leads, opportunités, activités, tâches, consentements, messages, exports).
> Toute évolution de traitement PII CRM met à jour CE document ET le manifeste.

---

## 1. Rôles et responsabilités

| Rôle | Responsabilité |
|---|---|
| Client employeur (tenant) | **Responsable de traitement** pour ses données CRM (RGPD art. 4.7) |
| Leopardo RH | Sous-traitant SaaS (hébergement, sécurité, support) — art. 28 |
| Super-admin Leopardo | Accès plateforme limité : provisioning, support, conformité |
| Utilisateurs tenant (principal/rh/employé CRM) | Accès selon rôles, Policies et tests cross-tenant |

## 2. Matrice PII / finalité / base légale / durée / responsable

Source : `api/config/crm-rgpd.php` (chaque entrée lie une table tenant à sa
finalité, sa base légale, sa durée de conservation et son responsable).

| Table | Données PII | Finalité | Base légale | Durée | Responsable |
|---|---|---|---|---|---|
| `crm_accounts` | name, email, phone, address | Gestion des comptes clients | 6.1.b / 6.1.f | 10 ans | Client employeur |
| `crm_contacts` | first_name, last_name, email, phone | Gestion des contacts | 6.1.b / 6.1.f | 10 ans | Client employeur |
| `crm_leads` | first_name, last_name, email, phone | Gestion des prospects | 6.1.a / 6.1.f | 5 ans | Client employeur |
| `crm_opportunities` | name | Suivi des opportunités | 6.1.b | 10 ans | Client employeur |
| `crm_activities` | description, note | Historique d'activités | 6.1.f | 10 ans | Client employeur |
| `crm_tasks` | description | Tâches et relances | 6.1.b | 10 ans | Client employeur |
| `crm_consents` | contact_id | Consentements par canal/finalité | 6.1.a | 10 ans | Client employeur |
| `crm_external_events` | payload | Événements providers (webhooks) | 6.1.f | 2 ans | Client employeur |
| `crm_conversations` | content, from/to_address | Conversations (email/WhatsApp/SMS) | 6.1.b / 6.1.a | 5 ans | Client employeur |

## 3. Cycle de vie

### 3.1 Droit d'accès et d'export

- Export autorisé : tout utilisateur tenant dispose d'un export de ses données
  (endpoints privacy existants : `GET /api/v1/privacy/export`).
- **Exports CRM : autorisés, expirants, audités, nettoyés** — un artefact
  d'export doit :
  1. être étiqueté du tenant (convention #5736 : `{tenant}-{type}-{période}`) ;
  2. porter une expiration (TTL court, purge automatique) ;
  3. être journalisé (audit : qui, quoi, quand — sans PII inutile) ;
  4. être nettoyé à expiration (job de purge).

### 3.2 Droit à l'effacement / anonymisation

- **Commandes sûres et rejouables** :
  ```bash
  php artisan crm:anonymize --company=<uuid> --dry-run   # simulation
  php artisan crm:anonymize --company=<uuid> --force     # écrit
  php artisan crm:anonymize --all --force                # tous tenants actifs
  ```
- **Idempotence** : remplacements déterministes (même entrée → même sortie) ;
  relancer ne change rien — la commande est rejouable.
- **Tables absentes** (socle V0 non mergé) : ignorées proprement avec journal
  audit `crm.anonymisation.skipped` — la commande s'active seule au merge des
  migrations CRM.
- **Anonymisation idempotente par type** : email → `u{hash}@anonymised.invalid`,
  téléphone → `+00{hash}`, nom → `Anonyme {hash}`, autre → `[anonymisé-{hash}]`.

### 3.3 Droit d'opposition et retrait de consentement

- `CrmConsentGate::canSend(channel, purpose, contactId, companyId)` :
  le **retrait d'un consentement bloque les nouveaux envois** concernés
  (canal + finalité) — fail-closed : pas de consentement = pas d'envoi.
- L'historique des envois déjà réalisés n'est pas effacé : il reste audité et
  soumis aux durées de rétention ci-dessus.

### 3.4 Exceptions d'audit et masquage des logs

- Les journaux (`structured`, `audit`) ne contiennent **jamais** de valeurs PII :
  uniquement `table`, `rows`, `company_id` (uuid) — pas d'email/téléphone/nom.
- Exception d'audit : le journal des opérations de conformité (qui a
  anonymisé quoi) est conservé séparément et ne peut pas être anonymisé lui-même.
- Les fixtures, seeds et métriques ne contiennent aucune donnée réelle
  (génération faker — voir `Tests\Support\CRM`, #5738).

## 4. Vérifications automatisées

- `api/tests/Feature/CRM/Rgpd/CrmRgpdLifecycleTest.php` — #5739 :
  - anonymisation idempotente/rejouable (deux passages → valeurs identiques) ;
  - aucune PII dans les logs de la commande (spy du canal audit) ;
  - retrait de consentement → envois bloqués ; ré-accord → rétablis ;
  - table absente → skip propre + fail-closed du consent gate ;
  - registre versionné lié aux tables (manifeste + `CrmRgpdRegistry`).

## 5. Historique

| Version | Date | Changement |
|---|---|---|
| 1.0 | 2026-08-28 | Registre initial du CRM client (issue #5739) |
