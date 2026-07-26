# Limites legales des regles pays (paie/pointage)

Version: 1.0
Date: 2026-07-23
Ticket: PA2-COUNTRY-012 (depend de PA2-COUNTRY-001)

## Objectif

`CountryDefaults` (devise/timezone/langue) et les classes `CountryRulesInterface`
(cotisations sociales, bareme d'impot, heures supplementaires, jours de repos,
cycles de paie) exposent des valeurs par pays consommees par le backend de
paie/pointage et par le provisioning entreprise (`CompanyProvisioningService`,
`PlatformCountryDefaultsController`, `SelfServiceTrialController`). Ce document
explique explicitement, pour chaque pays/zone couvert, **d'ou viennent ces
valeurs**, **ce qui est configurable**, et **ce qui doit etre valide localement
avant tout usage en paie reelle** — pour qu'aucune equipe commerciale ou client
pilote ne suppose a tort qu'un bareme affiche par l'application est deja une
reference legale validee.

Ce document ne remplace **jamais** un conseil juridique/comptable local. Il
documente l'etat reel du code, pas une garantie de conformite.

## Niveau de confiance (`confidenceLevel()`)

Chaque implementation de `CountryRulesInterface`
(`api/app/Modules/Payroll/Infrastructure/Services/CountryRules/*.php`) expose
`confidenceLevel()`, une des trois valeurs suivantes :

| Valeur | Signification | Statut actuel dans ce depot |
|---|---|---|
| `production` | Valide localement avec une source officielle/comptable, utilise en paie reelle | **Aucun pays n'a ce statut a ce jour.** |
| `pilot` | Implemente a partir de sources publiques generalistes (lois/codes du travail cites en commentaire), pas encore valide par un expert comptable/juridique local | DZ, MA, TN, FR, TR, SN |
| `placeholder` | Structure/forme correcte, valeurs non recherchees serieusement ou explicitement generiques pour toute une zone | CEMAC (CM/CF/TD/CG/GA/GQ) |

**Aucune valeur retournee par ces classes ne doit etre presentee a un client
comme juridiquement garantie** tant que son `confidenceLevel()` n'est pas
`production`. Un client pilote qui utilise reellement la paie de ce systeme
doit faire valider les taux/baremes par un professionnel local avant le
premier versement reel.

## Etat par pays/zone

| Code | Pays/zone | Devise | Confidence | Ce qui est reellement implemente | Ce qui reste a valider localement |
|---|---|---|---|---|---|
| DZ | Algerie | DZD | `pilot` | Cotisations CNAS, bareme IRG progressif + abattement, weekend legal (vendredi+samedi, loi 90-11), seuil heures supp 40h/semaine (art. 26), majoration +50% (art. 33) | Confirmer les taux CNAS/IRG en vigueur a la date de paie reelle (ils evoluent par loi de finances) ; calendrier de jours feries (voir section suivante) |
| MA | Maroc | MAD | `pilot` | Cotisations sociales, IR annuel avec deduction fixe, repos dominical, seuil heures supp 44h/semaine (loi 65-99), majoration +25% | Memes reserves : taux CNSS/AMO et bareme IR peuvent evoluer ; pas de distinction jour/nuit/ferie sur les heures supp (l'interface generique ne porte pas d'horodatage) |
| TN | Tunisie | TND | `pilot` | Cotisations sociales, IR progressif, repos dominical, seuil heures supp 48h/semaine (Code du travail art. 79), majoration +25% | Idem : validation CNSS/IRPP tunisien a la date reelle |
| FR | France | EUR | `pilot` | Cotisations sociales simplifiees, IR progressif, weekend legal (samedi+dimanche), seuil 35h/semaine (art. L3121-27), paliers +25% puis +50% au-dela de 8h supplementaires | La paie francaise reelle depend fortement de la convention collective applicable (non modelisee ici) ; ce module ne remplace pas un logiciel de paie francais certifie |
| TR | Turquie | TRY | `pilot` | Cotisations sociales, IR progressif, repos dominical, seuil 45h/semaine (Loi 4857 art. 63), majoration +50% | Validation SGK/gelir vergisi a la date reelle |
| SN | Senegal | XOF | `pilot` | Cotisations sociales, IR, repos dominical, seuil 40h/semaine, paliers +15% puis +40% | Validation locale des taux ; seul pays CEDEAO/UEMOA avec une classe `CountryRulesInterface` dediee a ce jour (voir section CEDEAO ci-dessous) |
| CM, CF, TD, CG, GA, GQ | Zone CEMAC | XAF | `placeholder` | Une seule classe `CemacPayrollRules`, scopee par pays via `forMemberCountry()` : cotisations CNPS/CNSS generiques (4.2%/16.2%), bareme IRPP progressif generique (structure seulement, pas de source citee par pays), SMIG different par pays, timezone differente par pays, seuil heures supp 40h/semaine, paliers +20%/+30% (structure generique inspiree des codes du travail d'origine francaise de la zone, non validee par pays) | **Aucune valeur fiscale/sociale de cette classe n'a ete confirmee pays par pays.** Avant tout client CEMAC reel : verifier taux CNPS/CNSS et bareme IRPP exacts du pays concerne auprès d'un expert local |
| SN, CI, ML, BF, BJ, TG, NE | CEDEAO/UEMOA (hors SN) | XOF (defaut) | n/a — **aucune classe `CountryRulesInterface`** | `CountryDefaults` expose devise/timezone/langue pour CI/ML/BF/BJ/TG/NE, mais aucune de ces 6 pays n'a de cotisations sociales, bareme d'impot, seuil heures supp ni jours de repos dedies : toute tentative de paie reelle pour ces pays via `PayrollCalculator::getRules()` echoue avec `InvalidArgumentException("No payroll rules for country: ...")` | Ticket separe `PA2-COUNTRY-008` (Regles CEDEAO, non livre a ce jour) doit ajouter ces classes avant tout client reel dans ces 6 pays |
| CA | Canada | CAD | n/a — **aucune classe `CountryRulesInterface`** | `CountryDefaults` expose devise/timezone/langue (`America/Toronto` par defaut, sans notion de province) depuis PA2-COUNTRY-001 ; aucune regle de paie/heures supplementaires n'existe | Ticket separe `PA2-COUNTRY-009` (Regles Canada par province, non livre a ce jour) doit ajouter des regles par province avant tout client reel canadien — les heures supplementaires et charges sociales canadiennes varient significativement par province |

## Jours feries : placeholder explicite partout

**Aucun pays de ce systeme n'a de calendrier de jours feries officiel branche.**
Chaque implementation existante retourne un `publicHolidaysSource()` qui
commence litteralement par le mot `placeholder`/`Placeholder` et se termine par
`Pending PA2-COUNTRY-012` (ce document). Concretement, cela signifie :

- aucune date de jour ferie n'est calculee ou suggeree automatiquement par le
  systeme, pour aucun pays ;
- les jours feries doivent etre saisis manuellement par chaque entreprise
  tenant (feature existante hors perimetre de ce document) ;
- un client qui suppose que le systeme "sait" quand est un jour ferie national
  se trompe — c'est explicitement documente comme non fait, pas silencieusement
  approximatif.

Un futur ticket dedie (hors backlog PA2-COUNTRY actuel) serait necessaire pour
brancher une vraie source de calendrier de jours feries par pays.

## Ce qui est configurable par entreprise (tenant) vs. fige par pays

| Aspect | Configurable par entreprise ? | Mecanisme |
|---|---|---|
| Bareme d'impot sur le revenu (tax slabs) | **Oui, avec fallback pays.** | `AbstractCountryRules::taxSlabs()` cherche d'abord une ligne `tax_slabs` scopee a l'entreprise (`company_id`), puis une ligne globale (`company_id IS NULL`), puis retombe sur le bareme code en dur par pays (`defaultTaxSlabs()`). CRUD expose via `TaxSlabController`. |
| Cotisations sociales (taux, plafonds) | **Non a ce jour.** | `socialContributions()`/`calculateSocialCharges()` retournent des valeurs codees en dur par classe pays ; aucune table de surcharge equivalente a `tax_slabs` n'existe encore pour les cotisations sociales. |
| Seuil et paliers d'heures supplementaires (`overtimeThresholdWeeklyHours()`/`overtimeRateTiers()`) | **Non a ce jour** au niveau paie legale — codes en dur par pays. Note : `Schedule::overtime_threshold_weekly` existe au niveau planning/pointage (regles horaires entreprise, PA2-ATT-006) mais reste un reglage operationnel distinct du seuil legal de calcul de paie modelise ici. | Codes en dur par pays dans chaque classe `CountryRules`. |
| Jours de repos hebdomadaires par defaut (`weeklyRestDays()`) | Sert de **defaut** seulement ; une entreprise peut configurer ses propres jours de repos via les regles horaires (PA2-ATT-006), qui prevalent alors sur ce defaut pays. | Defaut code en dur par pays ; surcharge via `Schedule`. |
| Cycles de paie autorises (`supportedPayCycles()`) | **Non.** C'est une contrainte technique par pays (ex. FR/TR ne supportent que `monthly` dans ce systeme aujourd'hui) — une entreprise ne peut pas demander un cycle non supporte par son pays. | Codes en dur par pays. |
| Salaire minimum (`minimumWage()`) | **Non a ce jour**, valeur informative codee en dur par pays/sous-pays (CEMAC : par membre). | Codes en dur. |

## Ce qu'un agent ou un commercial ne doit jamais affirmer

- Ne jamais dire a un client qu'un taux de cotisation sociale, un bareme
  d'impot ou un seuil d'heures supplementaires affiche par le systeme est
  "conforme a la loi [pays]" sans qualifier explicitement le niveau
  `confidenceLevel()` reel (`pilot` = source publique generaliste non validee
  localement, `placeholder` = structure non recherchee).
- Ne jamais proposer le systeme pour la paie reelle d'un pays qui n'a **aucune**
  classe `CountryRulesInterface` (aujourd'hui : CA, et CI/ML/BF/BJ/TG/NE hors
  Senegal) — l'appel echoue techniquement (`InvalidArgumentException`), ce
  n'est pas une simple imprecision de calcul.
- Ne jamais affirmer qu'un calendrier de jours feries est integre : c'est
  explicitement un placeholder pour tous les pays sans exception.

## References

- Definition of Done initiale : `docs/PLAN_ACTION2/05_SCOPE_PAYS_PAIE_POINTAGE.md`.
- Contrat d'interface : `api/app/Modules/Payroll/Domain/Contracts/CountryRulesInterface.php`.
- Implementations : `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/*.php`.
- Catalogue devise/timezone/langue : `api/app/Support/CountryDefaults.php`.
- Tests de regression du contrat : `api/tests/Unit/PayrollCountryRulesTest.php`.
- Tickets lies non encore livres a la date de ce document : `PA2-COUNTRY-008`
  (regles CEDEAO), `PA2-COUNTRY-009` (regles Canada par province).
