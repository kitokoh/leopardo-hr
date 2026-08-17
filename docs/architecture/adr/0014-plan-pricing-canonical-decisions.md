# ADR-0014 — Plans tarifaires canoniques, plan Free public, durée d'essai

**Date** : 2026-08-15  
**Statut** : Accepté  
**Décideur** : Responsable projet (délégation explicite du propriétaire)

---

## Contexte

Audit fonctionnel du 2026-08-15 a révélé 3 incohérences bloquantes :

1. La vitrine affichait **Starter/Business/Enterprise** alors que l'API utilise **Free/Pilot/Operations/Enterprise** — le checkout cassait silencieusement.
2. Le plan **Free** (0€/5 employés) existait dans le seeder mais était invisible de la vitrine.
3. La durée d'essai avait deux valeurs : **14 jours** (config, vitrine) vs **30 jours** (plan Free seeder).

Issues : #3876 (plans), #3883 (Free), #3913 (trial)

---

## Décisions

### 1. Noms et prix canoniques des plans

| Code (API) | Nom affiché | Prix mensuel | Prix annuel (÷12) | Employés inclus |
|---|---|---|---|---|
| `free` | Free | 0 € | 0 € | 5 max |
| `pilot` | Pilot | 29 €/mois | 24,17 €/mois (290 €/an) | 30 max |
| `operations` | Operations | 79 €/mois | 65,83 €/mois (790 €/an) | 200 max |
| `enterprise` | Enterprise | Sur devis | Sur devis | Illimité |

**Rationale prix** :
- `Operations` à **79 €** (pas 99 €) — la vitrine l'affichait à 79 € depuis le début, c'est le prix marché correct. Le seeder avait une valeur erronée qui aurait facturé 25 % de plus que ce qu'on vend.
- `Pilot` à 29 €/mois = cohérent avec ce qui est communiqué depuis le lancement.

**Rationale noms** :
- Les noms API (Free/Pilot/Operations/Enterprise) sont les noms canoniques.
- `Starter` et `Business` sont des alias legacy migrés par `PlanSeeder::migrateLegacyPlanNames()`. Ne plus les utiliser dans aucune surface.

### 2. Plan Free : PUBLIC

Le plan Free (0 €/5 employés) est **visible et sélectionnable** dans la vitrine.

**Rationale** : Leopardo cible les TPE/PME africaines. Un plan freemium pour ≤ 5 employés est le meilleur levier d'acquisition organique. Les petites structures découvrent le produit gratuitement, puis upgradent quand elles grandissent.

**Fonctionnalités Free** (from PlanSeeder) :
- Pointage basique ✅
- Gestion absences ✅
- Export paie basique ✅
- Pas de biométrie, pas de rapports avancés, pas d'export banque

### 3. Durée d'essai

| Plan | Trial |
|---|---|
| Free | **30 jours** — incentive pour que les petites équipes adoptent vraiment le produit |
| Pilot | **14 jours** — décision propriétaire D-E4-01, config `billing.trial_days` |
| Operations | **14 jours** |
| Enterprise | Négociable (défaut 14 jours) |

**Rationale** : `config('billing.trial_days', 14)` reste la référence pour les plans payants. Le plan Free garde ses 30 jours comme avantage distinctif du freemium — c'est inscrit dans le seeder (`'trial_days' => 30`) et cohérent avec la proposition de valeur « essayez vraiment ».

**Règle** : Le provisioning utilise `$plan->trial_days` en priorité sur `config('billing.trial_days')`.

---

## Conséquences

### Ce qui change

- `front/web/src/modules/vitrine/data/pricing.ts` — noms Free/Pilot/Operations/Enterprise, ajout du plan Free, Operations à 79 €
- `api/database/seeders/PlanSeeder.php` — `price_monthly` Operations = 79.00 (était 99.00)
- `front/web/src/app/(landing)/checkout/page.tsx` — codes plan canoniques
- FAQ, locales, copy vitrine — références aux nouveaux noms

### Ce qui ne change pas

- Structure technique des plans en DB
- Le seeder `migrateLegacyPlanNames()` continue de fonctionner pour les déploiements existants
- La config `billing.trial_days = 14` reste la valeur par défaut pour les plans payants

---

## Alternatives rejetées

- **Garder Starter/Business** : non, ça crée une dette de nommage permanente et casse le checkout.
- **Plan Free = interne** : trop conservateur. Le freemium est un standard SaaS africain.
- **30j pour tous** : non, 14j crée plus d'urgence à la conversion pour les plans payants.
