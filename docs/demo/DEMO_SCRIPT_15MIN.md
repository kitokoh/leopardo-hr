# 🚀 Démo Leopardo RH — Script 15 minutes (F-23)

> Entreprise fictive : **SARL Atlas Distribution** (Alger, DZ) — seeder
> `DemoDzPayrollSeeder`. Aucune donnée réelle.

## Préparation

```bash
# 1. Migrer (public + tenant, inclut F-11)
php artisan leopardo:migrate

# 2. Seeder de démo paie DZ (idempotent)
php artisan db:seed --class=DemoDzPayrollSeeder
```

Résultat : 1 entreprise, ~30 employés (20 000 → 180 000 DZD), 4 grilles
salariales avec composants DZ, **3 cycles de paie clôturés et verrouillés**.

## Parcours (15 min)

| # | Étape | Écran | Ce qu'on montre |
|---|---|---|---|
| 1 | Connexion RH | Admin → login | Compte démo, RBAC, 2FA dispo |
| 2 | Employés | RH → Employés | Matricules, contrats, salaires DZ réalistes |
| 3 | Grilles | Paie → Structures salariales | BASE, ancienneté, panier, CNAS 9/26 %, IRG |
| 4 | Bulletins | Paie → Run du mois | Bulletin validé : brut → cotisations → net |
| 5 | Verrouillage | Paie → cycle clôturé | Statut `locked` : plus aucune modification silencieuse |
| 6 | Exports | Paie → Journal / CNAS | CSV rejouable, totaux = totaux de la clôture |
| 7 | Anomalies | Paie → Anomalies (IA) | Détection doublons/écarts (F-28) |
| 8 | Pointage | App employee | Check-in/out, mode hors-ligne (F-21) |

## Points d'ancrage produit

- **Conformité DZ** : `docs/payroll/DZ_COMPLIANCE.md` (IRG, CNAS, prorata, HS, congés).
- **Bulletin** : mentions légales `docs/payroll/BULLETIN_DZ_MENTIONS.md`.
- **RGPD** : registre + DPA `docs/security/`.
- **Sécurité** : isolation multi-tenant testée (19+ tests), secrets hors git.

## Comptes

Mot de passe commun : `password123`.

| Rôle | Email |
|---|---|
| Employé | (premier employé affiché par le seeder) |
| RH / Comptable | via `DemoCompanySeeder` (principal/RH par entreprise) |

## Anti-vérité

- Toutes les données sont fictives (aucun salarié réel).
- Le seeder est **opt-in** : jamais exécuté en production (gardé hors du
  parcours d'onboarding).
