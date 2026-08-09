# 🎬 Kit de démo DZ réaliste — F-23 (#1553)

> Programme FOCUS — une démo crédible = une entreprise DZ fictive avec une
> paie complète et des comptes prêts. Alimenté par `DemoDzSeeder` (F-06 #1536).

## 1. Lancer le seeder

```bash
php artisan db:seed --class=DemoDzSeeder                # 30 employés (défaut)
php artisan db:seed --class=DemoDzSeeder -- --employees=50
```

Idempotent : relancer ne duplique rien (slug `demo-dz-spa`, firstOrCreate,
runs firstOrCreate). Zéro donnée réelle — tout est fictif.

## 2. Ce qui est créé

| Élément | Détail |
|---|---|
| Entreprise | Leopardo Demo DZ SPA (Alger, DZD, timezone Africa/Algiers) |
| Structures salariales | SMIG 20 000 · Cadre moyen 60 000 · Cadre supérieur 120 000 DZD + composants (base, prime transport) |
| Employés | 30 profils algériens réalistes (noms, matricules auto, contrats CDI depuis 2025) |
| Paie | 3 cycles : M-2 et M-1 **clôturés** (calcul → validation RH → verrouillage comptable), mois courant **calculé** (prêt pour la démo de clôture) |
| Congés | Type « Congé payé » (2,5 j/mois, politique `leave:accrue`) |

## 3. Comptes de démonstration (mot de passe commun : `password123`)

| Persona | Email | Rôle |
|---|---|---|
| Principal / DG | `principal.demo-dz@leopardo.test` | manager principal |
| RH | `rh.demo-dz@leopardo.test` | manager rh |
| Comptable | `comptable.demo-dz@leopardo.test` | manager comptable |
| Employé | `employe.demo-dz@leopardo.test` | employee |

## 4. Script de démo — 15 minutes

1. **0:00 — Connexion RH** (`rh.demo-dz@leopardo.test`) : tableau de bord,
   effectif, congés.
2. **0:03 — Pointage** : app employee (`employe.demo-dz@leopardo.test`),
   check-in hors-ligne simulé, géofencing, kiosque (si provisionné).
3. **0:06 — Demande de congé** : l'employé pose un congé payé, le RH approuve.
4. **0:08 — Paie** : ouvrir le run du mois courant (statut `calculated`) →
   montants, bulletin individuel (PDF), ligne « Indemnité de congés payés »
   si un congé payé est saisi dans la période.
5. **0:11 — Clôture** : validation RH → verrouillage comptable (audit trail
   visible) → tentative de recalcul refusée → déverrouillage motivé.
6. **0:13 — Exports** : journal de paie CSV, déclaration CNAS, virement
   bancaire (SEPA/CCP), export comptable.
7. **0:14 — Historique** : ouvrir M-1 (clôturé) — lecture seule, traces
   d'audit `payroll_run_validated/locked`.

## 5. Lien vitrine

La page vitrine `/signup` (essai guidé) reste le point d'entrée public ; le
kit de démo est destiné aux pilotes et à l'équipe commerciale (accès interne
ou démo accompagnée).

## 6. Notes

- Les montants sont calculés par le **moteur de paie réel** (`PayrollCalculator`
  + règles DZ) — pas de fixtures « à plat » déconnectées du calcul.
- Le mois courant reste `calculated` pour démontrer la clôture en direct ;
  les mois passés sont `locked` (lecture seule, audit trail).
- Rétention : aucune donnée personnelle réelle n'est utilisée.
