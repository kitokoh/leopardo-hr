# 🇹🇷 Bulletin de paie TR — mentions obligatoires (ücret hesap pusulası) (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel documentaire **pilot** — à valider
> par un expert local (SMMM/YMM) avant tout usage statutaire.

## Sources légales

- **İş Kanunu n° 4857, madde 37** (Code du travail turc, art. 37 — *ücret hesap pusulası*) : l'employeur remet au salarié, pour les paiements effectués au lieu de travail ou par banque, une fiche de calcul du salaire **signée ou portant la marque de l'entreprise**, indiquant : **le jour du paiement et la période concernée**, **tous les ajouts au salaire de base** (heures supplémentaires — *fazla çalışma* —, repos hebdomadaire — *hafta tatili* —, jours fériés et fêtes — *bayram ve genel tatil ücretleri*), et **toutes les retenues, montrées séparément** (impôt — *vergi* —, primes d'assurance sociale — *sigorta primi* —, imputation d'avances — *avans mahsubu* —, pension alimentaire — *nafaka* — et saisies — *icra*).
- **Vergi Usul Kanunu n° 213, madde 238** (Code de procédure fiscale, art. 238 — *ücret bordrosu*) : registre de paie mensuel obligatoire par salarié (période, salaire brut et unités, retenues fiscales avec taux, signatures) — le bulletin remis au salarié en pratique fusionne hesap pusulası + extrait du bordro.
- **5510 sayılı Kanun (SGK)** : identification employeur (n° de dossier SGK — *işyeri sicil numarası*) et salarié (n° SGK / T.C. kimlik) sur les documents de paie déclarés (APHB/e-bildirge).

## Mentions obligatoires

### Bloc employeur
- Raison sociale et adresse ; **signature ou cachet/marque de l'entreprise** (İş K. m.37).
- Numéro de dossier SGK de l'établissement (*işyeri sicil no*), numéro fiscal (*vergi kimlik no*) — exigés par les registres VUK/SGK.

### Bloc employé
- Nom, prénom ; **T.C. kimlik no / n° SGK** (bordro VUK m.238) ; poste.

### Bloc période & paiement
- **Jour du paiement** et **période de paie** concernée (İş K. m.37).

### Bloc rémunération
- Salaire de base et unités (jours SGK).
- **Chaque ajout au salaire montré séparément** : heures supplémentaires, repos hebdomadaire travaillé, fériés/fêtes nationales, primes.
- **Chaque retenue montrée séparément** : impôt sur le revenu (*gelir vergisi*, avec taux), taxe de timbre (*damga vergisi*), prime SGK salariale (14 %), assurance chômage salariale (*işsizlik sigortası*, 1 %), avances, pensions alimentaires, saisies.
- Brut total, total des retenues, **net à payer**.
- Exonération du minimum vital (asgari ücret istisnası — gelir + damga vergisi exonérés à hauteur du salaire minimum, depuis 2022) : l'assiette fiscale affichée doit en tenir compte.

## Mentions interdites

- Aucune interdiction expresse équivalente à l'art. R3243-4 français dans İş K. m.37 / VUK m.238. Prudence identique sur les données sensibles (santé, syndicat — *sendika* : la retenue de cotisation syndicale est licite mais relève de données sensibles au sens du KVKK).

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Raison sociale + adresse employeur | ✅ |
| Nom salarié, matricule, période | ✅ |
| Ajouts au salaire détaillés (lignes earnings avec base/taux/montant) | ✅ |
| Heures supplémentaires (volume) | ✅ |
| Retenues montrées séparément (lignes deductions) | ✅ (SGK/işsizlik/gelir vergisi si les lignes du run les portent) |
| Brut, total retenues, net | ✅ |
| Mention légale TR (« İş Kanunu uyarınca düzenlenmiştir… ») | ✅ (`COUNTRY_LEGAL['TR']`) |
| **Jour du paiement** distinct de la période | ❌ manquant (seule la période figure) |
| Signature / cachet employeur | ❌ manquant (İş K. m.37 exige une fiche « signée ou portant la marque » — un PDF non signé ne satisfait pas littéralement l'exigence ; e-imza à étudier) |
| N° dossier SGK employeur / vergi no | ❌ manquant (extensible via `company.metadata`, pattern F-09) |
| T.C. kimlik / n° SGK salarié | ❌ manquant |
| Damga vergisi en ligne séparée | ⚠️ dépend des lignes du run (le moteur TR la calcule ; vérifier le libellé) |
| Exonération asgari ücret affichée | ❌ manquant |

**Écart global : MOYEN** — la structure lignes-par-lignes couvre le cœur de
İş K. m.37 ; manquent les identifiants SGK/fiscaux, le jour de paiement et la
question de la signature. Générateur **non modifié** dans ce lot ; issues de
suivi à créer.

## Statut

- [x] Référentiel sourcé (İş K. m.37, VUK m.238, 5510).
- [x] Checklist de couverture générateur.
- [ ] Validation experte locale — niveau `pilot`.
