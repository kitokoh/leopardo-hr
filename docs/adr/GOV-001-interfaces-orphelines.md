# ADR GOV-001 — Interfaces de contrat orphelines : tolerance explicite et garde bloquante

- **Statut** : accepté
- **Date** : 2026-10-23
- **Périmètre** : `api/app/Modules/*/Domain/Contracts/`
- **Issues** : #1492 (garde `check-orphan-interfaces.sh`), #3596 (anti-staleness des allowlists)
- **Garde CI** : `.github/workflows/route-owner-and-orphan-interfaces-guard.yml`

## Contexte

Chaque module expose ses contrats dans `Domain/Contracts/`. Une interface de
contrat qui n'a **aucune** classe `implements` est du code mort : elle promet
une abstraction que rien ne tient, elle gonfle la surface publique du module, et
elle donne l'illusion d'une frontière architecturale inexistante.

`dev-hub/tools/check-orphan-interfaces.sh` (issue #1492) détecte ces orphelins
mais **n'était exécuté par aucun workflow** : la dette restait invisible en CI.
Constat au 2026-10-23 sur `main` @ `94e2fdf` : **17 orphelins**.

Le script portait par ailleurs deux défauts bloquants pour un usage en CI :

1. **Allowlist codée en dur** dans le script (`DEFAULT_ALLOW`), sans
   justification ni date — contraire à la convention du dépôt
   (`actions-convention-allowlist.txt`, allowlists openapi) qui exige un fichier
   externe, justifié et purgeable.
2. **Faux positifs silencieux si `ripgrep` manque** : sans `rg`, la recherche
   d'implémentation ne retourne rien, donc *toutes* les interfaces ressortent
   orphelines. Mesuré dans un environnement sans `rg` : 51 « orphelins » annoncés
   au lieu de 17, dont `CountryRulesInterface` pourtant implémentée par
   `app/Services/Payroll/CountryRules`. Un garde qui crie au loup sur 34 faux
   positifs est un garde qu'on désactive.

## Décision

1. **L'allowlist devient un fichier versionné** —
   `dev-hub/tools/orphan-interfaces-allowlist.txt`, une interface par ligne,
   `#` pour les commentaires, **une justification par catégorie**, et
   l'obligation de purger une entrée dès que l'interface est implémentée
   (anti-staleness, #3596 — même règle que `check-actions-convention.sh`).
2. **Le garde échoue si `ripgrep` est absent** au lieu de produire des faux
   positifs. Le workflow installe `ripgrep` explicitement.
3. **Le garde devient BLOQUANT** dans un workflow dédié. La dette étant nulle
   au sens du garde (17 orphelins tous allowlistés, 0 nouveau), il n'y a pas de
   phase « non bloquante » à prévoir : toute interface orpheline nouvelle casse
   la CI, et toute entrée d'allowlist devenue inutile aussi.
4. `--strict` reste disponible pour **mesurer la dette réelle** (17 au
   2026-10-23) sans allowlist.

## Catégorisation de la dette (17 entrées)

| Cat. | Nb | Nature | Sortie attendue |
|---|---|---|---|
| **A** | 1 | Liée au conteneur, **aucun consommateur** | Supprimer le contrat ou consommer le binding |
| **B** | 2 | Contrat mort assumé, documenté dans le provider | Réaligner sur l'implémentation réelle, ou retirer + baseline PHPStan |
| **C** | 14 | Contrat d'écriture « contract-first », jamais consommé | **Supprimées** le 2026-10-23 (0 usage mesuré) |

**Catégorie A — `GeofenceValidatorInterface`.** `AttendanceServiceProvider`
fait `bind(GeofenceValidatorInterface::class, AttendanceGeofenceService::class)`,
mais `rg GeofenceValidatorInterface api/app api/tests` ne trouve **aucun**
consommateur : le binding est résolu par personne, et `AttendanceGeofenceService`
n'implémente même pas l'interface (héritage absent — le binding ne produirait
donc pas un objet conforme si quelqu'un le résolvait). Coût à chaque requête,
valeur nulle. **C'est le seul cas de cette liste qui est un vrai défaut, pas une
dette d'abstraction.** Recommandation : `AttendanceGeofenceService implements
GeofenceValidatorInterface` (les signatures `evaluate(Company, Employee, ?float,
?float): array` concordent) puis consommer le contrat — ou supprimer les deux.

**Catégorie B — `AccessTokenServiceInterface`, `CameraRepositoryInterface`.**
Documentés comme code mort dans le docblock de `CamerasServiceProvider` (#7424) :
les signatures du premier ne correspondent pas à `CameraStreamTokenService`, le
second décrit un modèle `object`/`int $id` que les modèles réels (UUID,
`Camera`) ne suivent pas. Les lier reviendrait à **affirmer une architecture qui
n'existe pas**. Leur réalignement touche la baseline PHPStan Strict : travail à
part, hors périmètre du garde.

**Catégorie C — 14 repositories : RÉSOLUE le 2026-10-23 (suppression).**
Contrats écrits avant leur implémentation. Le plan de sortie prévoyait
« implémenter (le service concret existe souvent déjà) ou supprimer le
contrat ». La vérification par module a tranché : **supprimer**, sur preuve.

Preuve, pour chacune des 14 : `rg <Interface>` sur `api/app`, `api/tests`,
`api/routes`, `api/database`, `api/config` ne retourne **aucune** occurrence
hors du fichier de définition. Pas d'`implements`, pas de `bind()`/`singleton()`
en provider, pas d'injection en constructeur, pas de résolution `app()`, pas de
type-hint, pas de mention en test ni en docblock. Les services concrets du
module existent bien (`EmployeeService`, `NotificationDispatcher`,
`OnboardingQrService`…) mais **aucun ne correspond aux signatures** : par
exemple `CompanyProvisioningInterface::provision(int $companyId, string $plan)`
face à `CompanyProvisioningService::provisionSharedCompany(array $payload,
SuperAdmin $superAdmin)` — des signatures différentes, donc pas d'interface à
câbler, mais deux abstractions concurrentes. Idem
`EmployeeRepositoryInterface::save(Employee)` face à `EmployeeService::create`.
Les implémenter « pour ne pas les perdre » aurait donc imposé d'**écrire de
nouvelles implémentations sans consommateur** — exactement le code mort que cet
ADR combat.

Supprimées (service + baseline PHPStan Strict nettoyée des 3 entrées
correspondantes) : `AttendanceRepositoryInterface`, `InvoiceRepositoryInterface`,
`SubscriptionRepositoryInterface`, `DocumentRepositoryInterface`,
`FolderRepositoryInterface`, `PartnerRepositoryInterface`,
`ContractRepositoryInterface`, `DepartmentRepositoryInterface`,
`EmployeeRepositoryInterface`, `NotificationRepositoryInterface`,
`OnboardingRepositoryInterface`, `PlanningRepositoryInterface`,
`CompanyProvisioningInterface`, `JobPostingRepositoryInterface`.

**Verdict** : catégorie C close, allowlist ramenée de 17 à 3 entrées
(A1 + B2). Vérifié : `check-orphan-interfaces.sh` → `3 orphelin(s) connu(s)
(allowlist) — 0 nouveau, OK` (exit 0) ; PHPStan Strict level 8 : `[OK] No
errors` sur les 5 modules touchés. Détail : `docs/adr/GOV-002-montee-phpstan-2.3.md`
pour le contexte de la montée d'analyseur qui a motivé ce nettoyage.

## Conséquences

- Toute nouvelle interface sans implémentation **casse la CI** — c'est le but :
  on écrit un contrat quand on a un consommateur, pas avant.
- Une PR qui implémente une interface doit **purger sa ligne d'allowlist** dans
  la même PR (sinon rouge) : la liste ne peut pas pourrir.
- La dette reste **visible et datée** : elle n'est plus enfouie dans un
  `DEFAULT_ALLOW` en dur.
- Le passage en catégorie « résolu » se fait par module, sans coordination
  globale.

## Références

- `dev-hub/tools/check-orphan-interfaces.sh`
- `dev-hub/tools/orphan-interfaces-allowlist.txt`
- `.github/workflows/route-owner-and-orphan-interfaces-guard.yml`
- `docs/adr/` (format des ADR du dépôt)
