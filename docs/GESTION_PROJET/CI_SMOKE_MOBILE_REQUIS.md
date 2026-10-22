# Faut-il rendre le build mobile de fumée bloquant ?

**Date :** 2026-10-21
**Statut :** proposition — **la protection de `main` n'est pas modifiée**
**Contexte :** `docs/audits/LEOPARDO_PUBLIC_PHASE1_AUDIT.md` §13.7 et §13.9 (constats P0-01 / P2-06)

---

## 1. Le problème

`mobile-apps-ci.yml` filtre ses déclencheurs sur `front/mobile_apps/**`. Sur une PR
**backend**, les 8 jobs `Build Debug` n'apparaissent donc pas « `skipped` » : ils
**n'existent pas**. Une régression de compilation mobile introduite par un changement
d'API partagé reste **invisible jusqu'à la distribution Firebase sur `main`**.

`.github/workflows/mobile-smoke.yml` comble ce trou : **un** APK debug
(`leopardo_employee`) construit sur **chaque** PR vers `main`, sans filtre de chemin.

## 2. Ce que le smoke a réellement prouvé

Il n'est pas resté théorique. Sur son premier passage réel il a échoué **immédiatement**,
sur une régression préexistante que personne ne voyait :

```
uses-sdk:minSdkVersion 24 cannot be smaller than version 26 declared in library [:sentry_flutter]
BUILD FAILED in 3m 53s
```

C'est exactement ce que décrit P0-01. **#8776** (`minSdk = 26` sur les 8 apps) l'a
corrigée, et le workflow est passé au vert sur `bc038b01f`.

## 3. Le trou restant : le smoke n'est pas dans les checks requis

Les 4 checks qui conditionnent un merge sur `main` sont :

| Check requis | Rôle |
|---|---|
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | typage backend |
| `Module Structure Validator` | frontières de modules |
| `Frontend — ESLint + TypeScript` | typage/lint front |
| `actionlint` | validité des workflows |

`Mobile smoke` **n'y est pas**. Conséquence concrète : une PR peut être **rouge sur le
mobile** et être mergée quand même. Le statut est **informatif**, pas bloquant.

## 4. L'option « rendre bloquant » — coût et risque

### Coût mesuré

| Élément | Valeur observée |
|---|---|
| Durée d'un run (succès) | ~4 min |
| Durée d'un run (échec de compilation) | 3 min 53 s |
| Runner | Ubuntu standard, SDK Android + Gradle cache |
| Jobs lancés par PR | **1** (les 8 builds lourds restent filtrés) |

Le coût est **borné** : un seul job, quelques minutes, aucune matrice. Ce n'est pas la
dépense des 8 builds complets.

### Risques à arbitrer

1. **Dépendance à un cache Gradle distant.** Un incident de cache (ou un dépôt Maven
   indisponible) fait échouer un job **bloquant** pour une raison qui n'a rien à voir avec
   la PR. C'est le risque principal : on transforme une panne d'infrastructure en blocage
   de tous les merges.
2. **Aucun rapport avec la majorité des PR.** Sur une PR de documentation ou de backend
   pur, le job ajoute ~4 min au chemin critique sans rien prouver sur le diff lui-même —
   il prouve seulement que l'API **n'a pas cassé le mobile**, ce qui est précisément
   l'intention.
3. **Seuil de flakiness inconnu à ce jour.** Le workflow n'a qu'un historique court
   (créé le 2026-10-20). Le rendre bloquant avant d'avoir observé, disons, 20 passages
   verts consécutifs, c'est prendre le risque d'un blocage dont on ne saura pas dire s'il
   est un vrai signal.

### Bénéfice

Le seul qui compte : **une régression mobile introduite par un changement partagé ne peut
plus atteindre `main`**. C'est exactement le trou P0-01, et c'est plus grave qu'un merge
retardé de 4 minutes.

## 5. Recommandation

**Rendre `Mobile smoke` requis, mais après une fenêtre d'observation.**

1. **Maintenant** : garder le job informatif (statu quo de cette PR), et **consigner** son
   résultat dans la description de chaque PR qui touche `api/`, `shared/` ou
   `front/mobile_apps/` — un humain voit le rouge même s'il ne bloque pas.
2. **Après ~20 passages verts consécutifs** : ajouter `Mobile smoke` aux checks requis.
   À ce moment-là, un échec a une probabilité faible d'être infrastructurel.
3. **Si le job devient flaky** (échecs sans changement de code) : ne pas le retirer des
   checks requis sans le remplacer — préférer un `retry` ciblé sur l'étape Gradle, qui
   absorbe une panne de cache sans masquer une vraie erreur de compilation.

### Ce que cette PR ne fait pas

Elle **ne modifie pas** la protection de `main` : changer les checks requis est une
décision de paramétrage du dépôt qui appartient au propriétaire, et elle est sans effet
rétroactif sur les PR ouvertes. Le présent document fournit les chiffres et le risque ;
l'acte reste manuel.

## 6. Comment vérifier l'état actuel

```bash
# checks requis réellement appliqués
curl -s -H "Authorization: Bearer $TOKEN" \
  https://api.github.com/repos/kitokoh/leopardo-hr/branches/main/protection \
  | python3 -c "import json,sys; print(json.load(sys.stdin)['required_status_checks']['contexts'])"
```

Le résultat ne doit contenir **aucun** `Mobile smoke` — c'est la constatation, pas un
objectif.
