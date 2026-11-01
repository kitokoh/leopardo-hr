# Couloir 16 — maquettes 360 px et RTL

Livrables de **conception**, pas de code applicatif. Ces fichiers documentent
les écrans réellement implémentés dans `front/mobile_apps/leopardo_public` et
fixent la mise en page à la largeur de référence du cahier des charges.

| Fichier | Contenu |
|---|---|
| `fiche-vers-contact-360-ltr.svg` | Parcours fiche → contact → conversation, 3 écrans, 360 px, LTR (fr/en/tr) |
| `fiche-vers-contact-360-rtl.svg` | Le même parcours, miroir structurel, arabe (RTL) |

## Pourquoi du SVG écrit à la main plutôt qu'une capture d'écran

Une capture vieillit au premier changement de couleur sans que personne ne s'en
aperçoive : elle montre ce que l'app **était** au jour de la capture. Ici la
maquette **est** le contrat de mise en page — une divergence avec le code se
voit en revue de PR, et le fichier reste lisible en diff.

## Ce que la maquette RTL n'est pas

Ce n'est **pas** un `transform="scale(-1,1)"`. Un miroir mécanique retourne
aussi les photos, le logo et les chiffres — or en arabe les nombres et les
indicatifs (`+90`, `9:41`, `0 / 500`, `1 / 4`) restent écrits de gauche à
droite. Le fichier est donc retourné **structurellement** (positions, ordre des
onglets, ancrage du texte), puis le contenu numérique est réécrit en LTR à
l'intérieur des zones miroir.

C'est exactement le comportement de `Directionality.rtl` dans Flutter, et
c'est pourquoi les deux fichiers doivent rester cohérents : si la maquette LTR
change de structure, la maquette RTL doit être remiroirée à la main, pas
re-générée par transformation.

## Défauts trouvés au contrôle visuel (et corrigés)

Le premier rendu RTL portait deux défauts réels, trouvés en convertissant le
SVG en PNG puis en l'inspectant :

1. **Libellés d'écran illisibles.** `١ · البطاقة` était rendu `10 البطاقة` —
   le chiffre arabe suivi d'un séparateur `·` produit un réordonnancement bidi
   instable. Les libellés sont des **annotations**, pas du contenu
   d'interface : ils sont désormais en `direction="ltr"` explicite.
2. **Libellés frôlant le bord haut** (`y="-8"`) — descendus à `y="0"`.
3. **Cartouche « Léa » à l'étroit** — seconde ligne raccourcie plutôt que
   police réduite sous 8 px (seuil de lisibilité).

Les deux fichiers sont rendus et vérifiés à 1140 px de large avant commit.

## Comment revoir ces maquettes

```bash
pip install cairosvg
python3 -c "import cairosvg; cairosvg.svg2png(
    url='fiche-vers-contact-360-ltr.svg',
    write_to='ltr.png', output_width=1140)"
```

## Ce que ces maquettes ne couvrent pas

Elles couvrent le parcours **fiche → contact → conversation** uniquement, pas
les écrans Accueil (recherche, carte, catégories) ni les onglets
Messages/Activités/Profil, qui sont des coquilles en M1. Elles ne remplacent
pas un test d'intégration Flutter : la cible d'acceptation du couloir reste
« tests d'intégration Flutter Accueil → Fiche → Contact », qui n'est **pas**
livrée ici.
