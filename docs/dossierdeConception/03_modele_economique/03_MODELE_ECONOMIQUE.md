# MODÈLE ÉCONOMIQUE ET PLANS TARIFAIRES — LEOPARDO RH
# Version 2.0 | Mars 2026

---

## 1. TABLEAU DES PLANS (SOURCE DE VÉRITÉ)

| | **Trial** | **Starter** | **Business** | **Enterprise** |
|---|---|---|---|---|
| **Prix mensuel** | Gratuit | 29 EUR/mois | 79 EUR/mois | 199 EUR/mois |
| **Prix annuel** | — | 290 EUR/an (-17%) | 790 EUR/an (-17%) | 1990 EUR/an (-17%) |
| **Max employés** | 5 | 20 | 200 | Illimité |
| **Durée trial** | 14 jours | — | — | 30 jours |

---

## 2. FEATURES PAR PLAN (Champ `plans.features JSONB`)

```json
STARTER (29€) :
{
  "biometric": false,
  "tasks": false,
  "advanced_reports": false,
  "excel_export": true,
  "bank_export": false,
  "billing_auto": false,
  "multi_managers": false,
  "photo_attendance": false,
  "api_public": false,
  "evaluations": false,
  "schema_isolation": false
}

BUSINESS (79€) :
{
  "biometric": true,
  "tasks": true,
  "advanced_reports": true,
  "excel_export": true,
  "bank_export": true,
  "billing_auto": true,
  "multi_managers": true,
  "photo_attendance": true,
  "api_public": false,
  "evaluations": true,
  "schema_isolation": false
}

ENTERPRISE (199€) :
{
  "biometric": true,
  "tasks": true,
  "advanced_reports": true,
  "excel_export": true,
  "bank_export": true,
  "billing_auto": true,
  "multi_managers": true,
  "photo_attendance": true,
  "api_public": true,
  "evaluations": true,
  "schema_isolation": true    ← schéma PostgreSQL dédié (isolation physique)
}
```

---

## PRICING MULTI-DEVISES (Phase 1)

### Taux de référence (à mettre à jour trimestriellement)
| EUR | DZD | MAD | TND | TRY |
|-----|-----|-----|-----|-----|
| 1   | 145 | 10.8| 3.3 | 36  |

### Prix affichés par pays (arrondis pour lisibilité)

| Plan | EUR | DZD | MAD | TND |
|------|-----|-----|-----|-----|
| Starter | 29€ | 4 200 DA | 315 MAD | 96 TND |
| Business | 79€ | 11 500 DA | 855 MAD | 261 TND |
| Enterprise | 199€ | 29 000 DA | 2 150 MAD | 657 TND |

### TVA et fiscalité locale
| Pays | TVA | Règle |
|------|-----|-------|
| Algérie | 19% | TVA sur services numériques étrangers — à ajouter sur facture |
| Maroc | 20% | TVA normale — applicable |
| Tunisie | 19% | TVA normale — applicable |
| France | 20% | TVA normale — applicable |
| Turquie | 20% | KDV — applicable |

### Note Phase 1
- Facturation en EUR uniquement (simplifie la comptabilité Phase 1)
- Prix locaux affichés à titre indicatif sur la landing page
- Facture émise en EUR avec mention du taux de change du jour
- Phase 2 : facturation native en devise locale via Paydunya (Afrique)
  et Iyzico (Turquie)

### Conformité
- Algérie : Loi 18-07 sur la protection des données personnelles
- Maroc : Loi 09-08
- France : RGPD
- Note : Ces conformités sont déjà couvertes par le chiffrement
  national_id (AES-256) documenté dans 12_SECURITY_SPEC_COMPLETE.md

---

## 3. STRATÉGIE DE MULTITENANCY LIÉE AUX PLANS

**Règle simple et non ambiguë :**

| Plan | Tenancy type | Isolation |
|------|-------------|-----------|
| Trial | `shared` | Logique (Global Scope company_id) |
| Starter | `shared` | Logique (Global Scope company_id) |
| Business | `shared` | Logique (Global Scope company_id) |
| Enterprise | `schema` | Physique (schéma PostgreSQL dédié) |

**Upgrade automatique :**
- Quand une entreprise passe de Business → Enterprise : le Super Admin exécute la migration
  de données depuis le schéma shared vers un nouveau schéma dédié via `TenantMigrationService`.
- Le downgrade d'Enterprise → Business est possible mais rare (déplacer les données vers shared).

---

## 4. FUNNEL DE CONVERSION

```
Landing page (SEO + Ads)
    ↓
CTA "Essai gratuit 14 jours" (sans CB)
    ↓
Formulaire d'inscription (5 champs) :
  - Nom entreprise
  - Email gestionnaire
  - Pays (détermine le modèle RH pré-configuré)
  - Nombre d'employés estimé
  - Plan souhaité (Starter / Business)
    ↓
Création automatique du compte (Trial → shared)
    ↓
Email de bienvenue + lien dashboard
    ↓
Onboarding guidé (4 étapes en dashboard) :
  1. Ajouter vos 3 premiers employés (CSV ou manuel)
  2. Configurer votre planning de travail
  3. Télécharger l'app mobile (lien + QR code)
  4. Faire un premier pointage de test
    ↓
J-12 : Email "Votre trial se termine dans 2 jours"
J-14 : Conversion payante ou suspension du compte (données conservées 30 jours)
```

**Note Phase 1 :** L'inscription publique sera activée dès la Phase 1 (auto-onboarding).
Le Super Admin reste pour les cas Enterprise et les configurations avancées.

---

## 5. PROJECTIONS FINANCIÈRES

### Hypothèses
- Taux de conversion Trial → Payant : 15%
- Churn mensuel : 5%
- Mix plans : 60% Starter, 35% Business, 5% Enterprise

### Projections MRR

| Mois | Clients actifs | MRR estimé |
|------|---------------|------------|
| M6   | 50 | 2 500 EUR |
| M12  | 150 | 8 500 EUR |
| M18  | 300 | 18 000 EUR |
| M24  | 500 | 30 000 EUR |

### CAC (Coût d'acquisition client) cible : < 80 EUR
### LTV cible (24 mois) : > 800 EUR → LTV/CAC > 10x

---

## 6. MODÈLE DE FACTURATION

### Phase 1 (MVP) — Facturation manuelle
- Le Super Admin crée les factures manuellement depuis le dashboard admin
- Paiement par virement ou chèque (adapté aux marchés cibles)
- Relance automatique à J+7 et J+30 si impayée

### Phase 2 — Facturation automatique
- Intégration Stripe (Europe, France) ou Paydunya (Afrique)
- Prélèvement automatique le 1er de chaque mois
- Portail client self-service (changer de plan, télécharger factures)

---

## 7. FEATURE GATING EN LARAVEL

```php
// app/Services/PlanService.php

class PlanService
{
    public function hasFeature(Company $company, string $feature): bool
    {
        $features = $company->plan->features; // JSONB auto-cast en array
        return (bool) ($features[$feature] ?? false);
    }

    public function requireFeature(Company $company, string $feature): void
    {
        if (!$this->hasFeature($company, $feature)) {
            throw new FeatureNotAvailableException(
                __('messages.feature_not_available', ['feature' => $feature, 'plan' => $company->plan->name])
            );
        }
    }
}

// Usage dans un Controller :
$this->planService->requireFeature($company, 'biometric');
// → Lance HTTP 403 avec JSON {"error":"FEATURE_NOT_AVAILABLE","message":"..."}
```
