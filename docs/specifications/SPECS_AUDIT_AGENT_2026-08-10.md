# Spécifications — Audit Agent 2026-08-10

> Issues à créer à partir de l'audit `docs/audits/AUDIT_AGENT_2026-08-10.md`.
> Chaque spec est autonome : contexte, périmètre, critères d'acceptation.

## A-1 — [Qualité][P1] Audit du pattern PendingCommand lazy dans les tests

**Contexte** : `PendingCommand` exécute la commande au `__destruct` — les tests qui
chaînent `$this->artisan(...)->assertExitCode(0)` puis vérifient l'état de la base
(ou d'un fichier) constatent un état **avant** exécution : la commande n'a pas
encore tourné. Les tests peuvent être « verts » sans rien vérifier (le PendingCommand
lancé au destruct exécute alors la commande après les assertions, et les
expectations de sortie sont vérifiées au destruct — le test échoue seulement si
l'output ne correspond pas).

**Périmètre**
1. Auditer tous les usages de `$this->artisan()` dans `api/tests/` (grep + revue
   manuelle des assertions DB/fichier post-commande).
2. Corriger les tests concernés : `$cmd->run()` explicite avant les assertions
   (pattern validé S-1/S-2), ou restructuration.
3. Documenter le pattern dans `docs/GESTION_PROJET/CONVENTIONS_TESTS.md` (créer si absent).

**Critères d'acceptation** : 0 usage non audité ; tests corrigés verts ; convention documentée.

## A-2 — [Sécurité][P2] Alerte « secret exposé dans l'historique » automatisée

**Contexte** : les secrets réels (Redis Upstash, Neon) restent dans l'historique
git public (#1472/#1601) ; TruffleHog ne signale que les NOUVEAUX commits. Tant
que la purge historique n'est pas faite (action humaine), aucun garde-fou
n'existe côté CI.

**Périmètre**
1. Ajouter au secret-scan un scan périodique de l'historique complet
   (`--since-commit` sur la racine, workflow hebdo) qui signale les patterns
   réels connus sans bloquer les PR (job d'information).
2. Documenter l'état dans `docs/security/HISTORIQUE_SECRETS.md` : liste des
   secrets exposés, date, gravité, plan de purge.

**Critères d'acceptation** : workflow hebdo vert (ou signalant uniquement les
secrets connus) ; doc à jour.

## A-3 — [Process][P3] Fermer les PR/duplications agent-bot

**Contexte** : deux implémentations parallèles des mêmes specs (agent + bot).
À la fusion, une seule PR par spec doit rester ; les branches orphelines
supprimées.

**Périmètre**
1. Matrice des PR par spec (titre, branche, contenu, CI).
2. Fermeture des PR redondantes avec commentaire de référence vers la PR retenue.
3. Suppression des branches orphelines après fusion.

**Critères d'acceptation** : une seule PR par spec ; 0 branche orpheline ; main propre.

## A-4 — [Qualité][P2] Ratchet du coverage global 60 → 65 %

**Contexte** : `BACKEND_COVERAGE_MIN` est à 60 %, cible 65 % documentée dans
coverage-gate.yml, sans issue de suivi.

**Périmètre**
1. Mesurer le coverage global actuel (pcov, suite complète).
2. Passer la variable à 65 % une fois le palier atteint (ou plan de tests pour l'atteindre).
3. Suivre dans une issue avec le chiffre avant/après.

**Critères d'acceptation** : gate verte à 65 % ; issue de suivi créée.

## A-5 — [Security][P2] Rotation périodique des jetons d'accès agent/bot

**Contexte** : le bot et les agents utilisent des PAT GitHub à large portée
(`repo`, `workflow`). Aucune rotation documentée.

**Périmètre**
1. Documenter la politique de rotation (fréquence, qui, comment) dans
   `docs/CI_CD_SECRETS.md`.
2. Lister les PAT actifs dans le registre des secrets (sans les valeurs).

**Critères d'acceptation** : politique documentée ; registre à jour.
