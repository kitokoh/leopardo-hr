# Audit architecture technique — 2026-07-16

Statut: complete pour publication  
Auteur: audit externe KiloClaw (agent), a la demande de kitokoh  
Perimetre: architecture backend modulaire, RBAC/policies reelles, dependances composer, moteur paie multi-pays, secrets git.

Ce document est un audit technique complementaire a `AUDIT.md` (integrations tierces: FCM, Redis, email, Stripe/Chargily, Google OAuth, CI/CD).
Les tickets d'action issus de cet audit sont listes en fin de fichier et repris dans `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` sous les prefixes `PA2-SEC-*` et `PA2-ARCH-*`.

---

## 1. Dependances composer (`api/composer.lock`, 98 packages prod)

Recherche des advisories GitHub (`gh api /advisories`) sur les packages sensibles: `laravel/framework` (12.61.1), `laravel/sanctum` (4.3.1), `laravel/socialite` (5.27.0), `guzzlehttp/guzzle` (7.12.1), `symfony/http-foundation` + `symfony/http-kernel` (7.4.13), `dompdf/dompdf` (3.1.5), `predis/predis` (2.4.1), `firebase/php-jwt` (7.0.5), `league/flysystem` (3.34.0), `sentry/sentry` (4.25.0).

**Resultat : aucune CVE active sur les versions actuellement lockees.** Toutes les advisories trouvees concernent des plages de versions strictement inferieures a celles installees (ex. Laravel `< 12.61.1`, Guzzle `< 7.12.1`, Dompdf `< 2.0.4`, Symfony `< 7.4.12/7.4.13`). Le projet est a jour sur ce lot de dependances au 2026-07-16.

**CI deja en place** : `tests.yml` execute `composer audit --locked --no-dev`, et `.github/dependabot.yml` existe pour les mises a jour automatiques. Bon reflexe deja acquis — pas de nouveau ticket necessaire ici, juste vigilance continue (dependabot doit rester actif et ses PR doivent etre mergees, pas laissees en attente).

**Limite de cet audit** : verification faite sans `composer install` (pas de runtime PHP dans l'environnement d'audit) — verification par lecture directe de `composer.lock` + API GitHub Advisory, pas d'execution de `composer audit` locale. A recouper avec le run CI le plus recent sur `main`.

---

## 2. RBAC — Audit du code reel (Policies Laravel), pas seulement de la doc

`docs/security/RBAC_SYSTEM.md` annonce 6 roles dont **Department Manager** avec un scope explicitement documente "Department-only", et **Supervisor** avec un scope "Assigned-only".

Lecture de `app/Policies/*.php` (25 fichiers) et de `app/Core/Auth/Domain/Models/Employee.php` :

```php
public function isManager(): bool { return $this->role === 'manager'; }
public function hasManagerRole(string ...$roles): bool {
    if (! $this->isManager()) return false;
    if ($roles === []) return true;
    return in_array($this->manager_role, $roles, true);
}
```

`manager_role` est un enum `['principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing']` (`database/migrations/tenant/2026_04_01_000101_create_employees_table.php`).

### Constat critique

**Aucune policy ne filtre par `department_id` ou par `manager_id` pour les roles `dept` et `superviseur`.** Exemples verifies :

- `EmployeePolicy::viewAny()` → `return $actor->isManager();` — un manager avec `manager_role = 'dept'` voit **tous les employes de l'entreprise**, pas seulement son departement.
- `AttendancePolicy::viewAny()`, `SchedulePolicy::viewAny()`, `DepartmentPolicy::viewAny()`, `EvaluationPolicy::create()` → memes verifications `isManager()` plates, sans distinction de perimetre.
- Seuls `PayrollPolicy`, `ContractPolicy`, `ExpenseClaimPolicy::approve/reject` restreignent correctement a `hasManagerRole('principal', 'rh')` ou `'comptable'` — mais toujours **sans dimension departement**, seulement par type de role.
- Aucune occurrence de `department_id` ou de `manager_id` (chaine de management) dans un fichier de `app/Policies/`.

### Impact

Un chef de departement (`manager_role = 'dept'`) ou un superviseur (`manager_role = 'superviseur'`) obtient de facto les memes droits de lecture qu'un RH ou qu'un principal sur l'ensemble du perimetre RH/attendance/planning de l'entreprise, alors que le produit vend explicitement une isolation par departement (`RBAC_SYSTEM.md`, ligne "Department Manager ... Scope: Department-only"). Ce n'est pas une fuite cross-tenant (le scope `company_id` reste respecte partout via `BelongsToCompany`), mais c'est une **sur-permission interne** au sein d'un meme tenant — un chef d'equipe voit les salaires/contrats/evaluations d'employes qui ne sont pas les siens.

### Ce qui fonctionne bien

- Isolation tenant (`company_id`) systematiquement verifiee avant toute logique de role dans les policies qui manipulent un modele (`if ($x->company_id !== $actor->company_id) return false;`).
- Separation claire self-service vs management (`$actor->id === $target->id` pour consultation de son propre profil/absence/depense).
- Regle correcte et explicite empechant un RH de creer/promouvoir un `manager_role = principal` (`StoreEmployeeRequest`, `UpdateEmployeeRequest`) — bonne protection contre l'escalade de privileges horizontale.
- `TenantMiddleware` gere correctement la resolution multi-schema (isolation physique enterprise) avec validation du nom de schema (`isSafeSchemaName`) avant tout `SET search_path`, ce qui protege contre une injection SQL via le nom de schema.

---

## 3. Moteur de paie multi-pays (`Modules/Payroll/Infrastructure/Services/CountryRules/*`)

6 implementations pays : Algerie (DZ), Maroc (MA), Tunisie (TN), France (FR), Turquie (TR), Senegal (SN). Chacune fournit : devise, SMIG, cotisations sociales (salariale/patronale), baremes IR progressifs, calcul de charge.

### Points positifs

- Design propre : interface `CountryRulesInterface` + `AbstractCountryRules` avec `calculateProgressiveTax()` partage — pas de duplication de la logique de tranche entre pays.
- `PayrollCalculator` injecte les regles par `iterable<CountryRulesInterface>` (DI-friendly, testable, extensible sans toucher au calculateur).
- Taux et tranches plausibles et coherents avec les regimes reels (ex. CNAS DZ 9%/26%, CNSS MA avec plafond 6000 MAD, IPRES/CSS SN, SGK TR).

### Risques et manques

1. **Baremes fiscaux codes en dur (`taxSlabs()` retourne un tableau PHP statique)**, alors qu'un modele Eloquent `TaxSlab` existe deja en base (`company_id`, `country_code`, `effective_from`, `effective_to`...) et qu'un `TaxSlabController` permet meme de le gerer via API. **Les deux systemes coexistent sans lien** : `PayrollCalculator` n'interroge jamais `TaxSlab::` — grep confirme zero usage du modele dans `Infrastructure/Services/`. Consequence concrete : si un client Platform Admin met a jour un bareme via l'API `TaxSlabController` (ex. loi de finances 2027 en Algerie), **le calcul de paie continue d'utiliser l'ancien bareme codee en dur** sans que rien ne signale l'incoherence. C'est une bombe a retardement fonctionnelle, pas juste une dette technique.
2. **Pas de versionnement temporel des regles**: les classes `XxxPayrollRules` n'ont pas de notion de date d'effet. Un changement de taux CNAS/CNSS/SGK oblige a modifier le code et redeployer, sans pouvoir recalculer retroactivement un ancien cycle de paie avec l'ancien taux (necessaire pour un audit ou une regularisation).
3. **Zone CEMAC/CEDEAO/Canada annoncees dans `05_SCOPE_PAYS_PAIE_POINTAGE.md` (tickets `PA2-COUNTRY-007/008/009`) mais aucune classe `CountryRules` correspondante n'existe encore** dans le code — coherent avec le backlog existant (P1/P2, pas encore livre), pas une regression, juste confirmation que ces tickets restent a faire.
4. Aucun test ne verifie la coherence entre `TaxSlab` (DB) et `XxxPayrollRules` (code) — normal puisqu'ils ne communiquent pas, mais si le ticket ci-dessous est implemente, des tests de non-regression seront indispensables.

---

## 4. Secret Redis en clair — rappel de criticite (deja documente, toujours ouvert)

Confirme a nouveau lors de cet audit : `AUDIT.md` reference lui-meme un mot de passe Upstash reel commite dans l'historique git public (hostname et mot de passe retires de la documentation le 2026-07-19, voir `SECURITY_INCIDENT_REDIS_2026-07.md`), et la case correspondante dans sa propre checklist finale restait **non cochee** avant cette meme date. Aucune preuve dans le code ou les commits recents d'une rotation Upstash effectuee — cette action reste a faire manuellement (hors perimetre code). Ce point est repris ici en P0 dans le backlog ci-dessous parce qu'il n'a pas de ticket PA2 dedie a ce jour — seulement une note dans un fichier d'audit que personne n'est oblige de relire.

---

## 5. Couplage inter-modules (complement a l'audit d'architecture initial)

Confirmation par grep systematique : `HR` est importe directement par `Onboarding`, `Training`, `Recruitment`, `Cabinet`, `Platform` ; `Billing` est importe par `Growth`, `Payroll`, `Platform` ; `Planning` et `Absence` maintiennent des modeles domain dupliques (`Absence`, `AbsenceType`, `LeaveBalance` existent identiquement dans les deux modules). Le ticket `PA2-ARCH-002` ci-dessous couvre la clarification de propriete de ces modeles.

---

## 6. Backlog d'action — nouveaux tickets PLAN_ACTION2

Format identique a `02_BACKLOG_ATOMIQUE.md`. A copier dans ce fichier et dans `03_GITHUB_PROJECT_IMPORT.csv` (deja fait dans ce commit).

### Securite — P0/P1

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-SEC-001 | P0 | Rotation secret Redis Upstash expose en historique git | Infra/Render | mot de passe Upstash tourne, `REDIS_URL`/`REDIS_PASSWORD` mis a jour sur Render, ancien secret invalide verifie par tentative de connexion |
| PA2-SEC-002 | P0 | RBAC scope departement reel pour manager_role=dept | API/Policies | EmployeePolicy, AttendancePolicy, SchedulePolicy, EvaluationPolicy, DepartmentPolicy filtrent par `department_id`/`manager_id` quand `manager_role=dept`; tests dedies |
| PA2-SEC-003 | P1 | RBAC scope superviseur "assigned-only" reel | API/Policies | `manager_role=superviseur` limite a une liste explicite d'employes/departements assignes, pas company-wide |
| PA2-SEC-004 | P1 | Tests de regression RBAC par role manager_role | API tests | matrice de tests couvrant principal/rh/dept/comptable/superviseur/marketing sur chaque policy existante |
| PA2-SEC-005 | P2 | Documentation RBAC alignee code | docs | `RBAC_SYSTEM.md` reflete l'etat reel du scope (dept/superviseur) apres correction, pas avant |

### Architecture — P1/P2

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-ARCH-001 | P1 | Brancher TaxSlab/SocialContribution DB dans PayrollCalculator | API/Payroll | le calcul utilise les baremes DB si presents pour le tenant/pays, fallback code en dur documente sinon; tests avant/apres changement de bareme |
| PA2-ARCH-002 | P1 | Clarifier proprietaire canonique Absence/Planning | API | un seul module proprietaire des modeles Absence/AbsenceType/LeaveBalance, l'autre consomme via event/contrat, doublons supprimes |
| PA2-ARCH-003 | P2 | Reduire couplage direct HR <-> autres modules | API | dependances HR->Onboarding/Training/Recruitment/Cabinet passees par evenements ou contrats d'interface explicites, mesure avant/apres (nombre d'imports directs) |
| PA2-ARCH-004 | P2 | Versionnement temporel des regles pays paie | API/Payroll | taux/baremes pays associes a une date d'effet, recalcul retroactif possible pour audit |
| PA2-ARCH-005 | P2 | Reduire baseline PHPStan (3914 lignes ignorees) | API | plan de reduction par module, suivi du delta a chaque PR touchant un module ancien |

---

## 7. Recapitulatif executif

| Domaine | Etat | Severite |
|---|---|---|
| Dependances composer | A jour, aucune CVE active | OK |
| CI securite (CodeQL/ZAP/secret-scan/dependabot) | En place et actif | OK |
| RBAC company/tenant isolation | Correct | OK |
| RBAC scope departement/superviseur | Non implemente malgre la doc produit | Eleve |
| Secret Redis historique git | Toujours expose, non tourne | Critique |
| Moteur paie — calculs par pays | Coherent et teste | OK |
| Moteur paie — DB TaxSlab non branchee | Incoherence silencieuse possible | Moyen-eleve |
| Couplage inter-modules | Reel malgre ADR annoncant l'inverse | Moyen |
