# ADR-0031 — Le module `Absence` reste une façade HTTP, il ne redevient pas un bounded context

- **Statut** : Accepté
- **Date** : 2026-10-28
- **Contexte** : issue #7959 (audit structurel 2026-09-20), point 2
- **Décideur** : agent d'implémentation, au titre de la délégation fondateur/PM

## Contexte

L'audit #7959 relève que `api/app/Modules/Absence/` n'a ni `Application/`, ni
`Infrastructure/`, ni `Domain/Models` : 488 lignes au total, dont un
`AbsenceController` qui importe directement quatre Actions de **Planning**
(`CreateAbsence`, `ApproveAbsence`, `RejectAbsence`, `CancelAbsence`,
`UpdateAbsence`) et le modèle `Planning\Domain\Models\Absence`.

L'audit en conclut que « le module annoncé n'existe pas réellement » et demande
de trancher :

> « Trancher `Absence` : rapatrier le domaine dans le module, ou le déclarer
> sous-domaine de Planning et supprimer le faux module. »

## Décision

**Le module `Absence` est conservé en l'état : façade HTTP assumée, sans
domaine propre.** Le domaine absence/congé appartient à **Planning**, qui en
est le propriétaire canonique.

Concrètement :

1. `Absence` reste un dossier de `Modules/` contenant uniquement
   `Interfaces/` (contrôleur, requests) — c'est-à-dire **le nom sous lequel
   l'API publique du domaine est exposée**.
2. Aucun modèle, service ou Action métier n'est ajouté dans
   `Modules/Absence/`. Toute nouvelle règle métier d'absence va dans
   `Modules/Planning/`.
3. La dépendance `Absence → Planning` est déclarée au registre des bounded
   contexts et à la matrice de dépendances (`MAT-001`/`MAT-002`), comme
   n'importe quelle arête autorisée.
4. Le `AbsenceServiceProvider` documente l'arrangement ; c'est déjà le cas
   (« PA2-ARCH-002 : ce module n'est qu'une façade HTTP »).

## Justification

### Pourquoi ne pas rapatrier le domaine dans `Absence`

- **Le domaine est déjà cohérent dans Planning.** `Absence`, `AbsenceType`,
  `LeaveBalance`, `LeaveBalanceLog` et `AbsenceService` y vivent, à côté du
  planning d'équipe et des règles de congés qui les consomment. Les déplacer
  pour satisfaire une symétrie de dossiers casserait cette cohésion et
  toucherait plusieurs centaines d'imports, sans changer un seul comportement.
- **`Absence` n'a pas de modèle de données propre.** Une absence *est* un
  objet de planning : ses dates, son type, son impact sur les soldes sont des
  concepts de Planning. Un BC « Absence » ne serait pas un sous-système, ce
  serait un synonyme.
- **Le vocabulaire du projet va déjà dans ce sens.** Les Actions sont
  préfixées `Planning\Application\Actions\*Absence`, et le `README` de
  `Modules/Absence` décrit une façade.

### Pourquoi ne pas supprimer le module

- **Il porte une surface publique stable.** `/api/v1/absences/*` est
  consommée par les apps mobiles ; le contrôleur, les `Request` de validation
  et les règles d'autorisation (qui peut voir/approuver quelle absence) sont
  des responsabilités d'**interface**, pas de domaine. Les déplacer dans
  Planning ferait grossir le contrôleur de Planning et rendrait la frontière
  plus floue, pas plus nette.
- **La séparation interface/domaine est précisément ce que le schéma
  hexagonal du projet cherche à obtenir.** Un module qui ne contient que
  `Interfaces/` n'est pas « creux » : c'est un **adaptateur d'entrée** isolé.
  Le défaut serait qu'il contienne un domaine partiel et divergent — ce qui
  n'est pas le cas.
- **Supprimer un dossier de module est un changement cassant pour les
  namespaces** (`App\Modules\Absence\...` apparaît dans les routes et les
  tests) sans bénéfice fonctionnel.

### Ce qui rend la situation acceptable plutôt que subie

La crainte légitime de l'audit est qu'un « faux module » devienne un prétexte
pour dupliquer des modèles. Trois gardes déjà en place l'empêchent :

- `check-module-isolation.sh` (#5584/#8059) bloque tout **nouvel** import
  croisé hors allowlist — y compris depuis/vers `Absence`.
- `check-bounded-context-dependencies.sh` (MAT-002/#5860) exige que chaque
  arête soit déclarée dans `dev-hub/governance/bounded-context-dependencies.json`.
- `check-layer-purity.sh` (#6568) interdit les facades Laravel depuis
  `Application/` — un domaine qui pousserait dans `Absence/Application/`
  devrait respecter les règles de couche.

## Conséquences

- **Positif** : aucune migration de code risquée ; la frontière est
  **documentée** au lieu d'être un accident ; l'API publique ne bouge pas.
- **Positif** : les futures revues n'ont plus à rouvrir la question — l'ADR
  fait autorité.
- **Négatif assumé** : l'arborescence n'est pas « uniforme » ; un lecteur qui
  s'attend à ce que chaque `Modules/X` contienne un domaine sera surpris.
  L'ADR et le docblock du provider le disent explicitement.
- **Négatif assumé** : `Absence → Planning` reste un import cross-module
  toléré (il est dans l'allowlist). Il est **sémantiquement correct** : la
  façade dépend de son domaine, jamais l'inverse (`Planning` n'importe rien
  de `Absence`). Un couplage unidirectionnel interface→domaine n'est pas une
  violation d'isolation, c'est la définition d'un adaptateur.

## Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Déplacer le domaine dans `Absence` | Détruit la cohésion de Planning, ~centaines d'imports touchés, zéro bénéfice fonctionnel |
| Supprimer `Modules/Absence`, routes dans `rh.php` | Perte de lisibilité de la surface API ; grossit un fichier de routes déjà large ; changement cassant de namespaces |
| Fusionner `Absence` et `Planning` en un seul module | `Planning` deviendrait encore plus gros ; le découpage des surfaces HTTP est utile aux consommateurs |
| Créer un BC `Leave` distinct | Inventerait un sous-système qui n'existe pas dans le métier ; le congé est un attribut du planning |

## Références

- Issue #7959 — audit structurel 2026-09-20
- `api/app/Modules/Absence/Providers/AbsenceServiceProvider.php` — docblock PA2-ARCH-002
- `api/ARCHITECTURE.md` — sections « Interdictions strictes », « Modules »
- `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` — registre MAT-001
- ADR-0018 (frontières CRM), ADR-0027 (frontière Notification/Communication) — précédents de frontière assumée
