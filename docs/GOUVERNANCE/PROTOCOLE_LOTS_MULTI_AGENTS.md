# PROTOCOLE_LOTS_MULTI_AGENTS — Lots d'issues & merge sous saturation CI

> RETEX du 2026-09-09 formalisé (issue #7126) : collisions multi-agents sur #7057-#7075,
> file CI saturée ~1 h sans runner, fenêtre sans checks utilisée puis restaurée.
> But : exécuter un lot d'issues sans collision et merger même quand la CI est bloquée,
> **sans affaiblir durablement la protection de `main`**.

## 1. Préparation du lot (anti-collision)

1. Lister les issues ouvertes **non assignées** (assignee = vide) et vérifier qu'aucune
   PR ouverte ni branche (`git ls-remote origin | grep -i <issue>`) ne les référence.
2. Privilégier les issues à faible impact CI (docs, process, templates, scripts locaux) :
   vérifier le diff attendu — pas de suite backend 60 min si évitable.
3. Créer la branche unique `docs/<slug>-<date>` (ou `bc/<code>-<slug>` si même BC) depuis
   `origin/main` à jour, et pousser immédiatement un **claim marker** (`commit --allow-empty`
   listant les issues) pour verrouiller le lot.
4. S'il manque des issues au lot : créer des issues de moisson précises (constat daté +
   actions + critères d'acceptation) ancrées sur `main` réel.

## 2. Implémentation

- 1 commit par issue, message `<type>(<scope>): <résumé> (#issue)`.
- Vérifier localement ce qui est vérifiable (scripts bash/python exécutés, liens markdown,
  YAML parsés, tests rouge/vert démontrés). Ne jamais pousser de changement runtime non vérifié.
- Avant push final : re-fetch `main` ; si `main` a bougé, merger `origin/main` et résoudre
  (conflits fréquents sur AGENTS.md/docs partagés — prendre la version main quand un autre
  agent a déjà livré la même chose, pour éviter les doublons).

## 3. PR & merge sous saturation CI

1. PR unique avec un `Closes #N` par issue dans le **body** (règle #2512 ; PR typée `docs:`
   tolérée sans Closes, mais les issues resteraient ouvertes).
2. Vérifier `mergeable_state` : `clean` → merge normal. `blocked` (checks en file) ou
   `dirty` (main a bougé) → re-synchroniser puis, si la file reste saturée, **merge en
   fenêtre contrôlée** (autorisation fondateur requise, voir §4).
3. Après merge : vérifier issues fermées, supprimer la branche, confirmer que `main`
   contient le commit.

## 4. Fenêtre de merge contrôlée (bypass temporaire)

Réservé aux cas où la file CI est bloquée (saturation > 30-60 min) et avec l'accord du
fondateur. Séquence stricte, fenêtre la plus courte possible (< 30 s) :

1. **Sauvegarder** la protection actuelle : `GET /branches/main/protection` (conserver
   `required_status_checks.contexts`, `enforce_admins`, flags).
2. **Désactiver** : `PUT /branches/main/protection` avec `required_status_checks: null`,
   `enforce_admins: false`.
3. **Merger** la PR (PUT /pulls/<n>/merge), retries courts si état transitoire.
4. **Restaurer** immédiatement (bloc `finally`) la protection exacte ; vérifier ensuite
   `GET /branches/main/protection` : contexts présents, `enforce_admins: true`.

⚠️ Risques : pendant la fenêtre, un autre agent peut merger sans checks (d'où la brièveté) ;
la garde `branch-protection-guard.yml` (horaire) détecte toute dérive résiduelle — la
restauration doit donc être vérifiée, pas supposée.

## 4bis. Clôture d'une PR « couvert par le lot » (issue #7581)

Une clôture « couvert par le lot #N » est une **affirmation de contenu**. Quand elle
est fausse, le correctif disparaît **et** le backlog devient vert à tort — le
« ghost close » que `AGENTS.md` interdit pour les issues, appliqué aux PR (constat
#7531 : `optimizeCss` / `critters` perdu, corrigé seulement par #7565).

Avant de fermer une PR pour ce motif, **mesurer** au lieu d'affirmer :

```bash
bash dev-hub/tools/check-pr-subset-of-lot.sh --lot <branche-du-lot> --pr <branche-de-la-pr>
# sortie 0 : sous-ensemble PROUVÉ (chaque fichier existe dans le lot et chaque
#            ligne ajoutée par la PR y est présente) -> la clôture est sûre
# sortie 1 : NON prouvé, avec la liste des fichiers/lignes manquants
#            -> c'est exactement le texte à citer dans le commentaire de
#               fermeture, ou la raison de ne PAS fermer
```

Coller la sortie de l'outil dans le commentaire de fermeture : la clôture devient
vérifiable par le prochain lecteur, y compris si le lot est ensuite lu par un autre
agent.

## 5. Checklist post-lot

- [ ] Issues toutes fermées ; PR mergée ; branche supprimée
- [ ] Protection `main` restaurée et vérifiée (contexts + enforce_admins)
- [ ] Pas de doublon livré (comparer les fichiers aux PR concurrentes mergées)
- [ ] Aucune PR fermée « couvert par le lot » sans la sortie de
  `check-pr-subset-of-lot.sh` collée dans son commentaire de fermeture (#7581)
- [ ] Leçons du lot consolidées (AGENTS.md / BIBLIOTHEQUE_ERREURS.md / ce protocole)

## 6. Drain de crise — N branches concurrentes, aucun propriétaire

> RETEX du 2026-09-16 (session PM/DevOps, dépôt `kitokoh/leopardo-hr`) : **46 branches**,
> **33 PR ouvertes**, 40 issues, file Actions à ~350 runs `queued`, et **plus aucune PR
> mergeable**. État final : `main` seule, 0 PR ouverte, 4 checks requis verts, une journée.

### 6.1 Quand cette procédure s'applique (et quand elle ne s'applique JAMAIS)

**Elle s'applique** quand les trois conditions sont réunies :

1. **Personne n'est propriétaire** du périmètre : plusieurs agents ont livré le même sujet
   (`fix/7475-*` ×2, `fix/7481-*` ×2, `fix/7420-*` ×2, caméras ×3, deux « drains »…), donc
   aucun arbitrage local n'est possible ;
2. **La protection `strict` verrouille tout** : chaque merge dans `main` rend toutes les
   autres PR « behind », qui doivent repasser un cycle complet — la file ne se vide jamais ;
3. **Le coût CI d'une PR est le facteur limitant** : 25 à 60 runs par PR sur ~14 jobs
   concurrents. Dix-neuf PR coûtent la journée de runners pour un seul résultat.

**Elle ne s'applique PAS** en régime normal, et **elle n'autorise jamais** à :

- merger avec un **check requis rouge** (les 4 checks requis gardent leur rôle : c'est la
  seule qualité mécanique disponible quand on ne peut pas tout relire) ;
- fusionner sans résoudre les conflits **fichier par fichier** ;
- « verdir » un check en réécrivant du code correct : si la garde est fausse, on corrige la
  **garde** + une fixture (doctrine #7482) ;
- oublier que la revue ligne à ligne et la validation **fonctionnelle** (base de données,
  recette) ne sont pas remplacées par ce qui suit. Cette procédure est une sortie de crise,
  pas un mode de travail : le drain suivant se fait PR par PR.

### 6.2 Les huit règles du drain

1. **Un lot, une PR.** Le contenu canonique de toutes les branches retenues part dans une
   seule branche `integration/drain-<date>`.
2. **Un sujet = une implémentation canonique.** Les doublons se ferment avec un commentaire
   qui dit **ce qui est retenu, ce qui est perdu, et pourquoi** (jamais « doublon » sec).
   Rien ne se perd : les idées non retenues sont écrites noir sur blanc sur la PR fermée.
3. **Conflits un par un.** Jamais `-X ours/theirs` global. Pour l'i18n : merge **profond**
   des JSON puis régénération par les synchroniseurs du dépôt (`shared/i18n/sync/*.js`) +
   `validators/validate.js` — `git merge-file --union` produit du JSON **invalide** (voir §6.3).
4. **La file CI se pilote.** Identifier les workflows qui produisent les 4 checks requis
   (ici `Architecture Quality` + `Actionlint`), laisser **ceux-là**, annuler le reste sur la
   PR de drain (non requis, métrologiques, builds lourds), et annuler les runs des branches
   consolidées (travail mort) — en le **documentant** dans la PR.
5. **Reconstituer l'outillage local plutôt qu'attendre.** Rejouer la **commande exacte du
   CI** en session (PHPStan strict, `pint --test`, `tsc`, `eslint`, `jest`) vaut mieux qu'un
   cycle de 15 min : recette dans §6.4.
6. **Rejouer les gardes localement** avant de pousser (`check-migration-basename-collisions.sh`,
   `check-duplicate-schema-create.py`, `check-module-isolation.sh`, …) — elles détectent la
   majorité des rouges **non requis** sans consommer un runner.
7. **Comparer à `main` avant de conclure qu'un rouge est le vôtre.** Un check rouge peut
   être **préexistant** : le mesurer sur `main` (worktree détaché) avant de corriger.
8. **Vérifier en production ce qu'on a corrigé.** Un correctif de gate/CI qui passe tous les
   tests locaux peut échouer sur un état réel non modélisé (voir §6.3, `tests-null`).

### 6.3 Les pièges mesurés (tous rencontrés sur ce drain)

| Symptôme | Cause | Correctif |
|---|---|---|
| 13 à 17 JSON/ARB invalides après résolution de conflits | `git merge-file --union` concatène les deux côtés d'un conflit ligne à ligne : virgules et clés dupliquées dans un tableau JSON | merge **profond** (`json.loads` des deux côtés) puis **régénérer** par `shared/i18n/sync/{sync-backend,sync-web,sync-mobile}.js` ; réserver `--union` aux fichiers texte additifs |
| `versions.json` illisible → les synchroniseurs plantent | l'union l'a cassé ; il est lu **avant** l'écriture des catalogues | restaurer une version valide (`git checkout <ref> -- shared/i18n/versions/versions.json`) **avant** de relancer les syncs |
| Les étages `:1:/:2:/:3:` disparaissent après un `git add` | la résolution a remplacé l'état de conflit | récupérer les deux versions par `git show HEAD:<path>` et `git show <branche>:<path>` |
| ≈ 20 erreurs PHPStan strict « should return array{…, Employee} but returns …Model » | `@extends Factory<Employee>` placé dans un **docblock séparé** : en PHP, seul le **dernier** docblock avant la déclaration compte, l'annotation était ignorée | une seule docblock par déclaration (leçon `EmployeeFactory`, #7499) |
| Un panneau ne se referme jamais au clic | `closeHeaderPanels()` met le panneau à `false` **puis** l'updater fonctionnel `!value` le rouvre **dans le même lot** d'événements | calculer la cible **avant** de fermer les autres : `const next = !open; closeOthers(); setOpen(next)` |
| `Module Structure Validator` rouge : `Core/Tenant -> Modules/Platform` | un modèle de `Core/` importait un enum de `Modules/` | déplacer l'enum **vers `Core/`** (les modules dépendent de Core, jamais l'inverse) — ne **jamais** élargir l'allowlist |
| Mergeability GitHub « dirty » alors que `git merge` local est propre | cache de mergeability périmé sur gros diffs | `git merge origin/main` **dans la branche** + push (remède documenté du dépôt) ; jamais de force-push |
| Aucun run CI créé pour un push (0 run, 2 pushs de suite) | sous charge, GitHub ne crée pas de run pour certains événements `synchronize` | `git commit --allow-empty -m "ci: nudge"` + push ; si rien, merger `origin/main` et pousser ; en dernier recours, documenter et vérifier localement |
| `Deploy gate verdict` rouge ~30 min après un merge `api/**` | budget de polling (30 min) **plus court** que le timeout du job backend (300 min) : le gate lisait un `timeout` comme une indécision | distinguer **différé** (des runs requis tournent encore → `pending`, non fatal) d'une **indécision** (#7559) |
| `gate_outcome=tests-null` (motif inconnu du verdict) | le prédicat « en vol » ne couvrait que `queued`/`in_progress` : un run `requested`/`waiting` était ignoré, et le gate concluait sur une `conclusion` nulle | « en vol » = **tout ce qui n'est pas `completed`** ; et aucun motif `*-null` ne doit être constructible (#7579) |
| Un correctif annoncé n'est pas sur `main` | PR fermée « couvert par le lot #N » alors que le lot ne le couvrait pas (constat #7531 : `optimizeCss`) | avant toute clôture « couvert », **prouver** le sous-ensemble avec `bash dev-hub/tools/check-pr-subset-of-lot.sh --lot <lot> --pr <pr>` (sortie 0 = prouvé, 1 = perte nommée) ; coller la sortie dans le commentaire de fermeture ; sinon ne pas fermer |

### 6.4 Reconstituer l'outillage local (session sans image de dev)

```bash
# PHP 8.4 portable (sans root), depuis le PPA ondrej — prendre la version réellement servie
curl -s "https://ppa.launchpadcontent.net/ondrej/php/ubuntu/pool/main/p/php8.4/" \
  | grep -oE 'php8\.4-(cli|common|opcache|readline|mbstring|curl|xml|intl|sqlite3)_8\.4\.[^"]*ubuntu22\.04[^"]*amd64\.deb' | sort -u
# …puis curl -O chaque .deb et dpkg -x <deb> /tmp/php84 ; php.ini : extension_dir=+ phar/mbstring/curl/xml/intl/pdo/pdo_sqlite
curl -sS -o composer.phar https://getcomposer.org/composer-stable.phar
/tmp/php84/usr/bin/php8.4 -c /tmp/php84.ini composer.phar install --ignore-platform-reqs --no-scripts -d api
# et rejouer EXACTEMENT la commande du CI :
cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=1G --no-progress
```

> Astuce shell : `apt-get download` échoue souvent sur un index périmé — lire l'index du
> dépôt PPA donne la version réellement servie. `pdo_sqlite` doit être chargé **après**
> `pdo.so`.

### 6.5 Critères de sortie (sinon le drain n'est pas terminé)

- [ ] **Une seule branche** : `git ls-remote --heads origin` ne rend que `main`
- [ ] **0 PR ouverte** ; les PR fermées portent un commentaire « intégré / rejeté + pourquoi »
- [ ] **4 checks requis verts** sur le SHA de `main` (vérifiés **avant** chaque merge)
- [ ] Checks non requis rouges **soit corrigés, soit ticketés** avec la mesure (jamais « on verra »)
- [ ] `main` mergé dans toute branche qui a bougé pendant la session (jamais de merge « behind »)
- [ ] SHA de tête de **toutes** les branches supprimées archivés hors dépôt
- [ ] Jeton de session **révoqué** par le propriétaire
- [ ] Leçon consolidée (ce §6 + `AGENTS.md`)

⚠️ **Ce que la procédure ne remplace pas** : la revue de fond, les tests fonctionnels sur base
réelle, et l'arbitrage produit. Un drain ramène `main` à un état livrable et vérifiable ; il ne
transforme pas dix-neuf travaux en dix-neuf travaux relus.
