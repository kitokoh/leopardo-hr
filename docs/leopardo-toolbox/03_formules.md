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
