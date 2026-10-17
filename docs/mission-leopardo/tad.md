# TAD — Architecture technique cible

## 1. Principe directeur

**Aucune modification architecturale n'est nécessaire.** La vitrine reste un client Next.js 16 (App Router) qui consomme les données de présentation existantes. La mission est un chantier de **présentation** : elle ajoute des composants dans `src/modules/vitrine`, ne touche ni l'API, ni la base, ni les routes.

## 2. Couches

```
app/(landing)/
  page.tsx                  ← composition (inchangée pour l'accueil)
  packs/page.tsx            ← + carte « Trade » (offres)
  trade/…                   ← INCHANGÉ (route, layout, tests)

modules/vitrine/
  components/
    hero/
      HeroBusinessOS3D.tsx  ← moteur WebGL (modifié : onSelectModule)
      HeroScene3D.tsx       ← sélection WebGL / repli 2D (modifié)
      HeroScene3DPanel.tsx  ← wiring constellation + fiche (modifié)
      SolutionCard.tsx      ← NOUVEAU : fiche interactive
      GeoBackdrop.tsx       ← NOUVEAU : cartes géographiques animées
      HeroSolutionsCTA.tsx  ← NOUVEAU : CTA sous la constellation
      FranceConstellation.tsx ← conservé (fond d'autres pages)
    sections/HeroSection.tsx ← layout stage (modifié : slide CTA + fond géo)
    Navbar.tsx               ← « Trade » retiré de la barre
  data/
    solution-catalog.ts       ← NOUVEAU : source unique des solutions
    geo/map-paths.ts          ← NOUVEAU : tracés géographiques (généré)
    pack-pages.ts             ← INCHANGÉ : réutilisé par les fiches
  lib/                        ← inchangé (locale-href, vitrine-locale)
```

## 3. Flux de données (une seule source)

```
PACK_PAGES (data/pack-pages.ts)          ─┐
                                          ├─→ SOLUTION_CATALOG (data/solution-catalog.ts)
Copie courte des autres environnements  ─┘        │
                                                   ├─→ solutionLabel()   → étiquettes des étoiles
                                                   └─→ getSolutionDetail() → contenu des fiches
```

- Les verticales déjà documentées (**agences de voyage, stations-service, écoles, cagnottes, VTC**) et **Restaurants** lisent `PACK_PAGES` : **aucune donnée métier dupliquée**.
- Les environnements sans page catalogue (**International Trade OS, HR & Payroll OS, Accounting OS, Documents OS**) portent une copie courte, rangée sous `data/` comme `pack-pages.ts` (chemin exempté par la garde PA2-I18N-014).
- `getSolutionDetail()` retourne `null` pour un identifiant inconnu (fail-closed, comme `pack-signup`).

## 4. Sélection WebGL et repli

Chaîne inchangée, seul le **contrat d'interaction** change :

```
HeroScene3DPanel
  └─ HeroScene3D        (ssr:false, requestIdleCallback, hasWebGL, saveData, reduced-motion)
       ├─ HeroBusinessOS3D  (three.js)  ── onSelectModule(index) ──┐
       └─ ParticleField     (repli 2D, décor pur)                  │
                                                                   ▼
                                                       setSelected(SOLUTIONS[index].id)
                                                                   │
                                                                   ▼
                                                             <SolutionCard/>
```

Le repli 2D reste **non interactif** (décor). La compréhension de l'offre ne dépend donc jamais de la 3D : elle est portée par la navbar, les cartes de verticales, le hub `/packs`, le CTA sous la constellation et le footer.

## 5. Fond géographique

- `data/geo/map-paths.ts` — tracés SVG **générés hors ligne** à partir de Natural Earth 1:110m (domaine public). Générateur : `tools/cartography/make_maps.py` (voir le fichier de génération pour la commande).
- Frontières partagées supprimées par union (`shapely.unary_union`) : le continent africain est **un seul** tracé, pas 51 polygones juxtaposés.
- Projection équirectangulaire simple, centrée, dans un `viewBox 0 0 100 100`, ratios préservés.
- `GeoBackdrop` empile les 5 cartes et fait varier l'opacité (fondu croisé CSS). **Aucune nouvelle dépendance** : ni d3, ni topojson, ni libellé de carte à l'exécution.

## 6. Décisions

| Décision | Justification |
|---|---|
| Option A (transition successive) pour les cartes | La constellation reste l'élément unique et dominant ; une composition à 3 cartes (Option B) multiplierait les couches concurrentes derrière les étoiles. |
| SVG statique, rotation en CSS/état | Coût CPU quasi nul, aucune frame three.js supplémentaire ; `will-change` limité à l'opacité. |
| Fiche rendue par le parent (`HeroScene3DPanel`) | L'état de sélection vit au-dessus de la scène ; le canvas reste sans dépendance à React. |
| Catalogue unique | Évite la divergence entre les 3 surfaces qui parlent des mêmes solutions (constellation, hub packs, fiches). |
| `FranceConstellation` conservée | Composant encore monté par d'autres pages ; le supprimer sortirait du périmètre. |
