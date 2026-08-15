# Mini-spécification — Issue #3261

## Objectif

Éviter que les images sociales de la vitrine affichent systématiquement une copie française et des compteurs obsolètes, tout en gardant le rendu arabe compatible avec Satori.

## Correction

`opengraph-image.tsx` expose quatre variantes prérendues via `generateImageMetadata` : `fr`, `en`, `tr` et `ar`. Chaque variante traduit le titre, le sous-titre, les badges et les statistiques. Les compteurs canoniques sont **3 apps mobiles** et **2 apps web** ; le kiosque est présenté comme une surface distincte. La variante arabe utilise `dir="rtl"`, une disposition inversée et une police Noto Sans Arabic locale. `twitter-image.tsx` réexporte la même génération.

## Critères d’acceptation

1. Les routes `/opengraph-image/fr`, `/en`, `/tr` et `/ar` sont générées au build.
2. Les routes Twitter disposent des mêmes quatre variantes.
3. Aucun texte « 5 apps mobiles », « 19 modules metier » ou « 424 endpoints API » ne subsiste dans ces images.
4. Les textes FR, EN, TR et AR sont distincts et l’arabe est rendu en RTL.
5. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3261  
Branche : `fix/3261-localized-social-images`  
Date : 2026-08-15
