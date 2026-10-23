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
| **A** | 1 | Liée au conteneur, **aucun consommateur** | **RÉSOLUE** le 2026-10-23 : implémentée |
| **B** | 2 | Contrat mort assumé, documenté dans le provider | **RÉSOLUE** le 2026-10-23 : supprimées |
| **C** | 14 | Contrat d'écriture « contract-first », jamais consommé | **Supprimées** le 2026-10-23 (0 usage mesuré) |

**État final : 0 orphelin, allowlist vide, guard en mode bloquant (#8832).**

**Catégorie A — `GeofenceValidatorInterface` : RÉSOLUE (implémentation).**
`AttendanceServiceProvider` faisait `bind(GeofenceValidatorInterface::class,
AttendanceGeofenceService::class)`, mais `AttendanceGeofenceService`
n'implémentait **pas** l'interface : le binding se résolvait sans erreur tout en
ne produisant jamais un objet conforme au type déclaré. C'était le seul cas de
la liste qui soit un **vrai défaut**, pas une dette d'abstraction — un contrat
qui ment sur ce qui le satisfait.

Sortie retenue : **implémenter**. `evaluate(Company, Employee, ?float, ?float):
array` avait déjà exactement la signature du contrat ; ajouter `implements` ne
coûte rien et rend le binding vérifiable par le typeur. Supprimer le contrat
aurait aussi été correct, mais aurait laissé un `bind()` sans type de retour
contractuel — le provider continuerait de promettre une abstraction inexistante.

**Catégorie B — `AccessTokenServiceInterface`, `CameraRepositoryInterface` :
RÉSOLUE (suppression).** Documentés comme code mort dans le docblock de
`CamerasServiceProvider` (#7424). Sortie retenue : **supprimer**, pas réaligner.

Preuve, pour les deux : aucune implémentation, aucun `bind()`/`singleton()`, aucun
consommateur en production, en test, en route, en seeder ou en docblock hors du
fichier de définition. Les réaligner aurait produit des contrats que personne
n'appelle — exactement le code mort que cet ADR combat, et la conséquence déjà
tirée pour la catégorie C.

`AccessTokenServiceInterface` méritait un examen séparé : le service réel
`CameraStreamTokenService` **existe** et signe bien des jetons (JWT HS256). Mais
son `issue()` prend `(Camera $camera, int|string $actorEmployeeId): array`
quand le contrat promettait `(int $cameraId, int $employeeId, int $ttlSeconds):
string` — modèle de jeton entièrement différent. Le contrat n'a pas été «
presque aligné » : il décrivait une autre conception. Le jour où cette
abstraction sera réellement nécessaire, elle devra être écrite **à partir de**
`CameraStreamTokenService::issue()`, pas reprise du contrat antérieur qui l'a
contredit.

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
