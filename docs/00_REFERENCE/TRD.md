# TRD — Technical Requirements Document

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444

## 1. Objet

Exigences techniques de la plateforme Leopardo. Chaque exigence porte un
identifiant (`TRD-<domaine>-<n>`) référençable dans les issues et les tests.
Le détail métier par module reste dans `docs/dossierdeConception/05_regles_metier/`.

## 2. Exigences fonctionnelles transversales

### 2.1 Tenants & provisioning

| ID | Exigence |
|---|---|
| TRD-TEN-001 | Tout utilisateur peut créer un compte et terminer un onboarding guidé (interview de configuration) qui aboutit à un tenant provisionné. |
| TRD-TEN-002 | Le provisioning est idempotent, rejouable, audité, fail-closed, tenant-safe, observable et réversible. |
| TRD-TEN-003 | Le tenant choisit son profil métier (restaurateur, station-service, retail, santé, éducation, travel, VTC…) ; seules les solutions correspondantes sont activées. |
| TRD-TEN-004 | À la fin de l'onboarding, le tenant dispose d'une checklist de mise en service : établissement, employés, rôles/tâches, produits, apps (#8451). |
| TRD-TEN-005 | Un tenant multi-sites gère plusieurs établissements (restaurants, stations…) avec rotations des employés entre sites (#8450). |

### 2.2 Identité, rôles, permissions

| ID | Exigence |
|---|---|
| TRD-IAM-001 | RBAC à deux étages : rôles plateforme (super-admin) et rôles tenant installés par solution. |
| TRD-IAM-002 | Le gérant crée des employés, leur attribue rôles et tâches, et suit leur activité. |
| TRD-IAM-003 | Toute permission non validée reste désactivée (fail-closed). |
| TRD-IAM-004 | Email globalement unique pour les employés (contrainte enforced en base). |

### 2.3 Modules métier transversaux

HR, Attendance (pointage biométrique ZKTeco inclus), Payroll, CRM client,
Marketing client, Accounting, Documents, Notifications, Planning, Expense,
Recruitment, Communication — activés pour toutes les verticales selon
l'abonnement.

### 2.4 Solutions verticales

Chaque solution fournit ses workflows propres. Référence restaurateur
(exemple de la mission) :

| ID | Exigence |
|---|---|
| TRD-RES-001 | Le restaurateur crée son restaurant (référentiel, équipe, stock, réservations, livraison, vente en ligne, paiements, rapports). |
| TRD-RES-002 | Il gère ses produits (plats, menus, ingrédients) et leur disponibilité. |
| TRD-RES-003 | Il planifie les rotations de ses équipes, y compris multi-restaurants. |
| TRD-RES-004 | Ses employés accèdent à l'app mobile serveur (prise de commande). |
| TRD-RES-005 | La cuisine dispose d'un écran/app KDS offline-first (#8448). |
| TRD-RES-006 | Le POS encaisse hors-ligne et synchronise au retour réseau (#8449). |

Exigences équivalentes par verticale : stations (pompes, shifts, caisses,
ventes, incidents) pour FuelStation ; élèves/classes/notes pour EduManager ;
etc. — cf. spec plateforme et dossiers de conception.

### 2.5 Apps & distribution

| ID | Exigence |
|---|---|
| TRD-APP-001 | Le tenant accède depuis son espace à toutes les apps de son métier : consultation, téléchargement, installation (#8447). |
| TRD-APP-002 | L'espace « Mon Logiciel » ne montre que les apps des solutions actives du tenant. |
| TRD-APP-003 | Les apps critiques du service (serveur, cuisine, POS) fonctionnent en local sans internet via le bridge Edge. |

## 3. Exigences non-fonctionnelles

### 3.1 Offline & résilience

| ID | Exigence |
|---|---|
| TRD-NFR-OFF-001 | Le service en cours (pointage, vente, cuisine) ne s'interrompt pas en cas de coupure internet. |
| TRD-NFR-OFF-002 | Toute sync est idempotente : zéro perte, zéro doublon après resynchronisation. |
| TRD-NFR-OFF-003 | L'état de connexion et de sync est visible dans l'UI. |

### 3.2 Internationalisation & accessibilité

| ID | Exigence |
|---|---|
| TRD-NFR-I18N-001 | 4 langues partout (fr, en, ar, tr) — API, web, mobile — dont RTL arabe. |
| TRD-NFR-I18N-002 | Zéro chaîne en dur dans le texte visible (garde CI PA2-I18N-014). |
| TRD-NFR-A11Y-001 | Contrastes AA, navigation clavier, rôles ARIA sur les composants interactifs. |

### 3.3 Sécurité & conformité

| ID | Exigence |
|---|---|
| TRD-NFR-SEC-001 | Tenant résolu côté serveur à chaque requête ; jamais de confiance en un `company_id` client. |
| TRD-NFR-SEC-002 | Audit trail des actions sensibles avec correlation ID, sans fuite de secrets. |
| TRD-NFR-SEC-003 | Scans secrets/Trivy en CI ; pre-commit hooks actifs. |
| TRD-NFR-RGPD-001 | Registre des traitements maintenu ; purge tenant complète et classifiée. |

### 3.4 Qualité & performance

| ID | Exigence |
|---|---|
| TRD-NFR-QA-001 | PHPStan strict, tests unitaires + fonctionnels par module ; CI verte obligatoire avant merge. |
| TRD-NFR-QA-002 | Couverture E2E des parcours critiques (onboarding → espace configuré). |
| TRD-NFR-PERF-001 | Pages dashboard < 2,5 s en 4G ; apps mobiles démarrage à froid < 3 s sur appareil milieu de gamme. |
| TRD-NFR-OBS-001 | Durée, statut, retries et erreurs du provisioning mesurables par correlation ID. |

## 4. Contraintes techniques

- PHP 8.4 / Laravel — modules DDD uniquement (couches legacy supprimées).
- Next.js 16 (App Router) pour les fronts web ; Vue 3 pour le super-admin ;
  Flutter pour le mobile (package partagé `leopardo_core`).
- Migrations exclusives via `php artisan leopardo:migrate`.
- OpenAPI : `api/openapi.yaml` canonique, miroir généré vérifié en CI.
- `main` protégé : PR + checks + CODEOWNERS + entrée CHANGELOG.

## 5. Critères d'acceptation de la mission « espace tenant complet »

Pour tout profil métier, le parcours suivant doit fonctionner de bout en
bout (recette E2E) :

1. Créer un compte → suivre l'onboarding → obtenir un tenant provisionné.
2. Créer son établissement (et ses sites si multi-sites).
3. Ajouter des employés, leurs rôles et leurs tâches ; suivre leur activité.
4. Ajouter ses produits/prestations.
5. Installer les apps de son métier depuis « Mon Logiciel ».
6. Opérer hors-ligne (service, pointage, encaissement) puis resynchroniser.

Traçabilité : #8444 (docs), #8445 (marketing), #8446 (branding),
#8447 (Mon Logiciel), #8448 (KDS), #8449 (POS offline), #8450 (rotations),
#8451 (checklists onboarding).
