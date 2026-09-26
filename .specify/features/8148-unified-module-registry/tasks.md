# Tâches — Registre unifié modules/features/solutions (#8148 / BOS-010)

## Tâches de la présente issue (livrable documentaire)

- [x] 1. Audit des 3 sources de vérité + mesure des désynchronisations vivantes (`fleet`, `ai_cloud_allowed`) — preuves en spec §1.
- [x] 2. `spec.md` — modèle du registre, dual-read, test de parité, kill switch, metadata dérivé, consommateurs exhaustifs.
- [x] 3. `plan.md` — architecture cible, phases BOS-011/012, rollback.
- [x] 4. `tasks.md` — ce fichier.
- [x] 5. ADR-0026 `docs/architecture/adr/0026-registre-unifie-modules-features-solutions.md` + entrée registre `README.md`.
- [ ] 6. PR `Closes #8148` — revue **owner** (critère d'acceptation n° 1) — CI documentaire verte.

## Tâches définies pour BOS-011 (Block 2 — implémentation, issue à créer/suivre)

1. Créer la source déclarative du registre (format spec §4) + migration des 20 flags et 20 modules existants, `fleet` inclus (correction de la désync mesurée).
2. Dérivations `knownModules()` / `flags()` / `horizontalMirrors()` ; retrait de l'édition manuelle des constantes dérivées.
3. Mode `legacy`/`dual`/`registry` (config/env) + log de divergence sans PII.
4. Migrer en lecture les consommateurs (spec §8) sans changer les contrats.
5. Test de parité feature map (spec §5.1–5.2) + garde CI « module non enregistré = échec » (généralise les tests de registre par verticale).
6. Snapshot staging : parité 0 diff avant activation du mode `registry`.

## Tâches définies pour BOS-012 (Block 2 — consolidation, issue à créer/suivre)

1. `modules:consolidate --dry-run` : rapport de divergences `features` ⇄ `metadata.modules` mirrorées (0 diff fonctionnel exigé).
2. Écriture canonique unique pour les outils mirrorés ; double écriture transitoire bornée puis retirée.
3. `moduleSelection()` dérivée via `metadata_mirror` ; contrat `/auth/me` inchangé.
4. Retrait du code legacy après 1 release de dual-read stable ; conservation du rapport de diff (chemin de retour).
