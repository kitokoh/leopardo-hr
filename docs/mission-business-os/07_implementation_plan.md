# 07 — Implementation Plan

**Branche unique :** `mission/leopardo-business-os` (depuis `main` @ `e9c5f986c`)
**Règle :** une branche, des commits atomiques, un lot = un sujet cohérent + ses tests.
Aucune branche par issue, aucune modification directe de `main`, aucun test supprimé.

---

## 0. Principe de séquencement

Trois contraintes ordonnent les lots :

1. **Le socle de données d'abord.** Sans `sites` exploitable (code/type/status/index) et
   sans `companies.market_type`, tout ce qui suit serait à refaire.
2. **Le parcours ensuite.** L'onboarding ne peut transmettre une intention que si la
   colonne existe ; il ne peut créer un établissement que si la table le permet.
3. **La preuve en dernier, mais pas en option.** Un lot n'est « livré » que si le test
   correspondant s'exécute. Un lot sans test est un lot non livré.

---

## Lot 1 — Établissement de premier ordre (fondation)

| | |
|---|---|
| **Issues** | #T-01, #O-03 |
| **Périmètre** | migration additive `sites` (`code`, `site_type`, `status`, `timezone`, `currency`, `updated_at`) + index `(company_id)` + unique partiel `(company_id, code)` ; colonnes miroir `CreatesMvpSchema` ; `Site` model (fillable, casts, scope) ; `SiteController` validation ; seed du site principal dans `provisionSharedCompany()` |
| **Tests** | création de site avec code dupliqué → 422 ; cross-tenant → 404 ; provisioning crée exactement 1 site ; rejeu de provisioning n'en crée pas un 2ᵉ |
| **Risque** | colonne `code` non unique globalement — l'unicité est **par tenant** uniquement |
| **Rollback** | migration `down()` supprime colonnes et index, jamais de données métier |

## Lot 2 — Intention de marché portée jusqu'au tenant

| | |
|---|---|
| **Issues** | #O-01, #O-02, #O-09 |
| **Périmètre** | `companies.market_type`, `trade_profile` (nullable + CHECK) ; allowlist serveur (miroir de `TRADE_MODES`) ; `trade-intent.ts` étendu aux sous-profils ; `/signup` consomme `?trade=` et `?profile=` ; `SignupForm` transmet ; `CompanyProvisioningService` persiste ; pré-cochage des modules par marché |
| **Tests** | valeur hors allowlist → 422 avant toute écriture ; `?trade=international` présent dans le payload ; tenant créé avec `market_type` attendu ; sous-profil domestique grossiste → modules retail activés |
| **Risque** | divergence entre l'allowlist front et back → test de parité obligatoire |
| **Rollback** | colonnes nullables, aucun comportement dépendant si NULL |

## Lot 3 — Onboarding reprenable et idempotent

| | |
|---|---|
| **Issues** | #O-05, #O-06, #O-04 |
| **Périmètre** | `companies.onboarding_state` jsonb (étape courante, horodatages) ; endpoint de lecture/écriture d'étape ; clé d'idempotence cliente sur `/trial/signup` (header `Idempotency-Key`, table `idempotency_keys` ou contrainte sur `trial_signups`) ; profil professionnel dans l'entretien |
| **Tests** | deux `POST /trial/signup` avec la même clé → un seul tenant ; deux soumissions concurrentes sans clé → un seul tenant (contrainte unique + gestion `23505`) ; reprise après abandon → même étape ; étape validée non rejouée |
| **Risque** | le verrou `23505` sur `slug` n'est pas suffisant si deux e-mails différents visent le même nom — la clé d'idempotence est la vraie garantie |
| **Rollback** | colonne jsonb supprimable sans perte métier |
| **État (2026-10-18)** | **Terminé.** Incrément 1 : idempotence cliente de `/trial/signup` — table `public.idempotency_keys` (migration `2026_10_17_130000_8626`), `App\Core\Http\Idempotency\IdempotencyStore`, wrapper dans `SelfServiceTrialController::signup` (header `Idempotency-Key`, rejeu `Idempotent-Replayed`), test `TrialSignupIdempotencyTest` (4 tests). Incrément 2 : reprise du parcours — colonne `companies.onboarding_state` (migration `2026_10_18_100000_8627`), `ResumeOnboardingStep`, `CompanyOnboardingStateWriter`, `OnboardingStateController` (`GET`/`PATCH /onboarding-setup/state`), test `OnboardingResumeTest` (11 tests), i18n fr/en/ar/tr. **Reste hors de ce lot** : brancher le front sur `furthest_step` et le profil professionnel dans l'entretien. |

## Lot 4 — Honnêteté des applications et du hors-ligne

| | |
|---|---|
| **Issues** | #A-01, #A-02, #F-01, #A-05 |
| **Périmètre** | `SoftwareApp.offline` remplacé par `'verified' \| 'implemented' \| 'absent'` + libellé i18n ; version lue depuis la config de canal ; état de configuration par tenant (grant + `edge_nodes` du tenant) ; statut réel des jobs Firebase requalifié en `not_distributed` tant qu'ils échouent |
| **Tests** | aucun `offline: true` sans test référencé ; statut `published` uniquement si cible `link` ; `mon-logiciel` n'affiche aucune version si inconnue |
| **Risque** | requalifier une app en `not_distributed` est un changement de promesse visible → à valider produit |
| **Rollback** | réversible côté catalogue (données statiques) |

## Lot 5 — Diagnostic de synchronisation

| | |
|---|---|
| **Issues** | #F-03, #F-05, #F-07, #F-02 |
| **Périmètre** | `sync_queue.idempotency_key` + index unique partiel + `last_error`/`last_attempt_at` ; garde de cohérence schéma tenant ↔ edge ; panneau de diagnostic (file, âge, dernier échec, conflits) côté tenant et côté superadmin |
| **Tests** | rejeu avec même clé → 1 ligne ; conflit → statut `conflict` + trace ; garde CI détecte une divergence de schéma |
| **Risque** | l'index unique peut révéler des doublons **déjà présents** en production → migration en deux temps (dédoublonnage puis index) |
| **Rollback** | index supprimable, colonnes nullables |

## Lot 6 — Console superadmin du provisionnement

| | |
|---|---|
| **Issues** | #S-01, #S-03, #S-04 |
| **Périmètre** | vue « Provisionnement » (tenants, étapes franchies, échecs, tenants sans établissement) ; catalogue verticales/livré avec maturité et canaux ; diagnostic sync par tenant |
| **Tests** | accès refusé sans `platform.permission:companies.view` ; aucune donnée métier client dans la liste ; chaque action journalisée |
| **Risque** | ne pas exposer d'agrégats permettant de reconstituer des données clientes |
| **Rollback** | écrans isolés, suppression sans impact backend |

## Lot 7 — Pages d'erreur et branding borné

| | |
|---|---|
| **Issues** | #B-01, #B-02, #B-03, #B-04 |
| **Périmètre** | page 403 (demande d'accès), page 500 avec identifiant de corrélation, branding sur connexion/chargement, validation serveur du branding (format, taille, contraste AA) |
| **Tests** | 403 rendue pour un rôle insuffisant ; 500 n'expose aucune trace ; branding avec contraste < AA refusé 422 ; `prefers-reduced-motion` neutralise les transitions |
| **Risque** | la page 500 doit rester servie même si le backend est indisponible → rendu statique Next |
| **Rollback** | pages additives |

## Lot 8 — Preuves de bout en bout

| | |
|---|---|
| **Issues** | #T-03, #Q-02, #Q-04, #Q-05 |
| **Périmètre** | 1 scénario E2E par verticale présente, suite d'isolation multi-tenant transversale, 5 scénarios hors-ligne |
| **Tests** | chaque scénario vérifie la **première opération métier réussie**, pas seulement la connexion |
| **Risque** | durée CI → exécution en nightly pour les 12 verticales, bloquante pour les 3 prioritaires (restaurant, station, travel) |
| **Rollback** | suites isolées |

---

## Ordre d'exécution et dépendances

```
Lot 1 (sites) ──┬──► Lot 2 (marché) ──► Lot 3 (reprise/idempotence) ──► Lot 8 (E2E)
                └──► Lot 6 (console provisioning)
Lot 4 (apps/offline honnête) ──► Lot 5 (diagnostic sync) ──► Lot 8
Lot 7 (erreurs/branding) — indépendant
```

**Dépendances externes (non implémentées dans cette branche) :**

| Dépendance | État | Effet |
|---|---|---|
| `bc/bc-36-trade-os` (PR #8487) | ouverte, 207 fichiers | apporte `Modules/Trade` + `TradeIntegrationRegistry` ; la mission **référence** ce travail et ne le duplique pas |
| `bc/bc-37-farm-os` | branche | verticale Farm absente de `main` |
| Jobs Firebase App Distribution | en échec sur `main` | canal « build testeurs » non opérationnel |
| `#8496`, `#8498`, `#8583`, `#8585`, `#8610` | issues ouvertes | isolation edge, rejeu réel, logo station, app pompiste, vue groupe resto |

---

## Politique de commit

Format : `type(scope): sujet à l'impératif` (50 car. max), corps expliquant le **pourquoi**
et la **preuve**, entrée `CHANGELOG.md` sous `[Unreleased]` dès qu'un périmètre critique est
touché (garde `check-governance.ps1`, motif `^(api/|docs/GESTION_PROJET/|.github/|front/|…)`).

Types utilisés : `feat`, `fix`, `docs`, `test`, `refactor`, `chore`.

Un commit = un lot cohérent, jamais un lot + du nettoyage opportuniste.

---

## Définition de « terminé » pour un lot

1. Le code est écrit et le lint/typage passe (`pint --test`, `phpstan`, `tsc`, `eslint`).
2. Les tests du lot existent et **passent localement ou en CI** — avec la preuve citée.
3. Aucun test existant supprimé ou affaibli.
4. La documentation concernée est mise à jour **dans le même commit**.
5. Aucune régression sur la baseline CI de `main` (`14_audit_baseline.md`).
6. Aucune revendication de capacité non couverte par un test.

---

## Estimation et priorités

| Priorité | Lots | Motif |
|---|---|---|
| **P0** | 1, 2, 3 | sans eux, aucun tenant n'est réellement opérationnel et l'onboarding ment |
| **P1** | 4, 5 | promesses applications/hors-ligne vérifiables, risque financier terrain |
| **P2** | 6, 7 | exploitation et confiance, non bloquants pour un pilote |
| **P3** | 8 | preuve systématique, nécessaire avant généralisation |

Volume indicatif : 1 à 3 jours d'ingénierie par lot P0/P1, hors revue et CI.
