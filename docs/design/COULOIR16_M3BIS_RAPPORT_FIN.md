# COULOIR 16 — M3-bis · Rapport de fin de lot

**Date** : 2026-11-13 · **Branche de base** : `main` @ `ad0c561cb` · **Auteur** : agent (compte Kitokoh)

---

## 1. Ce qui a été livré

Six PR **ouvertes, jamais mergées**, empilées dans l'ordre de relecture.

| # | Lot | Issue | Preuve exécutée |
|---|---|---|---|
| [#9443](https://github.com/kitokoh/leopardo-hr/pull/9443) | `public_profiles` — la langue suit le compte | #9449 | `PublicProfileTest` **21 tests / 81 assertions** |
| [#9444](https://github.com/kitokoh/leopardo-hr/pull/9444) | Piste d'audit du rattachement (ADR §8.3-3) | #9450 | `PublicIdentityBridgeAuditTest` **7 / 19** |
| [#9445](https://github.com/kitokoh/leopardo-hr/pull/9445) | Export RGPD art. 20 | #9451 | `PublicDataExportTest` **9 / 40** |
| [#9446](https://github.com/kitokoh/leopardo-hr/pull/9446) | Pages PWA `/activites` et `/profil` | #9452 | jest **32 tests** · 14 suites / 152 · tsc 0 · eslint 0 |
| [#9447](https://github.com/kitokoh/leopardo-hr/pull/9447) | Surface Léa séparée | #9453 | jest **15 tests** |
| [#9448](https://github.com/kitokoh/leopardo-hr/pull/9448) | README + audit de couverture tracé | — | documentation seule |

**Total backend exécuté : 37 tests, 140 assertions, 0 échec** (PostgreSQL 15, `CI=true`).
**Suite publique complète** (`PublicProfileTest` + `PublicDataExportTest` + `PublicIdentityBridgeAuditTest` + `PublicActivityTest` + `PublicProfileCoverageTest`) : **53 tests, 202 assertions, 0 échec**.

---

## 2. Les cinq manques de l'audit, et ce qui les rend réels

### 2.1 `public_profiles` — la langue suivait l'appareil

`PUBLIC_PROFILE_DATA_MODEL.md` §3 la déclare « à créer » depuis M0. Sans elle, la PWA et l'app Flutter écrivaient la langue **localement** : un client qui change de téléphone la perdait.

`users.preferred_language` est en `varchar(2)` — incapable de porter `ar-SA`. D'où `preferred_locale` en `varchar(8)`, qui **prime** pour la surface publique.

La contrainte `CHECK` (`notifications_opt_in = true` exige `notifications_consent_at`) est en **SQL**, pas en règle applicative : un `UPDATE` direct ne doit pas pouvoir créer l'état incohérent.

### 2.2 Piste d'audit — un rattachement contesté n'était pas traçable

`PublicIdentityBridge` écrivait le rattachement `users` ↔ `travel_customer_accounts` sans aucune ligne d'audit. C'est le point le plus sensible du canal : il relie une identité publique à un compte existant.

Désormais `AuditLog` avec `action = public_identity.attached`, `company_id` **NULL** (événement plateforme, il n'appartient à aucun tenant), et le numéro vérifié en `metadata` — sans lui, la ligne ne dit pas *pourquoi* les deux identités ont été jointes.

### 2.3 Export RGPD — l'écran promettait « bientôt disponible »

`GET /user/data-export` rend compte, profil, favoris, historique, références d'activité et jetons d'appareil **tronqués à 8 caractères**. Un secret de transport en clair permettrait de pousser des notifications au nom du client.

La réponse déclare `not_included` : aucune commande, réservation ni message d'un commerçant — elles appartiennent au professionnel et relèvent de son propre export.

### 2.4 Pages PWA — deux liens morts

`PublicBottomNav` renvoyait vers `/activites` et `/profil` depuis M1. **Les routes n'existaient pas** : 404. Les API qu'elles consomment étaient pourtant livrées.

Trois décisions de comportement :
- **`unauthenticated` n'est pas une erreur** — un visiteur sans compte lit « connectez-vous », jamais « impossible de charger ». Confondre les deux serait un mensonge sur l'état du système.
- **Aucun succès déduit localement** — un retrait de favori n'est affiché comme réussi qu'après un `2xx`.
- **`DELETE /user/favorites` répond 204 sans corps** — traité comme succès explicite, pas via `toResult` qui exigerait un `data` et conclurait à une erreur sur un succès. Bug réel trouvé en lisant le contrôleur.

### 2.5 Léa — séparée, et honnête sur son indisponibilité

La spec impose « Messages (Léa séparée) ». L'onglet ne présentait qu'une liste : rien ne distinguait l'assistante d'un commerçant.

**Il n'existe aucun point d'entrée public pour l'assistant** : `POST /api/v1/ai/chat` exige un tenant (`AITenantInjector` injecte `companyId` depuis l'employé authentifié), et un client grand public n'en a pas. Brancher un champ de saisie renverrait une erreur à **chaque** message.

L'écran **dit** donc l'indisponibilité. La séparation visuelle et accessible est en place : le jour où une route publique existera, c'est ce composant qu'on branchera.

---

## 3. Gardes de gouvernance — vérifiées par exécution

| Garde | Résultat |
|---|---|
| `check-migration-destructive-down.py` + 3 autres | ✅ 0 violation |
| `check-route-owner-guard.sh` | ✅ 0 violation (2 exceptions déclarées, même motif que `PublicActivityController`) |
| `check-openapi-route-coverage.py --strict-staleness` | ✅ `modules/user.php` : 4 → **1** route non couverte (la seule préexistante) |
| `check-layer-purity.sh` | ✅ 0 violation nouvelle — **1 réelle corrigée** (`Log` facade → helper `logger()`) |
| `check-i18n-diff.js` | ✅ 0 chaîne en dur |
| `check-public-promises.sh` | ✅ |
| `phpstan --level=8` | ✅ No errors (6 erreurs réelles corrigées sur l'export) |

---

## 4. Échecs CI restants — tous préexistants, prouvés

| Check | Preuve du caractère préexistant |
|---|---|
| `Route → OpenAPI coverage guard` | `main` run 43291846271 : `drift: 5`. Cette PR : **5 aussi** — zéro dérive ajoutée. |
| `i18n Comptabilité ×4` / `validate-and-sync` | `check-accounting-i18n.py` sur `origin/main` : 3 dérives (`tax_rate_in_use`, `wf_unknown_tax_rate`, `journal_types.`). Aucun `accounting.php` touché. |
| `Hygiene Guards` (#7960) | `check-without-global-scope.sh` sur `origin/main` : 6 fichiers `Accounting/*` en « NOUVEAU ». Aucun fichier `Accounting/` touché. |
| `Smoke build leopardo_employee` | `Gradle 8.14.3 < Flutter minimum 9.3.1`. Aucun `.dart`/`gradle` dans le diff. |
| `Backend Security (Composer Audit)` | `phpseclib 4.0.2` ; `git diff --stat origin/main...HEAD -- api/composer.lock` → **vide**. |
| Dérive i18n `sync-web.js` | `origin/main` est `DIRTY` de la même façon (44 clés présentes dans les catalogues web, absentes de la source). |

---

## 5. Seuils de qualité — **aucun mesuré**

Pas d'appareil Android, pas de bridage réseau, pas de Flutter dans le sandbox (1 vCPU / 2 Go).

**Tous les seuils restent EN ATTENTE, jamais déclarés tenus** : premier écran utile < 3 s en réseau lent, taille d'app, poids d'images, zéro plantage sur 20 e2e, Lighthouse mobile, couverture de la logique critique.

---

## 6. Décisions en attente

1. **Périmètre du manifeste PWA** — `scope: '/decouvrir'` : une app installée ne couvre pas `/activites` ni `/profil`. Élargir le scope sans exposer le back-office est un travail de manifeste à part entière.
2. **Route publique d'assistant** — Léa est séparée visuellement mais non fonctionnelle. Un nouveau contrat d'API est nécessaire (limite de débit, facturation des jetons, modération).
3. **B1/B2** — appareil Android, porte (c), **aucune dépense**.
4. **M2** — porte (b), attend L1 et le contrat L4. **Non touchée.**
5. **M4** — distribution testeurs, porte (c).
6. **`AuthSelfRegistrationTest`** (2/3) — `NotCommonPassword` retire les chiffres de fin, `password123456` → racine `password` → refusée. Antérieur à la règle de sécurité.
7. **`phpseclib` 4.0.2** — CVE GHSA-w3p9-g4p2-h3rr. Un bump de dépendance mérite sa propre PR.

---

## 7. Portes respectées

- **Une PR par lot, ouverte et jamais mergée** — 6 PR, 0 merge.
- **Aucun déploiement, aucun paiement, aucune dépense.**
- **Aucun Bounded Context ajouté** ; aucune duplication de messagerie, d'historique ni de compte.
- **Aucune ville, aucun pays, aucune catégorie codés en dur** — `approx_city` est un libellé, jamais une coordonnée, et ne restreint jamais un résultat.
- **Chaque test cité a réellement été exécuté** ; aucun seuil non mesuré n'est présenté comme tenu.
