# Baseline d'exécution — ce que la suite backend prouve réellement

**Date :** 2026-10-21
**Commit de référence :** `main` = `4814220` (+ PR #8813 pour le correctif de `PublicDiscoveryTest`, voir §4)
**Environnement :** PHP 8.4.26, PostgreSQL 15 + PostGIS, `CI=true`, `DB_CONNECTION=pgsql`
**Origine :** l'annexe B de `LEOPARDO_PUBLIC_PHASE1_AUDIT.md` admettait que seule la suite
E2E publique avait tourné. Ce document exécute le reste et consigne les résultats bruts.

---

## 1. Résultats par groupe

| Groupe | Commande | Résultat | Durée mesurée | Statut |
|---|---|---|---|---|
| E2E parcours public | `phpunit tests/Feature/LeopardoPublic/` | `OK (31 tests, 195 assertions)` | 4,6 s | ✅ |
| Paiements (BC-38) | `phpunit tests/Feature/Paiements/` | `OK (22 tests, 64 assertions)` | 2,5 s | ✅ |
| Fil public | `phpunit --filter PublicConversationTest tests/Feature/Notification` | `OK (18 tests, 34 assertions)` | 2,8 s | ✅ |
| Découverte publique | `phpunit --filter PublicDiscoveryTest tests/Feature/Platform` | `Tests: 9, Assertions: 49, **Failures: 1**` | 1,3 s | ❌ → **corrigé**, voir §4 |
| Contrat OpenAPI | `phpunit --filter OpenApiDocs --testsuite=Feature` | `Tests: 9, Assertions: 55, PHPUnit Deprecations: 167` | 2,6 s | ⚠️ dépréciations, **0 échec** |

Les trois premiers groupes ont aussi été rejoués **après** ajout des 9 tests de policies
(PR #8810) : `tests/Feature/Paiements/` → `OK (31 tests, 89 assertions)`.

## 2. Ce que ⚠️ signifie exactement

`OpenApiDocsTest` ne sort pas `OK (...)` mais `Tests: 9, Assertions: 55` : **aucune
assertion n'échoue**, mais PHPUnit remonte 167 dépréciations au niveau notice, donc le
code de sortie est non nul. Ce n'est **pas** un échec de contrat — c'est de la dette de
dépréciation PHPUnit. Elle est consignée telle quelle plutôt que masquée par un
`--display-deprecations=0`, qui aurait fabricé un vert.

Même remarque pour `PublicDiscoveryTest` : **26 dépréciations** s'ajoutaient à l'échec réel
du §4.

## 3. Ce qui n'a pas pu être exécuté

**La suite `--testsuite=Feature` complète n'est pas consignée ici.** Deux tentatives :

1. **En une seule passe** (`phpunit --testsuite=Feature --no-coverage`, timeout 3 h) : le
   processus est resté bloqué **plus de 25 minutes** sur une tranche d'intégration
   (`tests/Feature/AI/`) sans produire de sortie, dans un conteneur à **~750 Mo de RAM**.
   Tué, non repris.
2. **Par tranches** (273 entrées de premier niveau sous `tests/Feature/`, une par
   répertoire/fichier, timeout 240 s chacune) : le balayage complet représentait plusieurs
   heures et n'était pas compatible avec le budget de la session. **Arrêté volontairement**
   après quelques tranches.

**Conséquence à ne pas atténuer :** l'affirmation « toute la suite Feature est verte » n'est
**pas** établie. Ce qui est établi, c'est que les cinq groupes nommés ci-dessus le sont
(quatre ✅ et un ⚠️ sans échec) — dont les trois qui touchent le parcours public et le
BC-38.

**Pourquoi ce n'est pas un test qu'on a désactivé :** aucun test n'a été marqué `skipped`,
`markTestSkipped`, exclu de `phpunit.xml` ou retiré. Les groupes non exécutés le sont par
**épuisement de budget**, pas par choix de confort.

### Comment compléter cette baseline

Une machine avec plus de 2 Go de RAM et un réseau sortant (pour `tests/Feature/AI/`) peut
lancer la suite entière d'une traite :

```bash
cd api
CI=true DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=leopardo_test \
DB_USERNAME=leopardo_user DB_PASSWORD=leopardo_pass_test DB_CONNECTION=pgsql \
vendor/bin/phpunit --testsuite=Feature --no-coverage
```

Le script par tranches utilisé ici est reproductible : une entrée = un répertoire ou un
fichier de premier niveau, timeout 240 s, sortie TSV `entrée / statut / tests / assertions /
durée / anomalie`.

## 4. Le seul échec trouvé — et ce qu'il révélait

`PublicDiscoveryTest::test_discover_publishes_real_per_type_actions` :

```
Failed asserting that an array contains 'booking'.
Tests: 9, Assertions: 49, Failures: 1.
```

Ce n'était pas un défaut du produit mais **un test resté en arrière après un correctif
volontaire**. #8795 avait retiré l'action `booking` fantôme de `RestaurantDiscoverableProvider`
— aucun contrat public de réservation n'existe (`RestaurantReservationController` n'est
monté que dans le groupe `auth:sanctum`). L'assertion, elle, continuait d'**exiger** cette
capacité : le test contredisait le code, et `main` était rouge sans que personne ne le voie.

Correctif (PR #8813) : l'assertion devient **négative et nommée**, donc elle protège
désormais la propriété (`assertNotContains('booking', ...)` avec le motif « P3-01 n'est pas
livré ») au lieu de l'inverser.

```
avant : Tests: 9, Assertions: 49, Failures: 1
après : Tests: 9, Assertions: 53
```

**Portée du constat :** il existait parce que personne n'exécutait ce groupe. C'est la
justification même de ce document.

## 5. Ce que la baseline ne prouve pas

- **Aucune couverture** n'est mesurée ici (`--no-coverage`, volontairement : la porte de
  couverture est lente et son échec n'a pas de rapport avec un correctif).
- **Aucun build mobile** n'est exécuté (ni Flutter ni Android SDK dans l'environnement) ;
  le seul signal mobile est le job CI `Mobile smoke`.
- **Playwright n'est pas dans ce document** : il est couvert séparément (10/10,
  `--workers=1`, §13.6 et §13.8 du rapport Phase 1).
- Les **durées** sont celles d'un conteneur à mémoire contrainte : elles ne prédisent pas
  celles de la CI.
