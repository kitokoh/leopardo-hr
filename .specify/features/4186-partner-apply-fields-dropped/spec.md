# Feature Specification: Partner — champs d'application persistés (issue #4186)

**Feature Branch**: `fix/4186-partner-apply-fields-dropped`
**Created**: 2026-08-16
**Status**: Draft → À implémenter
**Input**: Audit 360° 2026-08-16 — `ApplyAsPartner.php:21` crée une ligne `Partner` avec 7 clés non fillable/non-colonnes (`name`, `email`, `phone`, `website`, `commission_rate`, `employee_id`, `company_id`) → chaque application partenaire insère une ligne vide.

## Problème

- Table `partners` (migration `2026_06_13_000001`) : colonnes = `id, referral_code, default_commission_rate, status, type, timestamps` seulement.
- `$fillable` Partner : `user_id, referral_code, default_commission_rate, tax_rate, status, type, application_status, payment_details, payout_threshold, payout_cycle`.
- `ApplyAsPartner::execute()` passe `employee_id, company_id, name, email, phone, website, commission_rate` → Eloquent abandonne tout silencieusement → ligne quasi vide, programme partenaire sans contact ni taux.
- `ApprovePartner` (P2) et `PartnerService` (payouts) ne peuvent pas exploiter des données inexistantes.

## Décision

1. **Migration additive** `partners` : `name`, `email`, `phone`, `website` (nullable), `company_id` (uuid nullable), `employee_id` (nullable), `commission_rate` (decimal nullable). La table reste compatible avec les lignes existantes.
2. **Fillable complété** avec ces colonnes ; `commission_rate` reste distinct de `default_commission_rate` (le DTO `CreatePartnerDTO` transporte `commissionRate`).
3. `ApplyAsPartner` inchangé dans sa forme (les clés deviennent persistables) + garde : `status` initial `pending`.
4. **Test Feature** `PartnerApplyTest` : POST d'application → ligne avec name/email/phone/website/commission_rate/company_id/employee_id persistés ; `ApprovePartner` lit les contacts.

## User Scenarios & Testing

### User Story 1 — L'employé s'inscrit comme partenaire (Priority: P1)
**Independent Test**: `php artisan test --filter=PartnerApplyTest`

**Acceptance Scenarios**:
1. **Given** un employé soumet le formulaire partenaire (name/email/phone/website/commissionRate), **When** le use case s'exécute, **Then** la ligne `partners` contient toutes les valeurs (aucune perte).
2. **Given** une application approuvée (P2), **When** l'admin ouvre la fiche partenaire, **Then** les coordonnées de contact sont affichées.
3. **Given** une ligne existante sans contacts (avant migration), **When** migration appliquée, **Then** aucune donnée existante n'est perdue (colonnes nullable).

## Edge Cases

- `company_id` nullable : un employé sans compagnie (compte ordinaire) peut candidater — cohérent avec #4092/#3727 (stage `new`).
- Le taux par défaut reste `0.10` si le DTO n'en fournit pas (comportement actuel conservé).
- `default_commission_rate` (légataire) inchangé : utilisé par les payouts existants ; `commission_rate` = taux de l'application.

## Functional Requirements

1. Migration `2026_08_16_0000XX_add_contact_fields_to_partners` : `name, email, phone, website, company_id, employee_id, commission_rate` nullable sur `partners`.
2. `Partner::$fillable` étendu à ces 7 champs.
3. `ApplyAsPartner` persiste les 7 champs (aucun abandon).
4. Test : valeurs persistées + approbation lisible + non-régression lignes existantes.

## Success Criteria

- 100 % des applications partenaires conservent name/email/phone/website + taux (mesurable : zéro ligne `partners` avec name NULL après application).
- `ApprovePartner` affiche les contacts du candidat.
- PHPStan strict 0 erreur ; CHANGELOG mis à jour.
