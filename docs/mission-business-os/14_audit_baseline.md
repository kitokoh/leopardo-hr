# 14 — Baseline CI de `main` (état initial vérifié)

**Commit audité :** `e9c5f986c` (HEAD de `main` au démarrage de la mission)
**Méthode :** `GET /repos/kitokoh/leopardo-hr/commits/e9c5f986c/check-runs?per_page=100`
**Résultat brut :** 104 check-runs — **69 success · 17 failure · 9 skipped · 4 cancelled · 1 in_progress**

Ce document est la **référence de non-régression**. Tout échec listé ici est
**préexistant** : il ne peut pas être imputé à la mission.

---

## 1. Échecs préexistants (17)

### 1.1 Analyse Dart / Flutter (4)

| Check | Nature |
|---|---|
| `Analyze leopardo_employee` | `flutter analyze` en échec |
| `Analyze leopardo_hr` | `flutter analyze` en échec |
| `Analyze leopardo_manager` | `flutter analyze` en échec |
| `Analyze leopardo_platform_admin` | `flutter analyze` en échec |

### 1.2 Vérification desktop (8)

| Check | Nature |
|---|---|
| `Desktop verify leopardo_employee (macos-latest)` | build desktop en échec |
| `Desktop verify leopardo_employee (windows-latest)` | build desktop en échec |
| `Desktop verify leopardo_hr (macos-latest)` | build desktop en échec |
| `Desktop verify leopardo_hr (windows-latest)` | build desktop en échec |
| `Desktop verify leopardo_manager (macos-latest)` | build desktop en échec |
| `Desktop verify leopardo_manager (windows-latest)` | build desktop en échec |
| `Desktop verify leopardo_platform_admin (macos-latest)` | build desktop en échec |
| `Desktop verify leopardo_platform_admin (windows-latest)` | build desktop en échec |

### 1.3 Distribution Firebase (4)

| Check | Nature | Conséquence produit |
|---|---|---|
| `Distribute employee to Firebase` | distribution en échec | canal « build testeurs » non opérationnel |
| `Distribute hr to Firebase` | distribution en échec | idem |
| `Distribute manager to Firebase` | distribution en échec | idem |
| `Distribute platform_admin to Firebase` | distribution en échec | idem |

**Impact direct sur la mission :** le centre « Mes logiciels » déclare aujourd'hui un canal
« accès pilote » pour ces applications alors que la distribution échoue. C'est l'écart
**A-05** — la requalification du statut ne dépend pas de cette mission, mais la promesse
affichée doit refléter l'état réel.

### 1.4 Rattrapage de production (1)

| Check | Nature |
|---|---|
| `Rattraper la prod Render si en retard sur main (#8092)` | job de synchronisation prod en échec |

---

## 2. Checks annulés (4)

`Rapport d'état des checks (#7480)` ×4 — annulés, non concluants. À ignorer dans la
comparaison.

---

## 3. Checks en cours au moment de la mesure (1)

`Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` — non conclu au moment du relevé. **Ce n'est
pas un échec** ; il doit être revérifié sur le commit final.

---

## 4. Checks requis par la protection de branche de `main`

| Check requis |
|---|
| `PHPStan Strict` |
| `Module Structure Validator` |
| `Frontend ESLint + TypeScript` |
| `actionlint` |

**Statut de ces 4 checks sur `e9c5f986c` : verts** (absents de la liste d'échecs).
`enforce_admins = true` : un administrateur ne peut pas fusionner sans ces checks.

---

## 5. Ce que cela implique pour la mission

1. **On ne peut pas exiger une CI entièrement verte sur la branche de mission** : 17 échecs
   sont structurels. L'objectif est **« aucun nouvel échec »**, pas « tout vert ».
2. La comparaison se fait **liste contre liste**, pas total contre total : un total stable
   avec une liste différente = régression.
3. Les 4 checks requis doivent rester verts — c'est la condition réelle de fusion.
4. Le check `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` doit être relevé **sur le commit
   final** avant toute fusion ; un `in_progress` n'autorise pas une conclusion.

---

## 6. Protocole de comparaison (à appliquer avant fusion)

```bash
# 1. État de la branche de mission
curl -s -H "Authorization: Bearer $GH" \
  "https://api.github.com/repos/kitokoh/leopardo-hr/commits/$(git rev-parse HEAD)/check-runs?per_page=100" \
  | python3 -c "import json,sys;d=json.load(sys.stdin);[print((r['conclusion'] or 'in_progress'),'|',r['name']) for r in sorted(d['check_runs'],key=lambda x:x['name']) if (r['conclusion'] or '') != 'success']"

# 2. Diff avec la liste ci-dessus (§1). Tout nom absent du §1 est une régression.
```

**Règle d'acceptation :** ensemble des échecs de la branche ⊆ ensemble du §1, et les
4 checks requis verts, et `Backend` conclu.
