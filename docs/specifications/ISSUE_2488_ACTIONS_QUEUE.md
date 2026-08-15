# Spec — Réduction des runs GitHub Actions redondants

## Contexte

L’issue #2488 constate une file GitHub Actions durablement saturée. L’audit du dépôt montre que les workflows lourds principaux possèdent déjà une section `concurrency`, mais que le scan hebdomadaire de l’historique secret est le seul workflow planifié sans groupe de concurrence.

## Objectif

Éviter que deux exécutions informatives du scan complet TruffleHog se consomment simultanément lorsque le workflow est déclenché manuellement pendant une exécution planifiée ou lorsqu’un déclenchement est rejoué.

## Décision

Ajouter au workflow `.github/workflows/secret-history-scan.yml` un groupe global propre au workflow avec `cancel-in-progress: true`. Le scan est explicitement non bloquant et informatif ; annuler une exécution obsolète au profit de la plus récente ne compromet ni un déploiement ni un contrôle requis.

Les workflows lourds déjà équipés de groupes de concurrence ne sont pas modifiés dans cette issue afin de ne pas changer les garanties de validation des branches principales. La vérification des quotas du compte GitHub et le choix éventuel de runners payants restent des opérations administratives hors dépôt.

## Critères d’acceptation

1. Le workflow secret-history-scan possède une section `concurrency`.
2. Deux exécutions simultanées du même workflow partagent un groupe unique et la plus récente annule l’ancienne.
3. Le workflow reste planifié hebdomadairement et déclenchable manuellement.
4. Aucun workflow de déploiement ou de validation requis n’est rendu annulable par cette modification.
5. Le lint YAML/actionlint et la garde de gouvernance passent.
