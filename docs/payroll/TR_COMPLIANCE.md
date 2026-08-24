# 🇹🇷 Référentiel de conformité paie — Turquie (TR)

> Mise à jour **2026** (issue #5253, Pack Turquie 100 %). Niveau courant :
> `pilot` — à valider par un mali müşavir (expert-comptable local) avant
> passage à « production » (issue #1904). Sources : CSGB (asgari ücret
> officiel), SGK (taux/plafonds), GİB (barème), Resmî Gazete 31/12/2025.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR salariés 2026 | ✅ implémentée | G.V.K. art. 103 (RG 31/12/2025) | à confirmer |
| İstisna SMIC (IR) | ✅ implémentée | loi n° 7346 du 25/12/2022 | à confirmer |
| Damga vergisi | ✅ implémentée | D.V.K., 71 Seri No.lu Tebliğ (RG 31/12/2025) | à confirmer |
| Cotisations SGK + chômage | ✅ implémentée | SGK 2026 (RG 31/12/2025) | à confirmer |
| SMIG | ✅ 33 030,00 TRY/mois | CSGB — Asgari Ücret 2026 | à confirmer |
| Plafond (tavan) | ✅ 297 270 TRY/mois | SGK — RG 31/12/2025 | à confirmer |

## 1. SMIG (asgari ücret) 2026

**33 030,00 TRY bruts/mois** (décision du Comité Asgari Ücret Tespit
Komisyonu, publiée par le CSGB — effet 01/01/2026).

Vérification officielle (PDF CSGB « Asgari Ücretin Net Hesabı », 2026) :

| Ligne | Montant |
|---|---|
| Brut | 33 030,00 |
| SGK salarié 14 % | 4 624,20 |
| İşsizlik salarié 1 % | 330,30 |
| IR (istisna SMIC) | 0,00 |
| Damga (part ≤ SMIC) | 0,00 |
| **Net** | **28 075,50** |
| Coût employeur (sans teşvik) | 40 874,63 (= 33 030 + 7 184,03 + 660,60) |

## 2. Cotisations sociales 2026 (SGK + işsizlik)

| Cotisation | Taux | Type | Plafond (tavan) |
|---|---|---|---|
| SGK (malullük/yaşlılık/ölüm 9 % + GSS 5 %) | 14 % | salarié | 297 270 TRY/mois |
| SGK (MYÖ 12 % + GSS 7,5 % + KVSK 2,25 %) | 21,75 % | employeur | 297 270 TRY/mois |
| İşsizlik sigortası | 1 % | salarié | 297 270 TRY/mois |
| İşsizlik sigortası | 2 % | employeur | 297 270 TRY/mois |

Notes :

- **Tavan 2026** : 9 909,00 TRY/jour × 30 = **297 270,00 TRY/mois** (RG
  31/12/2025). Toutes les cotisations sont plafonnées à cette assiette
  (`computeContribution`, constitution §III).
- **Teşvik (incitations)** : l'employeur peut réduire sa part SGK — 5 puan
  (16,75 %) secteur imalat, 2 puan (19,75 %) autres secteurs. **Non
  appliquées par défaut** (défaut prudent sans incitation) ; surchargeables
  via la table `social_contributions` (code SGK_TR_PAT).
- L'ancien taux employeur 20,5 % (2024-2025) est remplacé par 21,75 % (2026).

## 3. Gelir vergisi (IR) 2026 — barème salariés

Tranches ANUELLES (G.V.K. art. 103, tarife ücretliler) :

| Revenu imposable annuel (TRY) | Taux |
|---|---|
| 0 – 190 000 | 15 % |
| 190 001 – 400 000 | 20 % |
| 400 001 – 1 500 000 | 27 % |
| 1 500 001 – 5 300 000 | 35 % |
| > 5 300 000 | 40 % |

Application mensuelle : assiette mensuelle (brut − cotisations salarié)
**annualisée × 12** → progressif → **/ 12**.

### Asgari ücret istisnası (exonération SMIC) — loi n° 7346 du 25/12/2022

L'impôt correspondant au SMIC net n'est pas prélevé, quel que soit le
salaire : `IR dû = max(0, IR sur l'assiette totale − IR sur le SMIC net)`.

- SMIC net mensuel 2026 : 33 030 × (1 − 0,14 − 0,01) = **28 075,50 TRY**.
- İstisna mensuelle 2026 : IR sur 28 075,50 × 12 = 336 906 TRY/an =
  190 000 × 15 % + 146 906 × 20 % = 57 881,20 TRY/an → **4 823,43 TRY/mois**.
- Conséquence : tout salaire ≤ SMIC net paie 0 IR ; l'exonération s'arrête
  dès que l'IR brut dépasse 4 823,43 TRY/mois.

## 4. Damga vergisi (taxe de timbre sur salaire)

- Taux : **binde 7,59 = 0,759 %** (D.V.K., 71 Seri No.lu Tebliğ, RG
  31/12/2025).
- Assiette : part du brut **excédant le SMIC** (part ≤ SMIC exonérée depuis
  la loi n° 7346 de 2022) → `damga = max(0, brut − 33 030) × 0,759 %`.
- La damga **n'est pas déductible** de l'assiette du gelir vergisi : elle
  est exposée via le mécanisme de taxe forfaitaire du moteur
  (`calculateBracketTax` / `flatPayrollTaxLabel` = « Damga vergisi (binde
  7,59) ») — ligne de déduction dédiée sur le bulletin, combinée
  additivement à l'IR (défaut `combineMinimumFiscalTax`).

## 5. Heures supplémentaires

- Seuil légal : **45 h/semaine** (İş Kanunu n° 4857, art. 63).
- Majoration : **+50 %** (× 1,5) du salaire horaire (art. 41) — palier
  unique modélisé, `confidenceLevel = pilot`.

## 6. Métadonnées pays

| Champ | Valeur |
|---|---|
| Devise | TRY |
| Langue | tr |
| Fuseau | Europe/Istanbul |
| Repos hebdo | dimanche |
| Cycles paie | mensuel uniquement |

## 7. Vérification experte

Aucune validation mali müşavir à ce jour (niveau `pilot`). Sources publiques
vérifiées le 2026-08-23 : CSGB (asgari ücret 2026), SGK (taux/plafonds 2026),
GİB (barème 2026), Resmî Gazete 31/12/2025 (71 Seri No.lu DVK Tebliği).

## 8. Bulletin & exports

- **Bulletin** : mentions légales TR déjà présentes dans
  `PaySlipPdfGenerator::COUNTRY_LEGAL` (« İş Kanunu uyarınca
  düzenlenmiştir. SGK primleri dahildir. ») — aucun changement requis.
- **Virement** : formats multi-pays `csv_generic` / `sepa_xml` (devise TRY
  résolue via `CountryDefaults`) — couverts par le référentiel générique.
