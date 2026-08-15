# Mini-spécification — Issue #2909

## Objectif

Harmoniser la communication publique de l’offre Leopardo RH afin que toutes les pages marketing présentent une durée d’essai gratuit de **30 jours** et les noms de plans canoniques **Pilot**, **Operations** et **Enterprise**.

## Contexte

Le catalogue pricing de la vitrine constitue la source de vérité pour les plans et la durée d’essai. Plusieurs CTA, FAQ, données structurées et métadonnées SEO conservaient une durée de 14 jours ou les anciens libellés Starter/Business, ce qui pouvait créer une promesse commerciale incohérente entre les pages et les moteurs de recherche.

## Périmètre

Les changements couvrent les layouts pricing et FAQ, les CTA des pages À propos, Études de cas et Témoignages, le contenu vitrine centralisé, ainsi que la description SEO de la page checkout. Les exemples historiques de documentation et les libellés fonctionnels sans rapport avec l’offre commerciale restent hors périmètre.

## Règles fonctionnelles

1. Toute promesse d’essai gratuit visible dans le périmètre marketing doit annoncer 30 jours.
2. Les plans commerciaux doivent être nommés Pilot, Operations et Enterprise.
3. Les FAQ et données structurées JSON-LD doivent refléter la même promesse que le rendu visible.
4. Aucun changement ne doit modifier les URLs de conversion existantes ni le comportement d’inscription.
5. La logique de sélection du pays et les calculs tenant-level ne sont pas modifiés par cette issue.

## Fichiers concernés

- `front/web/src/app/(landing)/pricing/layout.tsx`
- `front/web/src/app/(landing)/faq/layout.tsx`
- `front/web/src/app/(landing)/about/page.tsx`
- `front/web/src/app/(landing)/case-studies/page.tsx`
- `front/web/src/app/(landing)/testimonials/page.tsx`
- `front/web/src/modules/vitrine/lib/content.ts`
- `front/web/src/modules/vitrine/lib/seo.ts`
- `front/web/src/modules/vitrine/lib/seo-metadata.ts`

## Critères d’acceptation

- Les recherches ciblées sur les pages marketing actives ne retournent plus de promesse « 14 jours ».
- Le JSON-LD FAQ pricing et FAQ générale annonce 30 jours.
- Les métadonnées checkout utilisent Pilot/Operations/Enterprise.
- Les CTA concernés affichent 30 jours sans modifier leurs liens.
- Les tests de type, lint et build de la vitrine passent, ou toute limite d’environnement est documentée dans la PR.
- Une entrée `CHANGELOG.md` décrit la correction.

## Vérification

Exécuter les contrôles ciblés du front web, puis rechercher les anciennes chaînes dans les fichiers marketing actifs. Vérifier également le diff afin de confirmer qu’aucune règle métier, route ou logique de paiement n’a été touchée.

## Plan de retour arrière

Réversion atomique du commit de l’issue #2909. Aucun changement de schéma ni migration de données n’est prévu.

## Trace Spec Kit

Issue : #2909  
Branche : `fix/2909-pricing-trial-copy`  
Date : 2026-08-15

## Statut

Implémentation prête pour revue.
