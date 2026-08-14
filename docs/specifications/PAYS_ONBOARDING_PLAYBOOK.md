# Playbook — Onboarding d'un pays dans le moteur de paie multi-pays

> Issue #1875 — Processus reproductible pour ajouter un pays sans introduire
> de règles incomplètes ou de régression silencieuse. Complète la
> spécification `docs/specifications/MULTI_PAYS_RULES_ENGINE.md` (10
> invariants) et le référentiel de conformité `docs/payroll/*_COMPLIANCE.md`.

## Principes (rappel des invariants)

1. **Aucun fallback silencieux** : un pays non supporté doit lever une erreur
   explicite (`UnsupportedCountryRulesException`, 422), jamais retomber sur DZ
   ou un placeholder non déclaré (invariant 3/10).
2. **Un seul implémentation par ISO** : le registre
   `CountryRulesResolver::defaultRulesMap()` est la source unique — une classe
   dédiée OU un membre d'une classe zone (CEMAC/CEDEAO), jamais les deux.
3. **Le niveau de confiance est honnête** : `placeholder` (structure seule),
   `pilot` (implémenté depuis sources publiques, non validé localement) ou
   `production` (validé par expert local, date dans la doc) — invariant 7.
4. **Un pays incomplet échoue en CI** : la garde
   `dev-hub/tools/check-country-catalog.sh` (job `Module Structure Validator`)
   vérifie le catalogue sur chaque PR.

## Fiche pays obligatoire (avant toute implémentation)

Pour chaque pays supporté, le dossier `docs/payroll/` doit contenir
`<CC>_COMPLIANCE.md` avec **toutes** les sections ci-dessous (template
`docs/payroll/_TEMPLATE_COMPLIANCE.md`) :

| Section | Contenu requis | Exemple (DZ) |
|---|---|---|
| Statut | tableau règle × état × référence × validité | `DZ_COMPLIANCE.md` §Statut |
| Barème IR/IRPP | tranches (annuelles/mensuelles explicites, bornes inclusives) + assiette + abattement frais pro | §1/§2 |
| Cotisations sociales | taux salarial/patronal, plafonds (plafonné ou non), codes | §3 |
| SMIG / salaire minimum | valeur mensuelle + source | §4 |
| Heures supplémentaires | seuil hebdo légal, paliers (+20 %/+30 %…) | §5 |
| Fériés / calendrier | calendrier officiel + islamique si applicable (`confirmed` requis) | §6 |
| Fin de contrat | préavis (jours selon ancienneté), indemnité de licenciement (mois/année), solde de tout compte | §7 |
| Arrondis | règle d'arrondi (unité, demi-up…) | §8 |
| Niveau de confiance | `confidenceLevel()` + date de vérification + avertissement associé | §9 |
| Sources | textes officiels cités (CGI, code du travail, organisme social) + URLs | §10 |

La fiche est **soumise à la validation du propriétaire** avant création
d'issues/implémentation (règle d'or des nouveaux modules, AGENTS.md).

## Étapes d'implémentation (ordre)

1. **Fiche pays** : créer `<CC>_COMPLIANCE.md` (template ci-dessus) avec les
   valeurs légales sourcées. Sans fiche validée, pas d'implémentation.
2. **Registre des pays** : ajouter l'entrée dans
   `api/app/Support/CountryDefaults.php` (label, langue, devise, fuseau).
   Un pays sans entrée échoue la garde CI.
3. **Règles** :
   - pays isolé (règles très spécifiques, ex. SN) → nouvelle classe
     `CountryRules/<CC>PayrollRules.php` extends `AbstractCountryRules` ;
   - pays de zone homogène (CEMAC/CEDEAO) → membre de
     `CemacPayrollRules` / `CedeaoPayrollRules` :
     `MEMBER_COUNTRY_CODES` + branches `socialContributions()` /
     `defaultTaxSlabs()` / `confidenceLevel()` / `noticePeriodDays()`…
     **Ne jamais créer une classe dédiée si le membre appartient déjà à une
     zone, ni l'inverse** (garde CI anti-doublon).
4. **Résolveur** : ajouter la classe au registre
   `CountryRulesResolver::defaultRulesMap()` (pays isolés) — les membres de
   zone sont ajoutés automatiquement via `forMemberCountry()`.
5. **Contrat** : vérifier que l'implémentation couvre TOUTES les méthodes de
   `CountryRulesInterface` (contrat PHP typé — le compilateur/CI le garantit).
6. **Tests** :
   - **golden** `api/tests/Feature/Payroll/Golden/Golden<CC>PayrollTest.php` :
     cas **calculés à la main** (SMIG, tranches, plafonds, prorata, HS, fin de
     contrat) avec la référence légale citée — jamais de valeurs dérivées du
     code (cf. issue #1938) ;
   - **unitaires** pays (`PayrollCountryRulesTest`, tests zone) ;
   - **réconciliation** bulletin ↔ déclaration CSV si le pays a des
     déclarations (`BulletinDeclarationReconciliationTest`).
7. **i18n** : messages d'erreur/avertissements dans `api/lang/{fr,en,ar,tr}/
   payroll.php` (jamais de chaîne accentuée en dur — PA2-I18N-007) et
   régénérer `shared/i18n/versions/versions.json` via le sync tooling si des
   catalogues partagés changent.
8. **API/OpenAPI** : tout nouveau endpoint déclaratif/documenté dans
   `api/openapi.yaml` (+ miroir `dev-hub/openapi/v1.yaml`, SDK régénérés).
9. **CHANGELOG + AGENTS.md** : entrée `CHANGELOG.md` (Closes #issue) et toute
   leçon opérationnelle dans `AGENTS.md`.
10. **Recette métier** : faire valider les chiffres par un expert-comptable
    local (OHADA/CEDEAO/CEMAC), consigner date + nom dans la fiche, puis
    passer `confidenceLevel()` → `production` et lever l'avertissement.

## Validation CI (obligatoire avant merge)

La garde `dev-hub/tools/check-country-catalog.sh` (exécutée par le job
`Module Structure Validator` du workflow `Architecture Quality`) vérifie :

1. **Aucun doublon ISO** entre classes dédiées et listes de membres de zone ;
2. **Registre complet** : chaque pays à règles a une entrée `CountryDefaults` ;
3. **Fiche pays** : chaque pays `pilot`/`production` a `docs/payroll/<CC>_COMPLIANCE.md`
   (allowlist historique MA/TN/FR/TR — dette à résorber, suivi #1875/#1904) ;
4. **Golden** : chaque pays `pilot`/`production` a un fichier
   `Golden<CC>PayrollTest.php` (même allowlist historique).

Un **nouveau** pays (hors allowlist) doit satisfaire 1-4 dans la même PR, sinon
la CI est rouge.

## Rollback et versionnement

- **Versionnement** : `effective_from`/`effective_to` sur
  `tax_slabs`/`social_contributions` + `asOf()` pour les recalculs historiques
  (PA2-ARCH-004). Ne jamais écraser une fenêtre ouverte — fermer
  (`effective_to = nouveau effective_from − 1 j`) puis insérer.
- **Rollback** : un pays livré `pilot` peut être ramené à `placeholder` dans
  une PR dédiée (statut + golden alignés + CHANGELOG). Un pays `production`
  ne revient en arrière qu'avec une décision documentée (ADR) — jamais en
  silence.
- **Audit** : toute mutation de taux légaux passe par le workflow
  `tax_rate_change_log` (append-only, triggers PostgreSQL #1927/#2024) — le
  CRUD admin national est transactionnel et audité (#1923).

## Liens

- Spécification moteur : `docs/specifications/MULTI_PAYS_RULES_ENGINE.md`
- Référentiels pays : `docs/payroll/*_COMPLIANCE.md`
- Golden tests : `api/tests/Feature/Payroll/Golden/README.md`
- Compteur golden : `dev-hub/tools/payroll-golden-report.sh`
- Garde CI catalogue : `dev-hub/tools/check-country-catalog.sh`
- Validation experte multi-pays : issues #1904/#1912
