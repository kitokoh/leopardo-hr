# Feature Specification: [FEATURE NAME]

**Feature Branch**: `[###-feature-name]`

**Created**: [DATE]

**Status**: Draft

**Input**: User description: "$ARGUMENTS"

---

> **🔴 LEOPARDO PAYROLL PRESET — LU AVANT TOUT**
>
> Cette spec touche le module Payroll. Les contraintes suivantes s'appliquent AUTOMATIQUEMENT.
> Cocher chaque case avant de passer à `/speckit-tasks`.

## ✅ Checklist Conformité Paie (obligatoire)

### Conformité légale
- [ ] La spec référence un document `docs/payroll/{PAYS}_COMPLIANCE.md`
- [ ] Chaque taux fiscal ou social porte une référence légale en commentaire (`// CGI art. XX`)
- [ ] Le `confidenceLevel()` du pays est déclaré (`'pilot'` ou `'production'`)
- [ ] Si le pays passe à `'production'` : un expert-comptable local doit valider (mentionné dans la PR)

### Tests golden (NON NÉGOCIABLE)
- [ ] Au minimum 3 golden tests calculés à la main couvrent cette spec
- [ ] Chaque test documente le calcul manuel complet dans un commentaire PHP
- [ ] Les tests couvrent : SMIG, cadre moyen, et ≥ 1 cas limite (plafond, prorata, HS)
- [ ] Tests dans `api/tests/Feature/Payroll/Golden/Golden{PAYS}*Test.php`

### Isolation tenant
- [ ] Tout nouvel endpoint paie a un test `assert 404 cross-tenant`
- [ ] Toutes les requêtes Eloquent portent `->where('company_id', $run->company_id)`
- [ ] La spec mentionne explicitement l'isolation attendue

### Architecture
- [ ] `computeContribution()` utilisé (jamais de calcul taux inline)
- [ ] `professionalExpensesDeduction()` et `calculateBracketTax()` déclarés si pays concerné
- [ ] Audit trail `AuditLog::create()` sur toute modification de run payroll

### CI
- [ ] PHPStan strict level 8 vert prévu
- [ ] Coverage Payroll ≥ 80 % maintenu ou amélioré
- [ ] CHANGELOG.md à jour dans la PR

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - [Brief Title] (Priority: P1)

[Décrire ce que le RH / comptable / employé peut faire avec cette feature]

**Pays concerné** : [DZ | CM | CI | SN | GA | CG | BF | ML | autre]

**Référence légale** : [ex. CGI Cameroun art. 68 / DZ_COMPLIANCE.md §1]

**Acceptance Scenarios**:

1. **Given** [état initial], **When** [action], **Then** [résultat attendu avec valeurs chiffrées]
2. **Given** brut > plafond cotisation, **When** calcul, **Then** assiette = plafond (pas brut)
3. **Given** tenant B accède run de tenant A, **When** GET endpoint, **Then** 404

---

### User Story 2 - [Brief Title] (Priority: P2)

[Deuxième cas d'usage]

**Acceptance Scenarios**:

1. **Given** [état initial], **When** [action], **Then** [résultat attendu]

---

## Technical Context

### Pays / Zone ciblée
<!-- Préciser le pays ISO (DZ, CM, CI, SN, etc.) et le niveau de maturité actuel -->

| Pays | Zone | Niveau actuel | Niveau cible |
|------|------|--------------|-------------|
| [CODE] | [CEMAC/CEDEAO/Maghreb] | placeholder/pilot | pilot/production |

### Impact sur PayrollCalculator
<!-- Méthodes affectées : calculateIncomeTax, calculateSocialCharges, computeWorkedDays, etc. -->

### Fichiers à créer / modifier
- `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/{Pays}PayrollRules.php`
- `docs/payroll/{PAYS}_COMPLIANCE.md`
- `api/tests/Feature/Payroll/Golden/Golden{Pays}PayrollTest.php`
- `api/database/seeders/PayrollCountryConfigSeeder.php` (si nouveaux barèmes DB)

---

## Out of Scope

- Autres pays non mentionnés dans cette spec
- Interface admin de gestion des taux (issue séparée)
- Calcul automatique des fériés islamiques (table `islamic_calendar` existante)

---

## Dependencies

<!-- Issues/PRs dont cette spec dépend -->
- [ ] ZONE-INFRA (#1820) — `computeContribution()`, `calculateBracketTax()`, `thirteenthMonthMandatory()` ✅ mergé
- [ ] `Public holidays` (#1811) — si la spec touche `computeWorkedDays()` ✅ mergé
- [ ] Autre :

---

*Spec générée avec le preset `leopardo-payroll` v1.0.0*
*Référence : `.specify/constitution.md` § III & VIII*
