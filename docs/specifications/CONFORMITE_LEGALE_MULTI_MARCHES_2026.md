# Conformité légale multi-marchés — Comptabilité & RH/Paie (directive fondateur 2026-09-20)

**Version** : 1.0 · **Date** : 2026-09-20 · **Statut** : validée fondateur (directive directe, exception au freeze 60j — voir issue épic associée)
**Marchés cibles** : Afrique centrale (CEMAC), Afrique de l'Ouest (CEDEAO/UEMOA), France, Turquie, Canada.
**Périmètre** : BC-08 ACCOUNTING, BC-07 PAYROLL, BC-06 LEAVE, BC-04 HR.

> Règle de prudence (constitution §III et posture #7922) : rien ici ne promet une
> « conformité validée ». Le passage `pilot` → `production` exige toujours une fiche
> expert signée (`docs/payroll/VALIDATION_EXPERTE.md`). Ce chantier vise la
> **couverture fonctionnelle légale**, sourcée et testée, prête pour validation experte.

---

## 1. État des lieux (audit 2026-09-20)

### 1.1 Comptabilité (BC-08)

| Capacité | État | Références |
|---|---|---|
| Plans comptables grand-livre (PCG, SYSCOHADA, Tekdüzen, CA/GB/US) | ✅ registre `AccountingChartOfAccounts` (SYSCOHADA/PCG `production`, TR/CA/GB/US `pilot`) | `api/app/Modules/Accounting/Infrastructure/Services/AccountingChartOfAccounts.php` |
| **Seed du plan comptable au provisioning** | 🟡 PCG uniquement — un tenant CM/TR/CA reçoit un plan français | `ChartOfAccountsDefaults.php` |
| Taux de TVA par pays (21 pays) | 🟡 TR mono-taux (KDV 20 seulement, manquent 10/1) ; CA fédéral seul (GST 5, manquent TVH/TVP/TVQ provinciales) | `AccountingSettingsDefaults::TVA_RATES_BY_COUNTRY` |
| Export FEC France | 🟡 format 13 colonnes OK, mais devise défaut `DZD` en dur, pas de nommage officiel `SIRENFECAAAAMMJJ` | `FecExporter.php` |
| Mentions légales facture | 🟡 7 pays (DZ MA TN SN CI FR TR) — rien pour CEMAC, BF/ML/TG/BJ/NE, CA | `AccountingSettingsDefaults::LEGAL_MENTIONS_BY_COUNTRY` |
| Rétention légale | 🟡 120 mois uniformes, non paramétrés par pays | `AccountingRetentionService.php` |
| Liasse SYSCOHADA (états financiers normalisés OHADA) | ❌ statements génériques seulement | `FinancialStatementService.php` |
| Facturation électronique FR (Factur-X/PDP 2026), e-fatura/e-arşiv TR (GİB), déclaration GST/HST CA | ❌ | — |

### 1.2 RH / Paie (BC-07, BC-06, BC-04)

| Capacité | État | Références |
|---|---|---|
| Moteur paie multi-pays (barèmes IR + cotisations) | ✅ 20+ pays ; seul SN `production` (cœur), DZ validé expert (cœur) ; CEMAC CM/GA/CG et CEDEAO `pilot` ; CF/TD/GQ/BJ/NE placeholders | `Payroll/Infrastructure/Services/CountryRules/` |
| Déclarations sociales | 🟡 DZ (CNAS/DAS), SN, CM/GA/CG, CI/BF/ML, MA ✅ ; FR DSN minimale ; **TR SGK ❌, CA T4 ❌**, TN/TG/BJ/NE/CF/TD/GQ ❌ | `api/routes/modules/payroll_engine.php` |
| Canada | 🟡 fédéral seul : ni impôt provincial, ni RRQ (Québec) | `CanadaPayrollRules.php` |
| Congés légaux par pays | 🟡 4 pays (DZ MA TN SN) sur 20+ — **rien pour CEMAC, CEDEAO, FR, TR, CA** | `Planning/Infrastructure/Services/CountryRules/LegalLeaveRulesRegistry.php` |
| Mentions bulletin de paie | 🟡 DZ seul (`BULLETIN_DZ_MENTIONS.md`) | `docs/payroll/` |
| Modèles de contrats par pays | 🟡 DZ MA TN SN seulement | `HR/Infrastructure/Services/ContractCountryTemplates.php` |
| RGPD / protection des données | ✅ registre, matrice RGPD/18-07/09-08, PiiLifecycleService | `docs/security/` |

## 2. Cibles par marché (synthèse des exigences)

| Marché | Compta — exigences clés | RH/Paie — exigences clés |
|---|---|---|
| **CEMAC** (CM GA CG TD CF GQ) | Plan SYSCOHADA (acte uniforme OHADA révisé) seedé par défaut, mentions RCCM/NIU, rétention 10 ans (OHADA art. 24), liasse SYSCOHADA | CNPS/CNSS + IRPP par État, congés légaux (CM : 1,5 j ouvrable/mois — Code du travail art. 89 ss.), bulletins conformes |
| **CEDEAO/UEMOA** (CI SN BF ML TG BJ NE) | idem SYSCOHADA, TVA 18 %, mentions RCCM/IFU/NINEA | ITS/IUTS + CNPS/CNSS/INPS, congés 2 à 2,2 j/mois selon État, TRIMF SN |
| **France** | FEC conforme (EUR, nommage SIREN), TVA 20/10/5,5/2,1, rétention 10 ans (C. com. L123-22), Factur-X (échéance 09/2026 !) | DSN complète, PAS personnalisé, congés 2,5 j ouvrables/mois, bulletin clarifié |
| **Turquie** | Tekdüzen seedé, KDV 20/10/1, e-fatura/e-arşiv GİB, rétention 5 ans (VUK) / 10 ans (TTK) | SGK APHB (e-bildirge), İzin : 14/20/26 j selon ancienneté (İş K. m.53), kıdem/ihbar tazminatı |
| **Canada** | TPS 5 + TVH (ON 13, provinces atlantiques 15) + TVQ 9,975, rétention 6 ans (LIR 230), déclaration GST/HST | Impôt provincial + RRQ/QPP Québec, T4/RL-1, vacances CLC 2-3-4 sem. (fédéral) |

## 3. Plan d'exécution en vagues

### Vague 1 — implémentable immédiatement (lots par BC, branche `bc/<code>-...`)

**Lot BC-08 ACCOUNTING — `bc/BC-08-legal-multimarches`**
- **A1. Seed du plan comptable par référentiel** : au provisioning, sélectionner la famille selon le pays (SYSCOHADA pour OHADA, Tekdüzen pour TR, plan CA pour CA, PCG sinon) au lieu de PCG partout. Critères : nouveau tenant CM → comptes 521/571/44571 ; tests par famille.
- **A2. Fiscalité indirecte TR & CA complète** : KDV 20/10/1 ; TPS/TVH/TVP par province canadienne + TVQ. Critères : taux multiples exposés, déclaration TVA les ventile.
- **A3. FEC France conforme** : devise issue du pays (EUR pour FR), nommage `SIRENFECAAAAMMJJ`, SIREN dans les réglages. Critères : test de nommage + devise.
- **A4. Mentions légales factures étendues** : CM GA CG TD CF GQ BF ML TG BJ NE CA GB US (RCCM, NIU, IFU, NIF, BN/GST number…). Critères : table complétée + test.
- **A5. Rétention comptable par pays** : OHADA 10 ans, FR 10 ans, TR 10 ans (TTK 82), CA 6 ans (LIR 230(4)), défaut 10 ans. Critères : `retention_months` résolu par pays, docs `docs/security/ACCOUNTING_RETENTION.md` à jour.

**Lot BC-06 LEAVE — `bc/BC-06-conges-legaux-multimarches`**
- **L1. Congés légaux CEMAC + CEDEAO** : règles CM GA CG CI BF ML TG BJ NE (+ TD CF GQ en `pilot` prudent), chaque valeur sourcée (code du travail, article) dans `docs/payroll/LEGAL_LEAVES.md`.
- **L2. Congés légaux FR, TR, CA** : FR 2,5 j ouvrables/mois ; TR barème ancienneté m.53 ; CA CLC (2/3/4 semaines + indemnité 4/6/8 %).

**Lot BC-07 PAYROLL — `bc/BC-07-legal-multimarches`**
- **P1. Référentiels mentions bulletin** : `BULLETIN_{FR,TR,CA,CM,CI,SN}_MENTIONS.md` sur le modèle DZ + exposition des mentions dans le générateur PDF.
- **P2. Canada : impôt provincial + RRQ (Québec)** : barèmes provinciaux 2026 (au minimum QC + ON), RRQ/QPP à la place de CPP pour QC, RQAP, golden tests calculés à la main (≥6). Confidence `pilot`.

### Vague 2 — issues créées, implémentation ultérieure
- **V2-1** TR : export SGK APHB (e-bildirge) — BC-07.
- **V2-2** CA : exports T4 / RL-1 — BC-07.
- **V2-3** FR : DSN enrichie (blocs individus complets, contrôles NEODeS) + PAS personnalisé — BC-07.
- **V2-4** FR : Factur-X / facturation électronique (portail PDP) — BC-08. **Urgence marché : mandat 09/2026.**
- **V2-5** TR : e-fatura / e-arşiv GİB — BC-08.
- **V2-6** OHADA : liasse SYSCOHADA normalisée (bilan, compte de résultat, TAFIRE) — BC-08.
- **V2-7** Modèles de contrats CEMAC/CEDEAO/FR/TR/CA — BC-04.
- **V2-8** Campagne de validation experte par pays (pilot → production) — process humain, fiches `VALIDATION_EXPERTE.md`.

### Vague 3 — dépendances externes / décision fondateur
- Télédéclarations en ligne (API DGI/URSSAF/GİB/ARC), archivage à valeur probante, SAF-T.

## 4. Règles d'implémentation (opposables)

1. Tout taux/durée légale ajouté = source citée (loi, article, année) dans le doc compliance du pays.
2. Tout calcul paie/congé = golden test calculé à la main.
3. Aucun fallback silencieux de pays (pattern `CountryRulesResolver`).
4. `confidenceLevel` = `pilot` pour tout nouveau référentiel ; `production` seulement après fiche expert signée.
5. Aucune promesse marketing de « conformité » côté front/site (garde anti-over-promise #7922) — formulation « couverture en cours de validation selon le pays ».
6. Lots par BC (`BC_BATCH_BRANCH_PROTOCOL.md`) : 1 commit par issue, 1 PR par lot, `Closes #N` répétés.
