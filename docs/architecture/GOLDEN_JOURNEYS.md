# Golden journeys end-to-end

> **Issue :** [MAT-013 #5871](https://github.com/kitokoh/leopardo-hr/issues/5871)
> **Registre :** `dev-hub/tools/golden-journeys.json`
> **Garde CI :** `dev-hub/tools/check-golden-journeys.sh` (job Hygiene Guards)
> **Tests :** `dev-hub/tools/tests/check-golden-journeys.test.sh` (5 scénarios)

## Objectif

Les parcours métier critiques (golden journeys) sont versionnés et vérifiés :
chaque étape référence une route API qui existe réellement dans
`api/routes/**` (résolution des préfixes de groupes, paramètres `{x}` ignorés).
Un endpoint supprimé ou déplacé casse la CI au lieu de casser silencieusement
un parcours utilisateur.

## Parcours couverts

| ID | Parcours | Solution |
|---|---|---|
| GJ-01 | Onboarding employé complet (tenant → employé → pointage → soldes) | HR core |
| GJ-02 | Cycle de congé (demande → approbation → solde) | HR core |
| GJ-03 | Cycle de paie (run → calcul → validation → bulletin) | HR core |
| GJ-04 | Clôture comptable (journal → clôture → FEC) | HR core |
| GJ-05 | Pipeline CRM client (import → commit → conversion lead) | CRM client |
| GJ-06 | Pilote FuelStation (activation → station → pompe → relevé → vente) | FuelStation (planifié) |
| GJ-07 | Pilote EduManager (activation → campus → classe → élève → présence) | EduManager (planifié) |

Les parcours des solutions **planifiées** documentent la cible ; ils deviennent
vérifiés à l'activation de la solution (passage `status: active`).

## Règles

1. Chaque journey a un `id` unique, une `solution` connue, un critère
   `acceptance` et des `steps` ordonnées (méthode + route API + rôle).
2. Chaque étape d'une solution active doit exister dans les fichiers de routes
   (`api/routes/**`) — le garde résout les préfixes de groupes.
3. Chaque solution active doit avoir au moins un journey.
4. Un changement d'API (déplacement/renommage de route) met à jour le registre
   dans la même PR.

## Exécution locale

```bash
bash dev-hub/tools/check-golden-journeys.sh api
bash dev-hub/tools/tests/check-golden-journeys.test.sh
```

## Rollback

- Revert du commit du registre/garde ; scripts bash autonomes sans état.
