# Plan de remédiation des échecs préexistants (CI non requise)

**Portée :** job `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` et jobs Flutter de
`mobile-apps-ci.yml`. Ces jobs **ne sont pas** dans `required_status_checks`
(`dev-hub/tools/branch-protection-canonical.json` : `phpstan-strict`,
`module-structure-check`, `frontend-lint`, `actionlint`), donc ils ne bloquent
aucun merge — mais un rouge permanent détruit leur valeur de signal : une
régression réelle ne se distingue plus d'un bruit de fond.

**Règle directrice :** *on ne répare pas un module qu'on ne touche pas.* Un
module n'est remédié que si (a) la PR en cours le modifie, ou (b) il fait partie
d'une vague dédiée planifiée ci-dessous. Aucune PR fonctionnelle ne doit être
bloquée par cette dette.

## Méthode de comparaison avec `main`

Toute mesure d'échec doit être **différentielle**, jamais absolue :

1. Worktree propre sur `origin/main` (`git worktree add /tmp/base origin/main`),
   aucune modification locale, même `CI=true`.
2. Invocation identique à la CI (mêmes variables d'environnement, même base
   PostgreSQL, même image PHP).
3. Comparer la **liste numérotée** des tests en échec (`--log-junit`), pas
   seulement les totaux : un total stable peut cacher un échec remplacé par un
   autre.
4. Un échec n'est « préexistant » que s'il est présent dans la liste de `main`
   **avant** le diff. Sinon c'est une régression et la PR est concernée.

## Modules en échec — état et priorité

| Module | Nature dominante | Priorité | Effort | Statut |
|---|---|---|---|---|
| Flutter mobile (`sentry_flutter` ^9 vs ^10) | Conflit de dépendance bloquant **avant** toute analyse | **P1** | S | **Corrigé** (PR dédiée, #8677/#8599) |
| Restaurant | Fixtures et assertions obsolètes après refonte RBAC | P2 | M | À planifier |
| Solutions | Migrations de test hors parité avec `CreatesMvpSchema` | P2 | M | À planifier |
| Quiz | Assertions de contenu obsolètes (libellés) | P3 | S | À planifier |
| Notifications | Horodatage/ordre des messages non déterministe | P3 | M | À planifier |
| Comptes / transerve­rsal | Environnement (`max_locks_per_transaction`) | **P1** | S | Infra, hors code |

### Détail des deux entrées P1

**1. Flutter — conflit `sentry_flutter`.** `leopardo_core` déclarait
`^10.0.0` tandis que `leopardo_employee`, `leopardo_hr`, `leopardo_manager` et
`leopardo_platform_admin` restaient en `^9.29.0`/`^9.30.1`. `flutter pub get`
échouait avec `version solving failed` **avant** l'analyse, ce qui faisait
tomber en cascade `Analyze leopardo_*` **et** `Desktop verify leopardo_*` sur
`main` et sur **toutes** les branches. Correction : aligner les quatre
`pubspec.yaml` sur la contrainte du noyau. Aucun code applicatif touché.

**2. PostgreSQL — `max_locks_per_transaction`.** Le job Backend redémarre
PostgreSQL pour appliquer `max_locks_per_transaction=512` et échoue avec
`postgres n'est pas redevenu prêt après redémarrage (60 s)`. C'est un
**problème d'infrastructure de CI**, pas de code : tant qu'il n'est pas réglé,
le job Backend ne peut pas rendre de verdict fiable, et tout diagnostic de test
backend est biaisé. À traiter en priorité, car il masque les échecs réels des
modules ci-dessus.

## Ordre de remédiation

1. **P1 — Flutter** : alignement des dépendances. Fait.
2. **P1 — PostgreSQL CI** : fiabiliser le redémarrage (healthcheck explicite,
   attente active plutôt qu'un `sleep` fixe, message de diagnostic conservé).
3. **P2 — Restaurant** : après le lot RBAC ressource-scopé, reprendre les
   fixtures une par une ; mesurer en différentiel à chaque étape.
4. **P2 — Solutions** : aligner les migrations de test sur `CreatesMvpSchema`
   (garde de parité #5443 déjà en place).
5. **P3 — Quiz, Notifications** : nettoyage d'assertions, groupé, sans urgence.

## Ce qui n'entre pas dans ce plan

- Les échecs **requis** (les 4 checks de protection) : ils bloquent, ils sont
  traités dans la PR concernée.
- Les **contradictions internes prouvées** (deux assertions mutuellement
  exclusives pour la même opération, ex. #8578) : elles exigent un arbitrage
  produit, pas un correctif de test. Elles restent rattachées à leur issue.
