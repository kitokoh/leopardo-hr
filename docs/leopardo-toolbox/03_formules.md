# LEOPARDO TOOLBOX — Documentation des formules

> Chaque formule est implémentée dans `front/web/src/modules/toolbox/tools/<slug>/logic.ts`
> et couverte par `logic.test.ts`. Unités, hypothèses et références sont
> documentées ci-dessous **et** en commentaire d'en-tête du fichier de logique.

## Règles transverses

- **Entrées invalides** : toute fonction retourne `null` (jamais `NaN`/`Infinity`)
  si une entrée est non finie (`NaN`, `Infinity`) ou hors domaine.
- **Division par zéro** : jamais effectuée — `safeDivide()` retourne `null`.
- **Arrondi** : le calcul reste en pleine précision ; l'arrondi n'a lieu qu'à
  l'affichage (ou en sortie de fonction pour les montants, à 2 décimales).
- **Avertissements** : tout résultat estimatif ou théorique est signalé dans
  l'interface (voir `data/catalog.ts` → `warnings`).

---

## 1. IMC — Indice de masse corporelle (`imc`)

**Formule** : $$\text{IMC} = \frac{\text{poids (kg)}}{\text{taille (m)}^2}$$

**Unités** : poids en kilogrammes, taille en centimètres (convertie en mètres).

**Classification OMS** :

| IMC | Interprétation |
|---|---|
| < 18,5 | Insuffisance pondérale |
| 18,5 – 24,9 | Corpulence normale |
| 25 – 29,9 | Surpoids |
| ≥ 30 | Obésité |

**Hypothèses / limites** : ne tient pas compte de la masse musculaire, de l'âge
ni de la morphologie. Indicateur de dépistage, **pas un diagnostic médical**.

**Domaine** : poids > 0, taille > 0. Sinon `null`.

**Référence** : OMS — https://www.who.int/health-topics/obesity

**Cas de test** : 70 kg / 175 cm → 22,9 (normal) ; 100 kg / 200 cm → 25,0 (surpoids) ;
50 kg / 180 cm → 15,4 (insuffisance) ; 120 kg / 170 cm → 41,5 (obésité).

---

## 2. TVA — calcul HT / TTC (`tva`)

**Formules** :

- HT → TTC : $$\text{TTC} = \text{HT} \times (1 + \text{taux})$$
- TTC → HT : $$\text{HT} = \frac{\text{TTC}}{1 + \text{taux}}$$
- Dans les deux cas : $$\text{TVA} = \text{TTC} - \text{HT}$$

**Unités** : montants dans une devise quelconque (calcul neutre), taux en
pourcentage (20 = 20 %).

**Hypothèses** : taux unique appliqué au montant ; aucun arrondi intermédiaire.

**Domaine** : montant ≥ 0, taux ≥ 0. Le diviseur `(1 + taux)` est toujours ≥ 1 :
aucune division par zéro possible.

**Référence** : impots.gouv.fr — taux de TVA français (20 %, 10 %, 5,5 %, 2,1 %).

**Cas de test** : 100 HT à 20 % → TTC 120, TVA 20 ; 120 TTC à 20 % → HT 100 ;
100 HT à 5,5 % → TTC 105,5.

---

## 3. Salaire brut → net — ESTIMATION (`salaire-net`)

**Formule** : $$\text{net} \approx \text{brut} \times (1 - \text{taux de cotisations})$$

**Unités** : montants mensuels dans une devise quelconque, taux en pourcentage.

**Hypothèses** : le taux de cotisations est **fourni par l'utilisateur**. Aucun
barème fiscal n'est codé en dur : ce module ne calcule **pas** une paie légale.

⚠️ **Résultat indicatif** — ne remplace pas un bulletin de paie officiel.

**Domaine** : brut ≥ 0, taux ∈ [0, 100]. Sinon `null`.

**Cas de test** : 3 000 € à 22 % → net 2 340 €, cotisations 660 € ; taux 0 % → net = brut ;
taux 100 % → net 0.

---

## 4. Convertisseur d'unités (`convertisseur-unites`)

**Principe** : chaque unité porte un facteur vers l'unité de base de sa catégorie.

$$\text{résultat} = \frac{\text{valeur} \times \text{facteur(source)}}{\text{facteur(cible)}}$$

**Facteurs exacts** (définitions SI / internationales) :

| Catégorie | Base | Exemples exacts |
|---|---|---|
| Longueur | mètre | 1 in = 0,0254 m ; 1 mi = 1 609,344 m |
| Masse | kilogramme | 1 lb = 0,45359237 kg ; 1 oz = 0,028349523125 kg |
| Volume | litre | 1 gal US = 3,785411784 L ; 1 gal UK = 4,54609 L |
| Surface | m² | 1 acre = 4 046,8564224 m² ; 1 ha = 10 000 m² |
| Vitesse | m/s | 1 mph = 0,44704 m/s ; 1 kn = 1852/3600 m/s |

**Température** (formules **affines**, pas un simple facteur) :

$$F = C \times \tfrac{9}{5} + 32 \qquad K = C + 273{,}15$$

**Domaine** : valeur finie ; unités connues et de même catégorie. Sinon `null`.

**Référence** : BIPM — brochure SI.

**Cas de test** : 1 in = 2,54 cm ; 1 lb = 0,45359237 kg ; 36 km/h = 10 m/s ;
0 °C = 32 °F ; 100 °C = 212 °F ; −40 °C = −40 °F ; 0 °C = 273,15 K.

---

## 5. Pourcentages et évolution (`pourcentage`)

**Formules** :

- Pourcentage d'une valeur : $$A \times \frac{B}{100}$$
- Évolution de A vers B : $$\frac{B - A}{A} \times 100$$
- Remise de B % sur A : $$A \times \left(1 - \frac{B}{100}\right)$$

**Unités** : A et B sont des nombres ; le résultat est un nombre (points de
pourcentage pour l'évolution, valeur pour les autres modes).

**Domaine** : A et B finis. Le mode `evolution` avec A = 0 retourne `null`
(division par zéro).

**Cas de test** : 20 % de 250 = 50 ; 100 → 150 = +50 % ; 200 → 150 = −25 % ;
100 avec 30 % de remise = 70 ; évolution depuis 0 → `null`.

---

# Deuxième lot d'outils

## 6. Règle de trois (`regle-de-trois`)

**Formules** :

- Proportionnalité **directe** : si A correspond à B, alors C correspond à
  $$X = \frac{B \times C}{A}$$
- Proportionnalité **inverse** : si A correspond à B, alors C correspond à
  $$X = \frac{A \times B}{C}$$

**Unités** : A, B et C sont des nombres dans des unités quelconques ; le
résultat est exprimé dans l'unité de B. Aucune conversion d'unité n'est faite.

**Hypothèses** : proportionnalité directe **ou** inverse, jamais les deux à la
fois. Le mode est choisi explicitement par l'utilisateur.

**Domaine** : A, B, C finis. Directe avec A = 0 → `null` (division par zéro) ;
inverse avec C = 0 → `null`.

**Cas de test** : directe 3 → 12, donc 5 → 20 ; 2 kg → 14 €, donc 3 kg → 21 € ;
inverse 2 ouvriers → 6 jours, donc 3 ouvriers → 4 jours ; directe avec A = 0 → `null`.

---

## 7. Surfaces et volumes (`surface-volume`)

Toutes les longueurs sont dans la **même unité** ; l'aire est en unité², le
volume en unité³. Aucune conversion n'est faite ici.

| Forme | Aire | Volume |
|---|---|---|
| Carré | $$a^2$$ | — |
| Rectangle | $$L \times l$$ | — |
| Triangle | $$\frac{b \times h}{2}$$ | — |
| Cercle | $$\pi r^2$$ | — |
| Cube | $$6a^2$$ | $$a^3$$ |
| Pavé droit | $$2(Ll + Lh + lh)$$ | $$L \times l \times h$$ |
| Cylindre | $$2\pi r h$$ (latérale) | $$\pi r^2 h$$ |
| Sphère | $$4\pi r^2$$ | $$\frac{4}{3}\pi r^3$$ |

**Hypothèses** : dimensions strictement positives (une dimension nulle ou
négative n'a pas de sens géométrique → `null`).

⚠️ **Résultat théorique** — ne constitue ni un métré officiel ni une validation
de sécurité.

**Domaine** : toutes les dimensions requises finies et > 0. Sinon `null`.

**Cas de test** : carré côté 4 → aire 16 ; pavé 2 × 3 × 4 → volume 24, aire 52 ;
cylindre r = 2, h = 5 → volume 20π ; sphère r = 3 → volume 36π, aire 36π ;
dimension nulle ou négative → `null`.

---

## 8. CBM et poids volumétrique (`cbm-poids-volumetrique`)

**Formules** :

- CBM d'un colis : $$\text{CBM} = \frac{L \times l \times H}{10^6}$$ (dimensions en cm → m³)
- CBM total : $$\text{CBM} \times \text{quantité}$$
- Poids volumétrique : $$\text{CBM} \times \text{facteur (kg/m}^3\text{)}$$
- Poids taxable : $$\max(\text{poids réel},\ \text{poids volumétrique})$$

**Facteurs de conversion** (kg/m³) — valeurs de marché, à vérifier auprès du
transporteur :

| Mode | Facteur | Équivalent |
|---|---|---|
| Aérien | 167 | 6 000 cm³/kg |
| Routier | 333 | 3 000 cm³/kg |
| Maritime | 1000 | 1 m³ = 1 tonne |

**Unités** : dimensions en centimètres, poids en kilogrammes, CBM en m³.

**Hypothèses** : dimensions > 0, quantité ≥ 1, poids réel ≥ 0.

⚠️ **Résultat indicatif** — le poids taxable réel dépend du contrat transporteur
(arrondis, palettes, poids minimum facturable). Ne remplace pas un devis.

**Domaine** : dimensions finies et > 0 ; quantité finie ≥ 1 ; poids fini ≥ 0.
Sinon `null`.

**Cas de test** : 100 × 50 × 40 cm → 0,2 m³ ; ×10 colis → 2 m³ ; aérien → 33,4 kg ;
routier → 66,6 kg ; maritime 1 m³ → 1000 kg ; poids réel 50 kg > 33,4 kg → taxable 50 kg.

---

## 9. Marge et prix de vente (`marge-prix-vente`)

**Formules** :

- Marge (valeur) : $$\text{PV} - \text{PA}$$
- Taux de **marge** (rapporté au coût) : $$\frac{\text{PV} - \text{PA}}{\text{PA}} \times 100$$
- Taux de **marque** (rapporté au prix de vente) : $$\frac{\text{PV} - \text{PA}}{\text{PV}} \times 100$$
- Coefficient : $$\frac{\text{PV}}{\text{PA}}$$
- PV depuis un taux de marge : $$\text{PA} \times \left(1 + \frac{\text{taux}}{100}\right)$$
- PV depuis un taux de marque : $$\frac{\text{PA}}{1 - \frac{\text{taux}}{100}}$$

**Unités** : montants dans une même devise ; taux en points de pourcentage.

**Hypothèses** : PA et PV ≥ 0. Le taux de marge et le taux de marque sont deux
notions **distinctes** — ne pas les confondre.

**Domaine** : PA = 0 → taux de marge et coefficient `null` ; PV = 0 → taux de
marque `null` ; taux de marque ≥ 100 % → PV `null` (prix infini ou négatif).

**Cas de test** : PA 100, PV 130 → marge 30, taux de marge 30 %, taux de marque
23,08 %, coefficient 1,3 ; PA 100 + 30 % de marge → PV 130 ; PA 100 + 20 % de
marque → PV 125 ; taux de marque 100 % → `null`.

---

## 10. TVA multi-taux (`tva-multi-taux`)

**Formules** (par ligne, puis regroupement par taux) :

- TVA d'une ligne : $$\text{HT} \times \frac{\text{taux}}{100}$$
- Base HT par taux : $$\sum \text{HT des lignes de ce taux}$$
- Total TTC : $$\text{total HT} + \text{total TVA}$$

**Unités** : montants dans une même devise ; taux en points de pourcentage.

**Hypothèses** : montants HT ≥ 0, taux ≥ 0. Les lignes sont regroupées par
valeur **exacte** de taux (20 et 20,0 forment une seule ligne), comme sur une
facture française.

⚠️ **Résultat indicatif** — ne remplace pas une facture ni une déclaration de TVA.

**Domaine** : liste non vide ; chaque montant fini ≥ 0 ; chaque taux fini ≥ 0.
Une seule ligne invalide → `null` pour tout le récapitulatif.

**Cas de test** : 1000 HT à 20 % → TVA 200, TTC 1200 ; deux lignes à 20 %
(100 + 200) → base 300, TVA 60 ; 200 à 5,5 % + 50 à 10 % + 100 à 20 % → TVA 36,
HT 350, TTC 386 ; taux 0 % → TVA 0 ; liste vide → `null`.
