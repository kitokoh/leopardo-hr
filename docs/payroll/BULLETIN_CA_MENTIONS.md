# 🇨🇦 Bulletin de paie CA — mentions obligatoires (pay statement / bulletin de paie) (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel **pilot** — le Canada superpose un
> régime **fédéral** (Code canadien du travail, entreprises de compétence
> fédérale) et des normes **provinciales** (ex. Québec LNT) ; valider la
> juridiction applicable avant tout usage statutaire.

## Sources légales

- **Code canadien du travail, art. 254 (CLC s.254)** — l'employeur fournit au salarié, **au moment du paiement**, un état écrit indiquant : la **période** de paie, le **nombre d'heures** rémunérées, le **taux de salaire**, le **détail des retenues**, et le **montant net** effectivement versé.
- **Loi sur les normes du travail du Québec (LNT), art. 46** — le bulletin de paie québécois contient notamment : nom de l'employeur ; **nom du salarié** ; **identification de l'emploi** ; **date du paiement et période de travail** correspondante ; **nombre d'heures payées au taux normal** ; **nombre d'heures supplémentaires payées ou remplacées par un congé, avec la majoration applicable** ; **nature et montant des primes, indemnités, allocations ou commissions** ; **taux du salaire** ; **montant du salaire brut** ; **nature et montant des déductions** ; **montant du salaire net** versé ; **montant des pourboires déclarés / attribués** (art. 50.2).
- Équivalents provinciaux hors QC : ex. Ontario ESA 2000 art. 12 (wage statement) — période, taux, brut, retenues détaillées, net.

## Mentions obligatoires (union CLC fédéral + LNT Québec)

### Bloc employeur
- Nom de l'employeur (LNT 46 1°). Adresse : usage (non exigée par CLC 254).

### Bloc employé
- Nom du salarié, **identification de l'emploi** (LNT 46 3°).
- ⚠️ Le **NAS/SIN ne doit PAS figurer** sur le bulletin (bonne pratique fédérale de protection des renseignements personnels — le NAS est requis sur T4/RL-1, pas sur le pay stub).

### Bloc période & temps
- **Date du paiement et période de travail** (LNT 46 4°, CLC 254).
- **Heures payées au taux normal** (LNT 46 5°) et **heures supplémentaires avec la majoration** (LNT 46 6°).
- **Taux de salaire** (LNT 46 7°, CLC 254).

### Bloc rémunération
- Nature et montant des **primes, indemnités, allocations, commissions** (LNT 46 6.1°) — dont l'**indemnité de vacances** (vacation pay 4/6/8 % CLC).
- **Salaire brut** (LNT 46 8°).
- **Nature et montant de chaque déduction** (LNT 46 9°, CLC 254) : impôt fédéral, impôt provincial, RPC/CPP ou RRQ/QPP, AE/EI, RQAP (QC), cotisations syndicales, etc.
- **Salaire net versé** (LNT 46 10°).
- Pourboires déclarés/attribués le cas échéant (QC).

## Mentions interdites

- Pas de liste d'interdictions expresse dans CLC 254 / LNT 46 ; la minimisation des renseignements personnels (LPRPDE / Loi 25 QC) proscrit en pratique le **NAS** et toute donnée de santé sur le bulletin.

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Nom employeur, nom salarié, emploi (matricule) | ✅ (l'intitulé d'emploi dépend des données employé) |
| Période de paie | ✅ |
| Date du paiement distincte de la période | ❌ manquant (seule la date de génération figure) |
| Heures au taux normal | ⚠️ partiel — le bulletin affiche jours travaillés/ouvrés, pas les **heures** ni le **taux horaire** (CLC 254 & LNT 46 5°/7° raisonnent en heures/taux) |
| Heures supplémentaires + **taux de majoration** | ⚠️ partiel — volume affiché ; la majoration apparaît si la ligne earnings la porte |
| Primes/indemnités/allocations détaillées | ✅ (lignes earnings) |
| Salaire brut | ✅ |
| Nature et montant de chaque déduction | ✅ (lignes deductions : impôt fédéral/provincial, CPP/QPP, EI, RQAP dès lors que le moteur les génère — cf. #7933) |
| Salaire net versé | ✅ |
| Pourboires (QC) | ❌ manquant (hors périmètre produit actuel) |
| Absence du NAS | ✅ par construction |
| Mention légale CA (« Issued under the Canada Labour Code… ») | ✅ (`COUNTRY_LEGAL['CA']`) — à enrichir d'une mention LNT pour un employeur QC |

**Écart global : MOYEN** — l'écart structurant est le raisonnement en
**heures/taux horaire** (le gabarit est mensuel en jours). Générateur **non
modifié** dans ce lot ; issue de suivi à créer pour une variante heures/taux
et la date de paiement.

## Statut

- [x] Référentiel sourcé (CLC art. 254, LNT art. 46, ESA ON art. 12).
- [x] Checklist de couverture générateur.
- [ ] Validation experte locale — niveau `pilot`.
