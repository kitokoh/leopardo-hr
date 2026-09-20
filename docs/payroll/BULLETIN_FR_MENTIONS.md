# 🇫🇷 Bulletin de paie FR — mentions obligatoires (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel documentaire **pilot** — aucune
> promesse de conformité ; à valider par un expert-comptable / gestionnaire de
> paie français avant tout usage statutaire (registre `VALIDATION_EXPERTE.md`).

## Sources légales

- **Art. R3243-1 C. trav.** — liste des mentions obligatoires du bulletin de paie.
- **Art. R3243-4 C. trav.** — mentions **interdites** (grève, activité de représentation).
- **Art. L3243-2 / L3243-4 C. trav.** — remise du bulletin (dématérialisation possible), conservation du double par l'employeur pendant **5 ans**.
- **Arrêté du 25 février 2016 modifié (arrêté du 31 janvier 2023)** — modèle du **bulletin clarifié** : libellés, regroupement des cotisations par risque, ligne « **montant net social** » (obligatoire depuis juillet 2023).

## Mentions obligatoires (art. R3243-1)

### Bloc employeur
- Nom (raison sociale) et adresse de l'employeur (le cas échéant, établissement).
- Numéro **SIRET** de l'établissement et code **APE/NAF**.
- Référence de l'organisme de recouvrement des cotisations (**URSSAF**) et numéro de cotisant.
- **Convention collective** de branche applicable (ou, à défaut, référence au Code du travail pour congés payés et préavis).

### Bloc employé
- Nom du salarié, **emploi occupé**, **position dans la classification** conventionnelle (niveau/coefficient).

### Bloc période & temps de travail
- Période de paie et **nombre d'heures de travail**, en distinguant heures au taux normal et **heures supplémentaires/complémentaires** avec mention des **taux appliqués**.
- Nature et volume du **forfait** (heures/jours) le cas échéant.
- Dates de **congés** et montant de l'**indemnité de congés payés** si une période de congé est comprise dans la période.

### Bloc rémunération
- **Rémunération brute** du salarié.
- Accessoires de salaire soumis à cotisations (primes, avantages en nature…).
- Montant, **assiette et taux des cotisations et contributions sociales** (part salariale ET patronale), **regroupées par risque** (santé, AT/MP, retraite, famille, chômage) selon le modèle clarifié.
- Montant des **exonérations et allègements** de cotisations.
- **Montant net social** (depuis juillet 2023).
- **Net à payer avant impôt sur le revenu**, montant du **prélèvement à la source** (assiette, taux, montant), **net payé**.
- **Montant total versé par l'employeur** (rémunération + cotisations patronales − exonérations).
- Date de paiement.

### Mentions informatives obligatoires
- Mention incitant à **conserver le bulletin sans limitation de durée**.
- Mention de la rubrique dédiée du portail **service-public.fr** (bulletin de paie).

## Mentions INTERDITES (art. R3243-4)

- ❌ **Exercice du droit de grève** — ni le mot, ni un libellé permettant de l'identifier (utiliser « absence non rémunérée »).
- ❌ **Activité de représentation des salariés** (heures de délégation) — la nature et le montant de la rémunération de l'activité de représentation figurent sur une **fiche annexée** au bulletin, pas sur le bulletin lui-même.

> Nota : le **motif d'une absence maladie** n'est PAS une mention obligatoire de
> R3243-1 et n'est pas listé comme interdit par R3243-4 ; la pratique (et la
> doctrine CNIL) recommande un libellé neutre (« absence »), le détail médical
> relevant des données de santé. Seules la grève et la représentation du
> personnel sont formellement interdites sur le bulletin.

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Raison sociale, adresse employeur | ✅ (`company.name/address/city/country`) |
| Nom salarié, matricule | ✅ |
| Période de paie | ✅ |
| Jours travaillés / heures supplémentaires (volume) | ✅ (jours + heures sup) |
| Lignes de gains détaillées (libellé, base, taux, montant) | ✅ (`PaySlipLine` earnings) |
| Brut total | ✅ |
| Lignes de cotisations salariales (libellé, base, taux, montant) | ✅ |
| Cotisations patronales détaillées | ✅ (section dédiée) |
| Total retenues, net à payer | ✅ |
| Cumuls annuels | ✅ (non requis par R3243-1 mais présent) |
| SIRET / code APE / n° URSSAF | ❌ manquant (extensible via `company.metadata`, pattern F-09 DZ `legal_*`) |
| Convention collective | ❌ manquant |
| Position dans la classification (niveau/coefficient) | ❌ manquant |
| Regroupement des cotisations **par risque** (modèle clarifié) | ❌ manquant (lignes à plat, non regroupées) |
| **Montant net social** | ❌ manquant |
| Net avant impôt / PAS (assiette, taux, montant) / net payé | ⚠️ partiel — le PAS peut apparaître comme ligne de déduction, mais pas le triptyque normalisé |
| Montant total versé par l'employeur (coût total) | ❌ manquant (les cotisations patronales sont listées mais pas totalisées avec le brut) |
| Dates de congés + indemnité CP sur la période | ❌ manquant |
| Mentions service-public.fr + conservation illimitée | ❌ manquant (le pied de page générique ne les porte pas) |
| Absence de mention grève / représentation | ✅ par construction (aucun libellé de ce type généré) — à verrouiller si des composants custom sont libellés librement |

**Écart global : IMPORTANT** — le gabarit actuel est un bulletin générique
multi-pays, pas le modèle « clarifié » français (regroupement par risque, net
social, bloc PAS). Le générateur n'est **pas modifié** dans ce lot ; l'écart
est acté ici et devra faire l'objet d'issues de suivi dédiées (vue
`pdf.payslip` variante FR + métadonnées employeur `legal_siret`,
`legal_ape`, `legal_urssaf`, `legal_convention_collective`).

## Statut

- [x] Référentiel sourcé (R3243-1, R3243-4, arrêté du 31/01/2023).
- [x] Checklist de couverture générateur (ci-dessus).
- [ ] Variante FR du gabarit PDF (issue de suivi à créer, hors périmètre #7932).
- [ ] Validation experte (fiche `VALIDATION_EXPERTE.md`) — niveau `pilot`.
