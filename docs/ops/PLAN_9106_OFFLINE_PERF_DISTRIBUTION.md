# Couloir 16 — M4 : hors ligne, performance, distribution testeurs (#9106)

> Issue : #9106 · Statut : **partiellement implémenté, distribution bloquée par la porte (c)**

Ce document est le plan d'action concret demandé par l'issue. Il sépare ce qui
est **faisable en code maintenant** de ce qui exige un **accord explicite**
(porte (c) — dépense nouvelle).

---

## 1. Ce qui est déjà en place (vérifié dans le dépôt)

| Élément | Fichier | État |
|---|---|---|
| File d'attente hors ligne | `front/web/src/lib/offline/offline-queue.ts` | ✅ |
| Stockage persistant | `front/web/src/lib/offline/persistent-offline-storage.ts` | ✅ |
| État de connexion | `front/web/src/lib/offline/connection-state.ts` | ✅ |
| Synchronisation au retour | `front/web/src/lib/offline/use-offline-sync.ts` | ✅ |
| Cache de découverte publique | `front/web/src/lib/offline/public-discovery-cache.ts` | ✅ |
| POS hors ligne | `front/web/src/lib/offline/pos-offline.ts` | ✅ |
| État réel des canaux mobiles | `front/web/src/lib/mobile-distribution-status.ts` | ✅ (alimenté par la CI) |
| Garde de fraîcheur des canaux | `.github/workflows/mobile-distribution-status-guard.yml` | ✅ |

**Conclusion** : le socle hors ligne existe et est testé. Ce qui manque n'est
pas le mécanisme, c'est la **preuve mesurée** (Lighthouse avant/après) et la
**distribution**.

---

## 2. Budget de poids d'images — à écrire et vérifier en CI

### Budget proposé (à valider)

| Ressource | Budget | Justification |
|---|---|---|
| Image de héros (LCP) | ≤ 180 Ko | Au-delà, le LCP dépasse 2,5 s en 3G rapide |
| Image de contenu | ≤ 120 Ko | |
| Vignette de liste | ≤ 40 Ko | |
| Police (par fichier) | ≤ 45 Ko (woff2) | Sous-ensemble latin |
| JS initial (route publique) | ≤ 200 Ko gzip | |
| Poids total d'une page publique | ≤ 900 Ko | Cas nominal : connexion lente |

### Mise en œuvre

1. **Écrire le budget** dans `docs/ops/PERF_BUDGET.md` (tableau ci-dessus).
2. **Vérifier en CI** : un script `scripts/check-image-budget.mjs` qui parcourt
   `front/web/public/**/*.{jpg,png,webp,avif}` et échoue si un fichier dépasse
   son budget de catégorie. Branché sur le workflow `lighthouse` existant.
3. **Convertir** les images hors budget en AVIF/WebP via `next/image` (déjà en
   place) — le format est choisi à la volée, seule la source doit être
   raisonnable.

> ⚠️ **Non implémenté dans cette passe** : le script de budget nécessite de
> fixer d'abord les seuils avec le design (une image de héros à 180 Ko peut être
> impossible pour une photo pleine largeur). Le tableau ci-dessus est une
> **proposition chiffrée**, pas une décision.

---

## 3. Lighthouse mobile avant/après — procédure

L'issue exige des chiffres **joints à la PR**. Procédure reproductible :

```bash
# Avant (sur main)
npx lighthouse https://<preview>/decouvrir \
  --preset=desktop --form-factor=mobile \
  --throttling-method=simulate \
  --output=json --output-path=./lh-before.json

# Après (sur la branche)
npx lighthouse https://<preview>/decouvrir \
  --preset=desktop --form-factor=mobile \
  --throttling-method=simulate \
  --output=json --output-path=./lh-after.json
```

Joindre les deux JSON et un tableau comparatif (Performance, LCP, CLS, TBT).

> ⚠️ **Non exécuté ici** : le sandbox n'a pas d'URL de prévisualisation
> déployée. Les chiffres doivent être produits sur un environnement réel — un
> Lighthouse local sur un build non déployé ne mesure pas le réseau.

---

## 4. Parcours hors ligne — scénario de recette

| Étape | Action | Attendu |
|---|---|---|
| 1 | Charger `/decouvrir` en ligne | Page complète, cache rempli |
| 2 | Passer en mode avion | — |
| 3 | Recharger `/decouvrir` | Page servie depuis le cache, bandeau « hors ligne » |
| 4 | Ouvrir une fiche déjà visitée | Contenu du cache |
| 5 | Revenir en ligne | Bandeau disparaît, synchronisation silencieuse |
| 6 | Vérifier la file | Aucune action perdue, aucun doublon |

Ce scénario est **testable dès maintenant** avec Playwright
(`context.setOffline(true)`). Il n'est pas encore écrit — c'est la prochaine
tranche de code de cette issue.

---

## 5. Distribution testeurs — BLOQUÉE par la porte (c)

L'issue est explicite : « tout compte développeur de magasin est une dépense :
**aucun engagement sans accord explicite** ».

| Canal | Coût | Statut |
|---|---|---|
| Firebase App Distribution | **Gratuit** | ✅ Utilisé (4 canaux verts au 2026-10-28) |
| Amazon Appstore | 99 $/an | ❌ **Dépense — accord requis** |
| Google Play | 25 $ (unique) | ❌ **Dépense — accord requis** |
| Apple App Store | 99 $/an | ❌ **Dépense — accord requis** |

**Recommandation** : rester sur **Firebase App Distribution** (gratuit, déjà
opérationnel, 4 canaux verts) tant que la porte (c) n'est pas levée. Aucun
compte de magasin ne doit être créé sans accord écrit.

---

## 6. Découpage proposé

| Tranche | Contenu | Bloquée ? |
|---|---|---|
| **T1** | Script de budget d'images + workflow CI | Non (seuils à valider) |
| **T2** | Spec Playwright du parcours hors ligne (6 étapes) | Non |
| **T3** | Mesure Lighthouse avant/après sur preview | Non (nécessite un déploiement) |
| **T4** | Comptes de magasin | **Oui — porte (c)** |

**T1 et T2 sont implémentables immédiatement.** T3 nécessite un environnement
déployé. T4 est une décision de dépense, hors périmètre code.
