# AGENTS.md - Guide de travail Leopardo

> Leçon 2026-11-25 (#9910, #9907 — le run mobile d'apres merge meurt avec la branche) : **le commit qui atterrit sur `main` n'est jamais celui qui a ete mesure sur la PR, et le run post-merge peut etre annule avant d'avoir execute un seul job : un `cancelled` a 0 job est une absence de mesure, et la seule mesure possible est `gh run rerun` sur le commit de merge.** Mesure : #9910 fusionnee a `01:36:04Z` par `gh pr merge --merge --delete-branch` ; le commit de merge est un **squash** (`b7f97fce4`, un seul parent `8823b4843` = #9908) et le head mesure de la PR (`2e38dda04`) **n'est pas un ancetre** de ce commit — son SHA n'existe plus dans l'histoire de `main`. Le run `Mobile Apps CI - Flutter` cree 3 s plus tard (`45526943230`) a son `attempt 1` **`cancelled` a `01:37:08Z`, avec 0 job** : le nettoyage des runs orphelins declenche a la fermeture de la PR a annule le run de `main` avec la branche, donc **aucun `Build Debug <app>` n'a jamais tourne sur le commit de merge**, alors que `gh pr checks` affichait la PR verte. **Regles** : (1) un run post-merge se juge par ses **jobs**, jamais par son statut : 0 job + `cancelled` = aucune mesure, ni vert ni rouge (leçon #8396) ; (2) la mesure du commit de merge s'obtient par `gh run rerun <id>` (puis `--failed` si un job echoue) et se lit **par tentative** (`/actions/runs/<id>/attempts/<n>/jobs`) : le run garde son `head_sha`, donc c'est bien le commit de merge qui est mesure ; (3) ne pas supposer l'identite de code entre head de PR et commit de merge : la **verifier** (`git diff <head> <merge>`), parce qu'un squash recree toujours un commit jamais teste et que la base de `main` peut avoir avance ; ne pas compter non plus les commits arrives sur `main` **apres** le merge (`e660d3f`, #9920, est posterieur a `b7f97fce4` et hors de tout run mesure dessus) ; (4) une suppression de branche est un evenement de CI, pas du menage. **Mesure reelle** : `gh run rerun 45526943230` le 2026-11-25 -> `attempt 2` (`23:22:05Z`) **failure** sur `Build Debug leopardo_pos_retail` seul, en 2 min 11 s, sur une erreur **reseau Gradle** (`Error resolving plugin [dev.flutter.flutter-plugin-loader]` / `Could not resolve org.jetbrains.kotlin:kotlin-stdlib:2.2.21`), puis `attempt 3` (`23:37:26Z` -> `23:43:16Z`) **completed/success** sur `b7f97fce4` : **12 `Analyze` et 11 `Build Debug` `success`** (2 jobs `skipped` = deploiements Render, hors mobile). Corollaire : un flake reseau Gradle se rejoue, il ne se corrige pas — mais la relance reste ciblee pour que le verdict final porte sur le meme SHA.

> Leçon 2026-11-24 (#9907 — un bouton de fiche ne prouve pas un parcours) : **`meta.actions` dit ce qu'une verticale DECLARE savoir faire, `meta.action_urls` dit ou le web l'envoie — aucun des deux ne dit que l'app peut l'appeler.** `leopardo_public` affichait donc des boutons « Commander » / « Reserver » construits sur ces deux sources, alors que le parcours n'existait nulle part : `restaurant` expose bien une boutique publique (`/public/restaurant/shop/*`) mais derriere `X-Restaurant-Shop-Token`, un jeton **par tenant** emis au back-office ; `pharmacy` a une fiche de decouverte mais **aucun** resolver dans `PublicProfileLocator::types()`, donc une demande de contact serait refusee en **422** ; `travel` et `careers` ont des routes publiques completes mais **aucun** provider `public.discoverable`, donc aucune entree depuis `/public/discover`. Mesure : sur 8 verticales, **2** ont un contrat complet sans jeton (retail, hospitality), **3** n'ont que la passerelle de demande (restaurant, trade, VTC), **3** n'ont rien. **Regles** : (1) la jouabilite d'une action se declare dans un **registre par verticale** (mode + capacite + motif de blocage) et se verifie par un test qui refuse une verticale manquante, un type duplique, un motif sans cle i18n dans les 4 locales, ou un `contactTargetType` que la passerelle refuserait — jamais par la presence d'une URL ; (2) une capacite absente s'**affiche avec son motif** (« la commande exige un jeton boutique ») au lieu d'un bouton mort ou d'un « bientot disponible » : c'est la seule forme qui rende la prochaine tranche serveur actionnable ; (3) un code de suivi presente **une seule fois** (le serveur n'en stocke qu'une empreinte) doit etre affiche comme un code a conserver AVANT que l'ecran de confirmation puisse etre quitte, et une mutation sans rejeu client exige une **cle d'idempotence stable entre les retries** ; (4) une personne ne peut pas etre laissee croire qu'un `ArgumentError` de validation locale est une panne reseau — d'ou une classe d'echec locale distincte de l'echec HTTP. Corollaire de methode : lire les **middlewares** de la route, pas seulement son chemin (`signed`, `restaurant.public.shop`, `market.public` changent completement ce qui est appelable), et verifier l'existence d'un resolver/ provider avant de supposer qu'un type de decouverte est joignable.
>
> Leçon 2026-11-24 (#9901, #9903 — un placeholder i18n nomme un parametre Dart) : **une cle de traduction ne se verifie pas par sa presence, mais par ce qu'elle GENERE.** `edu.feeCharges.batchClassBanner` utilisait `{class}` ; cote web c'est inoffensif (`.replace('{class}', ...)`), mais `sync-mobile.js` recopie toutes les cles partagees dans les ARB et `flutter gen-l10n` derive le **nom de chaque parametre** du nom du placeholder : `String eduFeechargesBatchclassbanner(Object charges, Object class, Object students)`. `class` etant un mot reserve, le fichier genere ne se parse pas, donc `dart format` sort en **exit 65** — et le message d'erreur ne nomme jamais la cle fautive. **Consequence mesuree** : `Analyze leopardo_*` (14 jobs), `Desktop verify leopardo_*` (12) et `Smoke build leopardo_employee` (1) rouges pour **une seule cle**, alors que TOUTES les gardes i18n existantes etaient vertes : `check-i18n-catalog-parity.sh` ne compare que la parite des cles, `validate.js` accepte le JSON, les 3 syncs passent et `git diff --exit-code` est vide. **Regles** : (1) une garde i18n verifie aussi le **contrat de generation** de chaque consommateur, pas seulement la coherence des catalogues entre eux ; (2) un placeholder doit produire un identifiant valide dans la cible (`{class}` -> `{className}`) — la garde refuse mots reserves Dart, chiffre initial et caracteres non-identifiants ; (3) un echec de toolchain (`dart format exit 65`, `flutter_lints` introuvable) n'est pas une panne d'environnement a relancer : c'est un fichier GENERE qui ne se parse pas — chercher la derniere cle ajoutee avant de toucher au SDK. Mesure : `dev-hub/tools/check-i18n-dart-placeholders.sh` (auto-test 7 cas) vu ROUGE sur l'arbre reel en reinjectant `{class}`, puis VERT apres retrait ; 1064 placeholders sur 8 catalogues verifies.

> Leçon 2026-11-23 (#9883 — onze apps mobiles, une seule identité pour deux d'entre elles) : **une app qui analyse et teste ISOLEMENT ne voit jamais qu'elle porte l'identifiant d'une autre.** `leopardo_marketing` declarait `applicationId "com.leopardo.accounting"`, son `namespace` Kotlin etait `com.leopardo.leopardo_accounting`, et son titre de fenetre desktop `leopardo_accounting` : le scaffold avait ete copie de l'app comptable et la separation n'avait jamais ete faite. Aucune garde ne pouvait le voir — `check-mobile-layer-guard.sh` ne regarde que `lib/features/**/domain/`, et `mobile-apps-ci.yml` traite chaque app dans sa propre matrice. Consequence : installation cote a cote impossible, paquet publie au nom d'une autre app. **Regles** : (1) tout ce qui doit etre UNIQUE entre apps (identifiant Android/iOS, entree de matrice de distribution) se verifie par une garde qui compare les apps ENTRE ELLES, jamais app par app ; (2) un registre qui ne contient qu'un etat (« flat/layered ») ne prouve pas l'existence des AUTRES dimensions — il faut un registre par dimension (couche, plateformes) et une garde qui exige l'app dans chacun ; (3) six apps n'etaient dans aucune matrice de distribution alors qu'elles etaient analysees : « la CI est verte sur cette app » ne dit rien sur le fait qu'un utilisateur puisse l'installer ; (4) une chaine de secrets terminee par un repli generique (`secrets.FIREBASE_APP_ID`) attribue silencieusement l'identite d'une app a une autre — une chaine explicite terminee par une chaine vide est fail-closed (skip + avertissement). Mais un repli reste parfois NECESSAIRE : `hr` n'a jamais eu de `FIREBASE_APP_ID_HR` et son identite Firebase EST le secret generique (`com.leopardo.rh`, verifie par `gh secret list` le 2026-11-24) — le repli doit alors vivre dans la branche de CETTE app et non en fin de chaine, sinon on supprime un canal vivant pour supprimer un risque ; (4bis) un ternaire d'app id existe en PLUSIEURS exemplaires (env de validation, `appId:` de l'upload, relecture) : n'en corriger qu'un fait diverger l'app id VERIFIE de l'app id UTILISE — la garde exige desormais la branche de chaque app de matrice dans CHAQUE ternaire ; (5) un bundle id iOS n'accepte pas `_` : imposer Android = iOS force des identifiants valides sur les deux stores. Mesure : `dev-hub/tools/check-mobile-app-identities.sh` (auto-test inclus) ROUGE avant, VERT apres ; carte persona -> app dans `docs/mobile/CARTOGRAPHIE_APPS_MOBILES.md`.

> Leçon 2026-11-20 (#9668 — couverture HTTP des immobilisations) : **un fichier de test absent de `api/phpunit.xml` ne rougit pas — il ne s'exécute jamais, et le job reste vert.** Le fichier porte **deux** zones générées distinctes : `BEGIN/END GENERATED FEATURE SHARDS` (`api/dev-tools/generate-feature-shards.php`) et `BEGIN/END GENERATED FEATURE MVP SPLIT` (`dev-hub/tools/generate-feature-mvp-split.py`). Rejouer le premier script ne suffit pas : le nouveau fichier apparaissait dans la testsuite `Accounting` mais dans aucun shard MVP, donc hors de la mesure. **Règle** : après tout nouveau `*Test.php`, rejouer les DEUX générateurs et vérifier le fichier par `grep -c` (une occurrence dans la testsuite du module, une dans un shard). Corollaire de méthode, vérifié ici : écrire les tests avant de relire le contrôleur a trouvé deux défauts invisibles à la lecture — `store()` sans `unique(company_id, code)` alors que la migration porte la contrainte (donc `SQLSTATE 23505` ⇒ **500** au lieu de 422), et un modèle sans `$attributes` qui renvoyait `status: null` en 201 alors que la base portait `active`.
>
> Leçon 2026-11-19 (#9661 — relecture du BC-41 livré) : **un contrat d'erreur peut exister et n'être jamais atteint — et une entrée de cache public n'est pas une autorisation.**
> **(1) Une sous-classe du `\DomainException` GLOBAL est un 500, même avec sa traduction déjà écrite.** Le renderer de `bootstrap/app.php` n'écoute QUE `App\Exceptions\DomainException` (#8247). Les trois exceptions Branding (slug, publication, host) héritaient du `\DomainException` de PHP : un slug réservé répondait **500 Server Error** pendant que `lang/<locale>/errors.php` contenait `BRAND_SLUG_RESERVED` dans les 4 langues. **Indice de diagnostic** : un code d'erreur présent au catalogue i18n mais absent des réponses observées = le renderer ne voit jamais l'exception. Et **le renderer ne passe aucun paramètre** à `__()` : un `%s` dans une traduction s'affiche littéralement au client. Règle : toute erreur de contrat hérite de la classe du projet avec un `errorCode` stable, et une traduction de code ne contient pas de placeholder.
> **(2) Un cache ne revalide que ce qu'il contient : il ne voit jamais la base — la seule borne est la purge ou le TTL.** `BrandResolver` met 60 s en cache un couple (Brand, tenant) et le rendait tel quel, sans même vérifier que l'instantané était servable. Chaque lecture revalide désormais **l'entrée en cache** (statut de la Brand, accès du tenant : deux contrôles en mémoire, aucune requête) et jette celle qui ne passe plus. **Ce que cela ne garantit PAS — écrit à tort dans une première version de cette leçon** : une modification faite *uniquement* en base (suspension SQL, modération hors application, écriture d'un autre worker) est invisible pour cette revalidation, parce qu'une entrée de cache est un instantané et non une vue ; la fraîcheur n'est bornée que par la **purge** (immédiate, posée par `publish`/`suspend`/`update`) ou le **TTL** (60 s). Règle : décrire un cache par ses deux bornes explicites (purge, TTL), ne jamais appeler « revalidation » une comparaison de l'instantané à lui-même, et ne pas conclure d'un test qui injecte une entrée périmée qu'il prouve la fraîcheur — il prouve seulement le refus d'un instantané non servable.
> **(3) Un normaliseur qui rend `null` n'exprime pas « pas de valeur ».** `normalizeHost()` rend `null` pour un hostname inexploitable ; les actions enregistraient ce `null` comme « pas de domaine ». À la création la Brand restait injoignable par host ; à la mise à jour une faute de frappe **effaçait** le domaine existant — deux pertes de données silencieuses. Un `null` de normalisation est un **refus** : lever. Corollaire opposable : toute la boucle de résolution s'arrête au **premier tenant qui matche**, donc une exception non isolée sur un schéma en retard (42P01) ne casse pas une Brand, elle casse la surface publique de tout le parc — isoler l'échec par tenant, et garder un repli 404 uniforme.
> **Corollaire mesure** : un durcissement sans assertion n'est pas vérifié. `tests/Feature/Branding/PublicBrandSurfaceTest` mesure le contrat **observable** (code HTTP + payload) et non l'implémentation : 200 par slug et par host, 404 **indistinguable** brouillon/inconnu, Brand suspendue, société suspendue, entrée de cache périmée revalidée puis jetée, tenant sans Brand qui ne masque pas la Brand suivante. Un test qui vérifie une méthode privée laisse le resolver cassable ; un test qui vérifie la réponse le laisse réécrivable.
> **Corollaire outillage** : un `*/` dans un docblock (ex. écrire `lang/*/errors.php`) **ferme le commentaire** et produit un `Parse error: unexpected token`, pas une erreur de doc. Lancer `php -l` sur chaque fichier touché avant de pousser.

> Leçon 2026-11-18 (#9609, #9098 — famille notifications Travel) : **deux silences qui font accuser le mauvais coupable — et le journal `structured` est le seul témoin.**
> **(1) `Model::create()` filtre SILENCIEUSEMENT tout attribut absent de `$fillable`.** Le devis corporate ecrivait `corporate_account_id`, `class_id` et `passengers_count`, absents du `$fillable` de `TravelQuote` : aucune exception, aucun log, trois colonnes a `null`. Le `POST /corporate-quotes` repondait quand meme **201** (le total, lui, etait assignable) et c'est la reservation groupee qui echouait en **422 « devis incoherent avec le compte corporate »** — un invariant comparant `null !== int`, donc structuralement infranchissable, avec un message qui accuse le client. Regle : une colonne qui definit l'**identite** d'une agregation (a qui, sur quoi, combien) doit etre `fillable` ET castee ; et un test de creation doit relire au moins une colonne d'identite **depuis la base**, pas seulement le total et le code HTTP.
> **(2) Un `catch` autour d'un `INSERT` « best-effort » ne protege RIEN s'il s'execute dans une transaction deja ouverte.** `travel_notification_logs.company_id` est `NOT NULL` et n'etait jamais fourni (le trait `BelongsToCompany` ne l'alimente qu'avec une compagnie courante, absente hors requete HTTP) : chaque insertion echouait en `23502`, le `catch` l'avalait, mais PostgreSQL avait deja **avorte la transaction englobante** → toutes les requetes suivantes du consommateur tombaient en `SQLSTATE[25P02]`. Deux tests rouges dont le JUnit designait une `select exists(... travel_notification_consents ...)` : une **consequence**, jamais la cause. Regle : une ecriture best-effort passe par un savepoint (`DB::transaction($fn, 1)`), une valeur `NOT NULL` issue d'un contexte ambiant est passee **explicitement** par l'appelant, et un parametre obligatoire (pas un trailing optionnel) rend l'oubli impossible a ignorer.
> **Corollaire de methode** : pour un `25P02`, ne pas lire le code appelant mais chercher la premiere erreur **avalée** dans le canal de log `structured` (`grep log-failed structured-*.log`) — c'est la seule trace d'une exception attrapee. Doc : `docs/architecture/adr/0033-colonnes-identite-et-ecritures-best-effort.md`.

> Leçon 2026-11-17 (#9536, #9543, #9552 — tranches de #9098) : **un test rouge peut accuser le test autant que l'API — mais trois pièges font dire « test périmé » à tort ou à raison, et il faut la preuve dans le JUnit, pas l'intuition.**
> **(1) Un statut attendu qui n'existe pas dans l'enum est un test périmé, pas une régression.** `TravelCorporateApiTest` attendait `data.status == 'accepted'` après acceptation d'un devis corporate ; `QuoteStatus` ne contient que `draft|confirmed|booked|cancelled|expired`, et `CorporateBookingService::acceptQuote()` passe le brouillon à `confirmed`. Chercher la valeur attendue **dans l'enum** avant de toucher au service.
> **(2) Un `JsonResource::response()` déduit le statut 201 de `wasRecentlyCreated` — donc le MÊME POST idempotent répond 201 puis 200.** `POST /travel/quotes/{id}/book` transitionne le devis et renvoie la réservation existante : la première réponse était 201, le rejeu 200. Un client ne peut plus décider de l'idempotence sur le code de réponse. Règle : un endpoint **idempotent** fixe son statut explicitement (`->setStatusCode(200)`), il ne le laisse pas déduire d'un état de modèle.
> **(3) `round()` rend un flottant, `json_encode` peut le rendre en entier.** Sans `JSON_PRESERVE_ZERO_FRACTION`, `round(5.0, 2)` sort `5` : le client reçoit un **int** là où le contrat annonce un décimal (un `double` Dart lève). Fixer côté encodage (`new JsonResponse($data, 200, [], JSON_PRESERVE_ZERO_FRACTION)`), jamais en assouplissant l'assertion du test (`assertSame(5.0, (float) …)` masquerait la dérive de type).
> **Corollaire de méthode** : ces trois-là ont été trouvés dans l'ordre inverse de leur gravité apparente — on a d'abord cru que 4 tests « mentaient », puis chacun a révélé un défaut d'API (slug explicite renommé en silence, `amount` lu au lieu de `amount_minor`, `converted_amount_minor` jamais exposé). Un test rouge se classe en lisant **le corps de l'erreur** (le 422 contient `errors.message`), puis en relisant le contrôleur — jamais en supposant.

> Leçon 2026-11-14 (#9467/#9471) : **un auto-merge armé sur une PR mobile la merge AVANT que `Build Debug` (4 min × 11 apps) n'ait tourné — et le `cancelled` qui suit se lit comme un succès de file, pas comme une absence de verdict.** #9467 (toolchain Android : Gradle 9.3.1 / AGP 9.1.1 / Kotlin 2.4.0) a été mergée à `03:29:20Z` alors que son run de branche `43551775161` n'avait **qu'un job `Build Debug` `completed/cancelled`** : aucun build n'a jamais été vert sur le commit de la PR. Le run post-merge de `main` (`43551885547`) a ensuite révélé une par une deux erreurs que la PR aurait dû montrer (`checkDebugAarMetadata` → `minCompileSdk 37`, puis `uses-sdk:minSdkVersion 24 … 26 declared in library [:sentry_flutter]`), soit **trois correctifs successifs sur une CI déjà rouge** (#9467 puis #9472, cette dernière vérifiée verte : run `43563995866`, 11/11 `Build Debug` `success`). **Règles** : (1) ne pas armer `gh pr merge --auto` sur une PR qui touche `front/mobile_apps/**` ou `.github/actions/setup-flutter-android` — attendre `Build Debug` explicitement ; un `cancelled` n'est pas un vert (leçon #8396) ; (2) `gh pr merge --auto --delete-branch` supprime la branche, donc **coupe le run en cours** : la mesure disparaît avec le run ; (3) la preuve d'un build mobile n'existe que sur un job `Build Debug <app>` en `success` — jamais sur le statut du workflow. Corollaire découvert ici : **la contrainte `minSdk` traverse les dépendances de package** — une app qui n'importe pas `sentry_flutter` mais dépend de `leopardo_core` hérite quand même de son `minSdk 26`.

> Leçon 2026-11-12 (session PM dette de tests + alertes dev) : **trois pièges qui coûtent une PR chacun.**
> **(1) Vérifier `gh pr list --state open` AVANT d'écrire du code, pas après.** J'ai implémenté #9399
> (course `ensureWorkerPublicSchema()` en `--parallel`) alors que la PR #9418 était ouverte depuis la
> nuit sur la même issue, avec la **vraie** cause profonde que je n'avais pas vue : `migrate` était
> lancé **depuis** `configureTestingDatabaseConnection()`, donc **avant** que la config ne pointe sur
> la base du worker — la migration tournait sur la base **commune** et la base du worker restait vide.
> Verrouiller correctement une migration qui ne cible pas la bonne base ne corrige rien, et une
> correction **mesurée** (39 → 58 passed sur 3 runs froids) doit primer sur une correction seulement
> `php -l`. Ma PR a été fermée en doublon, avec le détail. Corollaire : le protocole anti-doublon
> d'`AGENTS.md` parle de branches, mais `gh pr list --state open` est le contrôle le moins cher et le
> plus direct.
> **(2) Une issue peut affirmer l'existence d'un helper : le vérifier avant de le réutiliser.**
> #9399 recommandait de « réutiliser `canonicalSchemaReady()` (déjà présent) » — il existe bien, mais
> dans le trait `tests/RefreshTenantDatabase.php`, **pas** dans `tests/TestCase.php` où le correctif
> devait vivre. Un `grep` de 2 s a évité un appel de méthode inexistante.
> **(3) Un watchdog qui promet « une alerte par 24 h » produit un ticket PAR JOUR sur une panne
> durable.** `dev-keepalive.yml` créait une issue `[dev-switch] ALERTE` sans regarder si une était
> déjà ouverte : 10 tickets identiques ouverts le même jour pour une seule panne. La règle est
> désormais « une panne durable = un ticket, rafraîchi par commentaire au plus 1 fois / 24 h »
> (`Closes #9424`). Piège de diagnostic associé : la mesure `gh issue list | grep -c` ne distingue pas
> 10 alertes d'une seule — lire les **titres**, pas le nombre.
> **(4) Un doublon de ticket se ferme avec sa motivation, jamais en silence** : les 9 alertes
> redondantes ont été fermées en renvoyant vers #9339 (canonique, porte la durée) et #9384 (cause
> opérationnelle), et la cause a été corrigée **à la source** — fermer sans corriger aurait fait
> revenir les doublons le lendemain.

Derniere mise a jour : 2026-11-15 (#9502 — le cout d'une suite de tests n'est pas la somme des tests)

> Lecon 2026-11-15 (#9502, tranche de #9098) : **une suite qui expire n'a pas forcement un test qui bloque — trie le JUnit par duree avant de chercher un coupable.** Le job `Backend` expirait a 210 min sans produire le moindre `feature.xml`, ce qui fait penser a un hang. Le `feature.xml` du dernier run COMPLET (9137 testcases) dit l'inverse : 92 tests pesent 19 751 s sur 23 938 s (82 %), 88 de ces 92 sont le PREMIER test de leur classe, et les 89 classes lentes utilisent **toutes** `CreatesMvpSchema`. Le cout est une reconstruction de fixture (~215 s de DDL) repetee 89 fois. **Trois regles** : (1) un plafond de duree autour d'une valeur ronde (~215 s x 89) est la signature d'une fixture ou d'une migration, pas d'une boucle infinie ; (2) quand un trait construit un etat au premier appel, la duree se lit en `first-of-class`, pas en « test le plus lent seul » ; (3) un marqueur de cache place DANS le schema que d'autres tests detruisent (`shared_tenants.__mvp_fixture_ready` face au `DROP SCHEMA ... CASCADE` de `RefreshTenantDatabase`) transforme le cache en dette — le remede le moins invasif est de separer les familles de classes en invocations distinctes, ce que paratest ne sait faire que par testsuite `<file>` explicite. **Corollaire de fiabilite** : tout decoupage de suite doit prouver que chaque fichier apparait EXACTEMENT une fois — un fichier oublie ne rougit pas, il ne s'execute jamais.

> Leçon 2026-11-11 (#9401, suite de #9311) : **un `timeout-minutes` de garde se dimensionne sur une MESURE, jamais sur une estimation — sinon il tue les runs légitimes et produit le faux rouge qu'il devait éviter.** Le correctif #9311 avait posé `timeout-minutes: 150` sur l'étape « Run backend feature tests » en s'appuyant sur « ~45-60 min nominal ». Le premier job complet passé ensuite sur `main` (`42770976926`, SHA `b1b1c8da2`) donne la durée réelle : étape Feature **124 min** (`08:09:40Z` → `10:13:48Z`, `completed/success`), job total 141 min. 150 min ne laissait donc que **21 % de marge** : le run suivant un peu plus lent aurait rougi par timeout, sans aucun rapport avec le code. **Règles** : (1) avant d'écrire un plafond, extraire la durée du dernier run complet via les **horodatages d'étapes** (`GET /actions/runs/{id}/jobs` → `steps[].started_at/completed_at`), pas depuis l'en-tête du workflow — les commentaires d'en-tête portaient ici une estimation périmée (job « ~82 min » contre 141 min mesurées) ; (2) la marge se prend sur la mesure (ici 124 × 1,6 ≈ 210 min), et le plafond du **job** garde le rôle de dernier filet ; (3) un `timeout` d'étape déclenche un échec `failure` indiscernable d'un vrai échec de test dans la liste des checks — d'où le coût : on ne saurait plus distinguer « suite trop lente » de « suite cassée ». Corollaire de lecture : les durées dans les commentaires de CI vieillissent plus vite que le code — dater la mesure et la sourcer (run + SHA) à côté de la valeur.

> Leçon 2026-11-10 (#9311) : **un seul job suspendu sur `main` suffit à annuler la capacité de mesurer de tout le dépôt — et le symptôme ressemble à « la CI est saturée ».** Trois runs `Backend` annulés, puis six runs de `main` restés `pending` **sans un seul job exécuté**, alors que la file était presque vide (7 `queued`, 7 `in_progress`) : la cause n'était ni la charge, ni la concurrence de la couverture, mais **un run zombie** (`42741886141`) resté `in_progress` **3 h 42** dans l'étape « Run backend feature tests » d'un job plafonné à 300 min. Comme `tests.yml` sérialise **par ref** sur `main` (`cancel-in-progress: false`), ce run retenait le groupe de concurrence ; chaque push suivant **remplaçait** le `pending` précédent (GitHub ne conserve qu'un `pending` par groupe), donc aucun n'exécutait ses jobs. **Règles de diagnostic** : (1) avant d'accuser la saturation, lire les **étapes** du job (`GET /actions/runs/{id}/jobs` → `steps[]`) : `in_progress` sur la même étape depuis des heures = run suspendu, pas run chargé ; (2) un `pending` à **0 job** ne mesure rien — chercher quel run `in_progress` du **même groupe de concurrence** le bloque, avant de conclure quoi que ce soit sur le code ; (3) **plafonner l'ÉTAPE**, pas seulement le job : un `timeout-minutes` de 300 sur le job laisse un groupe de concurrence bloqué 5 h, alors qu'un plafond sur l'étape (mesure × 1,6, jamais une estimation) libère en ~3 h 30 — `Run backend feature tests` porte désormais `timeout-minutes: 210`, dimensionné sur les 124 min mesurées (run `42770976926`, SHA `b1b1c8da2` ; la valeur initiale de 150 min venait d'une estimation et ne laissait que 21 % de marge, cf. #9401) ; (4) **débloquer** est une action PM légitime : `gh run cancel <zombie>` a fait passer le `pending` de `main` à `queued` avec 6 jobs en une minute. Corollaire de diagnostic : annoncer une cause (ici « la couverture sature les slots ») avant d'avoir suivi l'état **par étape** coûte une issue à réécrire — l'observation secondaire (4 runs pcov concurrents) n'était pas la cause et ne doit **pas** être « corrigée » : la concurrence par SHA de `coverage-gate.yml` garantit un verdict sur le commit taggé à la release (#8396).

> Leçon 2026-11-09 (#9222) : **un garde qui dépend d'un binaire absent passe au VERT en silence — et masque la violation qu'il devait attraper.** Le job `Hygiene Guards` loguait `check-policies-single-point.sh: line 45: rg: command not found` puis `Process completed` sans échec : `ripgrep` n'est pas garanti sur le runner, et les deux substitutions qui décident du verdict étaient protégées par `|| true`, donc `OFFENDERS=""` → « tout est conforme » **sans avoir rien lu**. Conséquence mesurée : `RestaurantManagerServiceProvider` enregistrait `Gate::policy(RestaurantPrintJob::class, …)` hors du point unique (PA2-ARCH-008, garde #6575) — invisible depuis la création du garde. **Règles** : (1) tout garde qui appelle un outil externe vérifie sa présence (`command -v`) et fournit un repli équivalent, ou échoue **fail-closed** — jamais de `|| true` sur la seule lecture qui décide ; (2) une garde réparée se prouve **ROUGE** avant d'être verte (sonde d'une policy hors point unique → exit 1, retrait de la sonde → exit 0) ; (3) normaliser les séparateurs de chemins rendus par `rg`/`grep` (`tr '\\' '/'`) : sous Windows l'exclusion du fichier canonique ne matchait jamais et le garde s'auto-accusait.

> Leçon 2026-11-08 (#9189, suite de #9035) : **un déclencheur `workflow_run` sans filtre repart pour les runs de `push` — et le SHA d'un commit de `main` RÉSOUT vers sa PR fusionnée.** `pr-external-checks-report.yml` écoutait 4 workflows sans regarder l'événement du run source : un run déclenché par un **push** sur `main` résolvait `GET /commits/{sha}/pulls` vers la PR **fermée** de ce commit (`41e4b030` → #9187 `state=closed`) et rafraîchissait un commentaire que plus personne ne relit — la branche « SHA sans PR ouverte » du script n'était jamais atteinte, parce que `base.ref == 'main'` **prouve qu'une PR a mergé ce commit, pas qu'elle est ouverte**. **Piège de mesure, à retenir** : `runs?branch=main` groupé par `head_sha` affichait 22 à 23 runs par commit de `main` (`41e4b030` → 22) — mais `head_sha` d'un run `workflow_run` est le **HEAD de la branche par défaut**, pas le SHA source : ces runs venaient des **PR ouvertes** (preuve : `target=806837f6…` → « Rapport mis à jour sur #9183 »). Un volume par `head_sha` ne dit donc **rien** de l'origine d'un run. **Règles** : (1) tout `workflow_run` qui publie sur une PR filtre `github.event.workflow_run.event == 'pull_request'` — un run de `push` sur `main` n'a aucune PR à servir (vérifié après merge : run `42462082709` → `skipped`, zéro runner) ; (2) tout outil qui commente vérifie `state == open` **côté script**, pas seulement côté déclencheur (filet pour les appels manuels) ; (3) la garde à faux `curl` (zéro réseau) est vue **ROUGE** sur l'ancien script (`Rapport publié sur #9187`) avant d'être verte — `dev-hub/tools/tests/report-external-checks.test.sh`, branchée dans le workflow. Corollaire : « le SHA pointe une PR » ne veut pas dire « il y a une PR à rapporter ».

> Lecon 2026-11-07 (#9116) : **un echec de build macOS peut etre une resolution de dependances, pas notre code — et un `pubspec.lock` committe peut etre le coupable fusible.** Les 6 jobs `macos-latest` de `Desktop CI` etaient rouges sur `Build (verify)` depuis le 2026-11-02 avec un seul message : `firebase_messaging` 17.0.1 -> `firebase-ios-sdk` 13.1.1 -> `gtm-session-fetcher` 4.0.0..<6.0.0, contre `google_sign_in_ios` 6.3.3 -> `GoogleSignIn` 9.x -> `gtm-session-fetcher` 3.3.0..<4.0.0. **Trois pieges de diagnostic** : (1) `flutter analyze` et les jobs Windows passaient (Windows ne passe jamais par Swift Package Manager, exclusif a apple) : un vert partiel sur la meme matrice ne dit rien de la cause, il faut lire le job rouge; (2) le conflit est **resolu par xcodebuild avant compilation**, donc aucune erreur de notre code et aucun fichier `.swift` en cause; (3) le `pubspec.lock` committe fige `google_sign_in_ios` 6.3.3 alors que le constraint `^6.2.0` autorisait deja mieux: bump de **lock**, pas de `pubspec.yaml`. `6.4.0` elargit la dependance a `GoogleSignIn` 9.0..<11.0 (donc 10.x, gtm 4.x), et exige Flutter 3.47/Dart 3.13 — a verifier car un lock qui depasse le SDK local casse `flutter pub get` chez les devs en Flutter ancien. **Ne pas « reparer » en desactivant SPM** (`flutter config --no-enable-swift-package-manager`) : la CI passerait au vert en changeant de resolveur sans prouver que les builds CocoaPods tiennent, et Flutter annonce l'option comme non supportee a terme.

> Leçon 2026-11-07 (#9164) : **un outil de nettoyage CI peut annuler son propre run — et, bien pire, toute la file.** Deux pièges dans `cancel-orphan-runs.sh`, tous deux silencieux. **(1) L'auto-annulation** : `cleanup-orphan-runs.yml` se déclenche sur `pull_request: [closed]`, donc son **propre** run porte la branche qui vient d'être mergée puis supprimée ; sans exclure sa propre identité (`GITHUB_RUN_ID`), le script se classe « orphelin » et s'annule lui-même — le check reste `cancelled`, que `gh pr checks` affiche comme rouge (leçon #8396), **sur chaque PR mergée**, alors que le nettoyage a bien agi. **(2) La liste vide qui n'échoue pas** : `mapfile -t X < <(gh api …)` sous `set -e` ne propage JAMAIS l'échec de la commande de la substitution ; une réponse vide (rate limit, token sans `contents: read`) donne un tableau vide, donc « **toutes** les branches sont mortes » et le script annule les runs de `main` et de toutes les PR ouvertes. **Règles** : toute liste lue par substitution de processus est confrontée à une garde **fail-closed** (`0 élément = exit 1, aucune action`) ; tout outil qui annule ou édite exclut sa propre identité ; et une garde de test n'existe que si on l'a vue **ROUGE sans le correctif** (faux `gh` local, zéro réseau) — `dev-hub/tools/tests/cancel-orphan-runs.test.sh`, branchée dans le workflow, est la référence.

> Lecon 2026-11-01 (#8396 -> #9098) : **une campagne se ferme par une DECLARATION et un TICKET SUCCESSEUR, jamais par un `close` nu ni par un statu quo indefini.** #8396 etait `CLOSED` (2026-10-29) alors que son propre dernier commentaire concluait « l'issue reste ouverte comme suivi de campagne » : deux etats contradictoires, donc personne ne savait si les 166 echecs restants etaient suivis. Trois options existaient et deux sont mauvaises : rouvrir l'issue (une campagne de 5 tranches deja livrees n'a plus de lot a porter : elle n'a pas de proprietaire par module, et une issue ouverte ne cree pas ce proprietaire) ; laisser l'ecart en place (la dette devient invisible -> motif exact du « ghost close » #4816). **Regle** : la cloture se prouve par un commentaire qui dit (1) ce qui est livre et mesure, (2) ce qui ne l'est PAS et pourquoi la campagne n'est plus le bon vehicule, (3) le numero du ticket successeur qui porte le reste. Le successeur recoit la **mesure de reference datee** (run + commit), le **tableau par module**, l'**ordre de traitement impose par le volume** et le **critere de sortie mesurable** (`0 echec` sur 3 runs consecutifs) — sans quoi le successeur redevient une campagne. Corollaire : une mesure datee qui vieillit doit le dire elle-meme (« anterieure aux merges #9088..#9090 ») ; l'instrument (#9074) la rafraichit a chaque run, ce qui autorise la cloture sans re-mesurer a la main.

> Leçon 2026-10-31 (#8396) : **(1) `gh pr checks` affiche `cancelled` comme `fail` — un job annulé ne prouve rien sur le code.** J'ai conclu « le job Backend ne tourne que sur `main` » en lisant les checks d'une PR **déjà mergée** : le job existait bien, il tournait (`pull_request` + `paths: api/**`), et il a été **annulé 36 s après le merge** (workflow `cleanup-orphan-runs`, déclencheur `pull_request: [closed]`, plus la suppression de branche) pendant qu'il en était à 2 min sur une suite de ~100 min. Un `cancelled` rendu comme `fail` a produit une conclusion structurelle **fausse** et une recommandation de changement de protection de branche inutile. **Règle** : avant de conclure qu'un check ne tourne pas, vérifier `gh run list --workflow <f.yml> --json event,headBranch,conclusion` et le `conclusion` réel du job — `cancelled` ≠ `failure` ; et ne jamais merger une PR pendant qu'un job long qu'on veut mesurer tourne, sinon sa mesure disparaît avec le run. **(2) Un helper de test défini mais jamais appelé garde un commentaire qui ment.** `EduAcademicYearTest::dropTablesReferencing()` existait, documenté (« les tables dépendantes sont donc supprimées d'abord »), et **aucun appel** nulle part : le `down()` échouait en `2BP01 cannot drop table edu_admissions because other objects depend on it`. Le commentaire décrivait une intention, pas le code — même piège que la spec OpenAPI périmée de #8247. Avant de diagnostiquer un échec de cycle `down()`/`up()`, chercher les FK **inter-migrations** (`information_schema.constraint_column_usage`) et vérifier que le helper est réellement invoqué. Corollaire : réparer le **test** (supprimer les dépendantes d'abord), jamais la migration — un `DROP ... CASCADE` dans `down()` détruirait des contraintes définies par d'autres fichiers (#8207). **(3) Un helper de test qui exige un argument mais est appelé sans n'en est pas un bug de produit** : `activeSubscription(Company $company)` appelé 4 fois sans argument levait `ArgumentCountError` avant toute assertion (5 échecs muets). Rendre l'argument optionnel **suffit à casser l'assertion** si chaque appel crée une nouvelle entreprise : mémoriser l'entreprise par test est ce qui préserve la séquence de numérotation attendue.

> Leçon 2026-10-29 (#8396) : **un triage se fait sur la pile d'appel, pas sur le message** —
> « 264 échecs » n'est pas « 264 bugs », et l'outil `dev-hub/tools/triage-junit.py`
> (branché dans le job Backend, sortie dans `$GITHUB_STEP_SUMMARY`) regroupe chaque échec par
> `(module, exception, message normalisé, première frame du dépôt)`. Trois pièges mesurés, tous
> verrouillés par `dev-hub/tools/tests/triage-junit.test.sh` : **(1)** les `<testsuite>` PHPUnit
> sont **imbriqués** et chaque niveau redéclare `tests` — additionner ces attributs gonflait le
> total à **21 349 au lieu de 9 693** : compter les `<testcase>`. **(2)** le module se déduit du
> **dossier** de test (`tests/Feature/<Module>/…`), jamais du nom de fichier : sans `/` final dans
> la regex, `tests/Feature/HealthEndpointTest.php` devient un module nommé « HealthEndpointTest ».
> **(3)** sans normalisation (UUID, hash, chemins CI), chaque échec d'une même cause est unique et
> **aucun regroupement** n'a lieu — l'outil devient un `grep` décoré. **Un triage est un rapport,
> pas une garde** : ne jamais le brancher avec `--fail-over` dans un job requis, le rouge d'un
> outil de diagnostic masquerait le verdict réel des suites. Corollaire de méthode : l'outil
> **localise**, il ne qualifie pas — affirmer « préexistant » exige le stash du diff et le rejeu
> (#8035) ; un outil qui devinerait produirait des « préexistant » faux, plus coûteux qu'aucune
> réponse.

Derniere mise a jour : 2026-10-29 (#8396 — defauts SQL non hydrates cote Eloquent)

> Leçon 2026-10-29 (#8396, suite de #8987) : **Eloquent ne lit JAMAIS les `default()` SQL.**
> Juste après `save()`, tout attribut non fourni explicitement vaut `null` EN MÉMOIRE alors
> que la base porte bien le défaut : `$model->enum->value` lève alors
> `ErrorException: Attempt to read property "value" on null` → 500 sur la création.
> Deux modules ont payé le même prix à deux semaines d'intervalle (Restaurant ×11 modèles
> via #8987, Marketing ×4 modèles : `MarketingAsset`, `MarketingCampaign`, `ContentIdea`,
> `MarketingPublication`). **Règle** : tout modèle dont un cast d'enum (ou un champ lu par
> une Resource/Audit) repose sur un `default()` de migration déclare ce défaut dans
> `protected $attributes`. Corollaire de diagnostic : une série de 500 « à la création »
> sur un module entier n'est presque jamais N bugs, c'est UN défaut non hydraté —
> chercher le `->value` sur le modèle fraîchement créé avant de lire les contrôleurs.
> Second corollaire : le JUnit du job Backend (artefact `backend-test-reports` d'un run
> `tests.yml`) donne la liste des échecs **avec leurs traces** ; c'est la seule façon
> honnête de clustériser #8396 — les messages d'assertion seuls ne disent pas la cause.

Derniere mise a jour : 2026-10-28 (#8708 — exclusivite et verrou agent sur la vitrine marketing)

> 🔒 Règle opposable 2026-10-28 : **VERROU D'EXCLUSIVITÉ VITRINE MARKETING**
> Les répertoires `front/web/src/modules/vitrine/**` et `front/web/src/app/(landing)/**`
> sont désormais **STRICTEMENT RÉSERVÉS À L'AGENT VITRINE DÉDIÉ**.
> **INTERDICTION FORMELLE À TOUT AUTRE AGENT DE MODIFIER, RENOMMER OU TOUCHER À LA VITRINE.**
> Mécanismes actifs : `front/web/src/modules/vitrine/AGENTS.md`, garde CI bloquante
> `.github/workflows/vitrine-lock-guard.yml`, script `dev-hub/tools/check-vitrine-agent-lock.sh`,
> et entrées `CODEOWNERS`. Toute tâche ou issue externe touchant la vitrine doit être refusée
> ou transférée à l'Agent Vitrine Dédié.

Derniere mise a jour : 2026-09-29 (#8247 — contrat d'erreur : abort() vs renderer)

> Leçon 2026-09-29 (#8247) : **un `abort(404, 'MON_CODE')` n'expose JAMAIS
> `MON_CODE` au client API** — le renderer `HttpExceptionInterface` de
> `bootstrap/app.php` réécrit tout 404 en `{error: RESOURCE_NOT_FOUND}`
> générique. Pour un code d'erreur stable et documentable
> (`PENDING_ACTION_NOT_FOUND`…), il faut une `DomainException` (renderer
> dédié `{error, message, localized_message}`). Corollaire doc : ne jamais
> documenter dans openapi.yaml un code d'erreur lu dans un `abort()` sans
> vérifier le renderer — et toujours relire le contrôleur plutôt que faire
> confiance à la spec existante (le champ `reply` de `/ai/chat` était
> documenté mais n'a jamais existé ; le champ réel est `response`, enveloppé
> dans `data`).

> Leçon 2026-09-28 (#8207, lot Z11 BOS-017/018) : **(1) une migration fantôme
> neutralisée garde souvent son `down()` DESTRUCTEUR d'origine** — 10 fichiers
> du repo avaient un `up()` no-op et un `down()` qui droppait tables/colonnes
> des migrations canoniques ; un `migrate:rollback` ciblé vidait le schéma sans
> que rien ne le restaure. Règle : un `down()` ne détruit que ce que le `up()`
> du MÊME fichier a défini. Garde `dev-hub/tools/check-migration-destructive-down.py`
> (branchée dans `migration-duplication-guard.yml`) — dette legacy allowlistée
> dans `migrations-destructive-down-allowlist.txt` (résorption : #8237).
> **(2) Dédupliquer une migration déjà jouée en prod = NEUTRALISER (no-op
> documenté), jamais supprimer le fichier** : Laravel résout chaque entrée de la
> table `migrations` par son fichier au rollback — un fichier manquant casse
> `migrate:rollback` du batch (doublon 7417/7420 : 7417 neutralisée, 7420
> canonique idempotente, chaîne préservée, prouvé sur copie PG16).
> **(3) Tester `ShouldBeUnique` ne exige aucun worker** : le verrou est acquis
> dans `PendingDispatch::__destruct` — `Queue::fake()` + deux `Job::dispatch()`
> identiques puis `assertPushedTimes(Job::class, 1)` prouve l'absorption
> (cache `array` en test). Et le gate PHPStan CI réel = `phpstan.ci.neon`
> **niveau 8 + 3 baselines** sur fichiers changés (généré dans `tests.yml`,
> job backend-quality) — `phpstan.neon` seul (level max) produit des findings
> hors gate (`cast.*` sur mixed).

Derniere mise a jour : 2026-09-23 (#8092 — rattrapage prod Render + alerte de retard)

> Leçon 2026-09-23 (#8092) : **(1) un workflow de rattrapage se juge à sa COUVERTURE réelle,
> pas à son nom** — « Deploy Main Catch-up » concluait 4×/h « dev à jour — aucun rattrapage
> nécessaire » pendant que la PROD Render tournait 2 jours derrière `main` : le job ne
> comparait que le tier dev. Quand un mécanisme renvoie vers un filet de sécurité (« le
> catch-up rattrapera »), vérifier que le filet couvre BIEN l'environnement concerné.
> **(2) Un `::warning` dans un run planifié vert n'alerte personne** : le rapport prod↔main
> (#7648) existait et mesurait l'écart — il n'a rien empêché. Au-delà d'un seuil d'âge, la
> garde doit ÉCHOUER (rouge visible) ; et mesurer l'âge du RETARD sur le premier commit non
> déployé, pas sur le commit déployé (un merge d'il y a 1 h sur une prod posée il y a 5 jours
> n'a qu'1 h de retard). **(3) `POST /deploys` Render sans corps laisse `commit: null`** sur
> le deploy — tout déclenchement API doit porter un `commitId` explicite, sinon la
> comparaison « SHA déployé ↔ main » devient impossible et aveugle les gardes suivantes.

Derniere mise a jour : 2026-09-21 (tranche 3 du deblocage CI #8004 — role Postgres du service container)

> Lecon 2026-09-21 (#8004, tranche 3) : **(1) dans un service container PostgreSQL de GitHub
> Actions, le role `postgres` N'EXISTE PAS des que `POSTGRES_USER` est personnalise** : `initdb -U
> $POSTGRES_USER` fait de CETTE valeur le seul superutilisateur cree. Un `docker exec -u postgres
> <conteneur> psql …` repond donc `FATAL: role "postgres" does not exist` et tue le job — utiliser
> `psql -U <POSTGRES_USER>` (auth locale `trust` dans l'image officielle, `ALTER SYSTEM` permis
> puisque ce role est superutilisateur). **(2) Un job qui meurt a l'etape de preparation rend le
> diagnostic de l'issue FAUX** : #8004 annoncait « ~470 tests Feature KO » alors que le job
> Backend n'atteignait jamais la premiere migration — lire la PREMIERE erreur du log de job
> (`/actions/jobs/<id>/logs`, token repo requis) AVANT d'attribuer les echecs aux tests ; les
> annotations de check run ne portent que « Process completed with exit code N ».

Derniere mise a jour : 2026-09-21 (lots sécurité backend #7995/#7999 + BC-01 PLATFORM #7973..#7978 + session PM #7963/#7966/#7958/#7967 — fusion des deux blocs de leçons ; + garde Trivy opt-out et acquittements réalignés #8024)

> Leçon 2026-09-21 (#8024) : **(1) un `.trivyignore.yaml` qui contredit le code est pire
> qu'aucun acquittement** — trois entrées y décrivaient l'état *antérieur* à #7966/#7996
> (php84 « exclu du non-root » alors qu'il porte `USER leopardo` ; `edge/Dockerfile`
> « chown root + Caddy :80 » alors qu'il est `USER www-data` sur :8080). Un acquittement
> périmé ne fait pas que mentir : il **masque** le défaut s'il réapparaît. Corollaire
> opérationnel : après tout lot non-root (#7966/#7996), relire les acquittements Trivy
> et `trivy config` localement (binaire téléchargeable, `--ignorefile .trivyignore.yaml`)
> avant de pousser.
> **(2) Une auto-référence `COPY --from` peut cacher un Dockerfile entièrement faux** :
> `edge/Dockerfile` n'avait aucun `FROM base` avant son dernier bloc, donc tout le bloc
> final (PWA, Caddyfile, entrypoint, `EXPOSE`, `USER`) s'exécutait dans le stage
> `pwa-build` (`node:20-alpine`) — l'image produite n'était pas FrankenPHP du tout.
> Retirer un acquittement peut donc RÉVÉLER un bug réel (ici `AVD-DS-0006`, CRITICAL) :
> ne jamais le retirer sans rejouer le scan.
> **(3) Garde locale = opt-out, jamais opt-in** : `TRIVY_ENFORCE` était à `0` par défaut
> et n'était posé par aucun workflow — une garde jamais appelée ne protège rien. Même
> règle que #8013 : une garde se juge à sa fréquence d'exécution réelle, pas à son
> existence.

> Leçon 2026-09-21 (#8028) : **(1) un refactor de composant peut faire disparaître un
> ÉTAT MÉTIER de l'écran sans casser un seul test unitaire.** #7908 a déplacé le badge
> « Trial » dans `BusinessRailCard` (modules `scope: 'business'`) alors que l'ancien
> `NavPill` le rendait pour tout module `enabled && state === 'trial'` : un module cœur en
> essai (« Rapports ») n'était plus signalé nulle part, et seule la garde **e2e** l'a vu —
> encore faut-il qu'elle tourne sur un job REQUIS, ce qui n'était pas le cas (job vitrine
> hors branch protection). Déplacer un composant = vérifier la liste des états qu'il
> rendait, pas seulement ses liens.
> **(2) Reproduire un e2e « déterministe » en local AVANT de choisir entre « test obsolète »
> et « régression produit », puis DATER la régression par l'historique** :
> `commits?path=<spec>` puis `contents?ref=<sha>` sur le fichier d'avant le refactor suffisent
> à trancher (ici : le badge existait bien avant #7908). Sans cette double preuve, on
> « répare » le test et on enterre la régression.

Derniere mise a jour : 2026-09-21 (lot sécurité #8021 — politique mots de passe réellement unique + garde durcie ; lots #7995/#7999 + BC-01 PLATFORM #7973..#7978 — fusion des blocs de leçons)

> Leçon 2026-09-21 (#8021) : **(1) un helper unique ne suffit pas — il faut migrer les sites
> historiques ET durcir la garde.** #7995 avait créé `PasswordPolicy` mais sa garde CI ne
> tolérait que `min:1..11` : sept surfaces (les comptes plateforme les plus privilégiés + 6
> sites inline) ont survécu avec la règle dupliquée, dont un `min:12` SEUL sans chiffre ni
> blocklist. Une garde de politique doit exiger la conformité COMPLÈTE (blocklist + chiffre)
> et couvrir TOUS les noms de champ (`new_password`), pas seulement le cas le plus faible ;
> et un scanner borné par l'équilibre des crochets vaut mieux qu'une fenêtre glissante de
> N lignes (qui déborde sur la règle suivante). **(2) Dans une worktree, vérifier OÙ
> l'autoloader résout le code** : `api/vendor` symlinké vers le checkout principal fait
> résoudre `App\`/`Tests\` via `dirname(vendorDir)` = le checkout PRINCIPAL — les tests
> tournent alors sur le mauvais code (faux verts). Trancher avec
> `php -r 'echo (new ReflectionClass(App\Shared\Rules\PasswordPolicy::class))->getFileName();'` ;
> remède local = copie réelle de `vendor/` (jamais de `composer dump-autoload` dans le
> vendor partagé).
Derniere mise a jour : 2026-09-21 (lots sécurité backend #7995/#7999 + BC-01 PLATFORM #7973..#7978 + session PM #7963/#7966/#7958/#7967 — fusion des deux blocs de leçons) + suivi dev #8018

> Leçon 2026-09-21 (#8018, suivi #8014) : **(1) toute sonde qui grep un motif
> dans `/proc/*/cmdline` doit CASSER le motif (`[q]ueue:work`)** — sinon le
> `grep` de la sonde lit SA PROPRE cmdline (elle contient le littéral), la sonde
> est tautologique et toujours verte : une sonde qui ne peut pas être rouge ne
> prouve rien. Le rejouer en simulation : sans worker elle doit être ROUGE, avec
> un `sh -c '... <motif> ...'` vivant elle doit être VERTE (sémantique
> préservée). **(2) Un mot de passe Postgres ne s'applique qu'à l'`initdb`** : le
> changer sur un volume `pgdata` existant fait échouer l'app en `password
> authentication failed` — garder l'ancienne valeur ou `docker compose down -v`.
> **(3) Après le passage d'une image en non-root, les fichiers root-owned du
> bind-mount doivent être rechownés par l'hôte** (`sudo chown -R $(id -u):$(id -g)
> api/`) — et l'uid de l'image aligné (`APP_UID`/`APP_GID`) si l'uid hôte diffère
> de 1000. **(4) Un workflow de gardes de scripts ne vérifie pas un build
> d'image** : ne jamais écrire « à confirmer par la CI (edge-ci) » pour un
> `docker build` (edge-ci n'en fait aucun) — dire « non vérifié — build
> manuel ».
Derniere mise a jour : 2026-09-21 (lots sécurité backend #7995/#7999 + BC-01 PLATFORM #7973..#7978 + session PM #7963/#7966/#7958/#7967 + suivi de revue #8023 — fusion des deux blocs de leçons)

> Leçon 2026-09-21 (#8023) : **(1) un « read-tool » qui délègue à un service canonique hérite de TOUS ses
> effets** — lire le `match($mode)` jusqu'au bout avant d'écrire « cet outil n'envoie JAMAIS rien » :
> en politique `auto`, `CommunicationReplyService::prepare()` appelait `GoogleGmailReplySender::send()`,
> un envoi synchrone et irréversible déclenché par un tool call du LLM SANS validation humaine. Un
> chemin read-tool s'immunise par un paramètre EXPLICITE au service (`allowAutoSend: false` → mode
> rétrogradé en `confirm`), pas par un commentaire ; le chemin canonique (job/endpoints) garde son
> défaut, et un test à spy sur le sender verrouille les deux sens (rouge sans correctif).
> **(2) tout `down()` de migration de contrainte CHECK doit dire s'il est LOSSY** : ré-imposer une
> liste antérieure plus étroite fait échouer le rollback en SQLSTATE 23514 dès que des valeurs
> nouvelles (ici `dispatcher`/`delivery_manager`) ont été écrites entre `up()` et `down()` — et le
> DROP déjà exécuté laisse la table SANS contrainte. Documenter la remédiation (purge des valeurs
> avant rollback), et se demander si le rollback a encore un sens (l'union EST le correctif).

> Leçon 2026-09-21 (#8020, suivi #8005) : **(1) la matrice `platform.permission` (#7553) se
> vérifie par SONDAGE, pas par grep** — après un lot de durcissement, re-scanner le groupe
> `/admin` ET `/platform` jusqu'à zéro `Route::` sans `->middleware('platform.permission:...')`
> (hors `auth/*` et groupes déjà gardés) : #8020 a trouvé 13 routes oubliées (hr-reports,
> ai/*, training/*, fleet/alerts, survey-stats, ai monitoring/health, country-defaults,
> payroll/simulate). **Un alias = la même permission que sa route canonique**, et un contenu
> tenant CROSS-TENANT se garde au niveau le PLUS fort du catalogue réutilisé (`companies.manage`),
> jamais au niveau de confort du rôle qui l'utilise aujourd'hui. **(2) Piège de test de garde :
> le binding implicite (`SubstituteBindings`, groupe `api`) s'exécute AVANT les middlewares de
> route** — dès qu'un paramètre est lié à un modèle (`WebhookEndpoint $webhookEndpoint`), un 403
> de permission devient 404 sur une ressource absente (c'est ce qui rend
> `test_webhooks_require_webhooks_manage` rouge seul et vert en suite complète). Tester la garde
> sur une route SANS binding, ou inspecter la définition (`Route::getRoutes()` →
> `gatherMiddleware()` contient `platform.permission:<perm>`), comme
> `PlatformPermissionMatrixDeploymentTest`. **(3) Worktree + `vendor` symlinké** : les tests d'un
> worktree chargent l'app du dépôt qui porte le `vendor` (`Application::inferBasePath()`) —
> lancer `APP_BASE_PATH=<worktree>/api php artisan test ...` sinon on teste le code d'un autre
> checkout.
Derniere mise a jour : 2026-09-21 (lots sécurité backend #7995/#7999 + BC-01 PLATFORM #7973..#7978 + session PM #7963/#7966/#7958/#7967 + perf #7985)

> Leçon 2026-09-21 (#7985) : **(1) un test de comptage de requêtes doit exclure la
> plomberie de connexion** — `SHOW search_path` / `SET search_path` (bascule
> tenant) varient d'un appel à l'autre DANS le même test : un `assertSame` sur le
> total est flaky (observé 9 puis 10 requêtes pour la même requête HTTP).
> Ne compter que les requêtes métier (`SELECT/INSERT/UPDATE/DELETE`).
> **(2) une optimisation « agrégat » n'est PAS prouvable par le seul nombre de
> requêtes** — `->get()` + `sum()` en PHP et l'agrégat SQL émettent le MÊME
> nombre de requêtes : la garde utile est l'assertion sur le SQL (`sum(amount)`,
> `group by "status"`) PLUS l'invariance N vs 2N lignes. **(3) les suites citées
> par une issue peuvent être ROUGES sur main** — `RestaurantPublicShopTest` (9
> tests) pointe encore `/api/v1/public/restaurant/menu|orders` alors que les
> routes vivent sous `/public/restaurant/shop/*` : lancer `phpunit <fichier>`
> AVANT d'y ajouter des tests, et poser les nouveaux tests dans une suite verte.
Derniere mise a jour : 2026-09-21 (lots sécurité backend #7995/#7999 + BC-01 PLATFORM #7973..#7978 + session PM #7963/#7966/#7958/#7967 — fusion des deux blocs de leçons ; + tranche 2 déblocage main #8004)

> Leçon 2026-09-21 (#8004) : **(1) `PendingCommand::assertExitCode()` est PARESSEUX.**
> `$cmd->assertExitCode(0);` n'exécute PAS la commande — il enregistre l'attente et
> l'exécution réelle est différée au `__destruct` du `PendingCommand`. Tout test qui
> assertait ensuite la file d'attente (`Queue::assertPushed`) ou la base observait donc
> l'état **d'avant l'exécution** : un rouge fantôme, très difficile à attribuer au vrai
> coupable. Écrire `$this->artisan('x')->assertExitCode(0)->run();` (ou ne rien asserter
> après). Corollaire : un échec de test peut cacher un bug de test — avant de chercher
> dans le code applicatif, vérifier que le test observe bien l'état APRÈS.
> **(2) Laravel n'auto-découvre QUE `app/Console/Commands`.** Toute commande rangée sous
> `app/Modules/*/Console/Commands` doit être listée dans le `$this->commands([...])` de son
> Provider (pattern #7420) : sinon `artisan` répond « Command not found » **et** un doublon
> racine homonyme peut masquer l'implémentation du module selon l'ordre d'enregistrement
> (4 collisions constatées : `travel:expire-pending-bookings`, `leopardo:travel:import-legacy`,
> `leopardo:restaurant:stock-alerts`, `pilot:seed`). Détecter : extraire les `$signature`
> des deux arborescences et comparer les PREMIERS mots (les options changent d'une ligne à
> l'autre, le nom non).
> **(3) Un test rouge peut masquer les suivants.** Les doublons `assertDispatchedCount()`
> (méthode inexistante, vrai nom `assertDispatchedTimes()`) n'étaient visibles qu'APRÈS
> l'enregistrement de la commande : l'`InvalidOptionException` échouait plus tôt. Réparer
> une cause racine en révèle d'autres — prévoir plusieurs passes.

> Leçon 2026-09-20 (#7995/#7999) : **(1) une politique de validation = un helper unique**
> — les 3 sites historiques de la norme mots de passe étaient dupliqués textuellement ;
> les centraliser dans `PasswordPolicy` ET refactoriser les sites existants évite la
> prochaine dérive. **(2) `BelongsToCompany` est sans danger sur les routes publiques**
> (no-op sans compagnie liée) : l'ajouter « par défense » sur un modèle lu publiquement
> ne casse rien et protège la surface tenant future. **(3) toute garde nouvelle doit
> embarquer la liste des cas préexistants** (legacy_pending_review) — une garde qui
> échoue sur l'existant ne sera jamais mergée.

> Leçon 2026-09-20 (#7973/#7975) : **(1) un grep littéral ne prouve pas l'absence**
> — `rg "Schema::create('users'"` = 0 ne voulait pas dire « users sans migration » :
> `public/2026_05_02_100001` la crée via `createTableIfMissing($variable)`. Avant de
> créer une migration « manquante », chercher aussi les helpers avec variable
> (`createTableIfMissing`, `schemaTableExists`) et les builds Schema::create($var).
> **(2) Toute nouvelle route `/platform/*` ou `/admin/*` DOIT porter
> `platform.permission:<perm>`** — #7973 a trouvé 7 blocs oubliés (purge tenant,
> plans, webhooks, réglages, audit paie) + des alias `/admin/edge-nodes` qui
> perdaient la garde de leur route canonique : un alias = la MÊME permission.
> **(3) `leopardo:migrate --fresh` exige `--force` hors interaction** (#7974) —
> refus sec en production ; penser à répercuter dans Makefile/scripts/tests.

> Leçon 2026-09-20 (session PM, audit sécurité 2026-09-20) :
> 1. **Drift déploiement dev** : la garde `deploy-drift-guard.yml` compare le health
>    public au dernier commit `api/` — un service Render en retard se réaligne par
>    l'API Render (`POST /v1/services/{id}/deploys`, clé du workspace) SANS attendre
>    un push, puis `workflow_dispatch` de la garde pour constater le vert.
> 2. **APP_KEY Render** : `generateValue: true` dans un blueprint = régénération à
>    chaque re-sync. Le pin SÉCURISÉ = lire la valeur courante via l'API
>    (`GET .../env-vars/APP_KEY`), la reprendre telle quelle (`PUT`), PUIS passer le
>    blueprint en `sync: false` — zéro rotation, zéro invalidation de session (#7967).
> 3. **Biométrie kiosque (#7958)** : `method=fingerprint/face` sans preuve device est
>    désormais dégradé en `manual` + audit `UNVERIFIED_IDENTITY` APRÈS la matrice
>    BIO-006 (méthode désactivée → 422 intact). `manual` est accepté hors matrice :
>    c'est le mode déclaratif honnête. Le vérificateur de preuve se branchera sur
>    `KioskAttendanceService::hasVerifiableBiometricProof()` (fail-closed par défaut).
> 4. **Fail-fast URL backend (#7963)** : `next build` SANS `NEXT_PUBLIC_API_URL`
>    échoue désormais — tout workflow qui build un front doit poser la variable
>    (localhost en CI), et tout projet Vercel doit l'avoir par cible (production ET
>    preview, sinon previews rouges — vérifier via l'API Vercel `GET /v9/projects/{id}/env`).
> 5. **Images edge non-root (#7966)** : nginx :8080 + `USER www-data` +
>    `supervisord -u www-data` (no-op si déjà www-data, filet si `--user root`) ;
>    penser à chowner `/var/log/supervisor`, `/var/lib/nginx`, `/var/log/nginx`,
>    `/run/nginx` et à propager le port dans compose/Caddyfile/install.sh.

> Leçon 2026-09-19 (HC-001..008 #7785..#7792) : créer une VERTICALE complète = 8 points
> d'enregistrement au-delà du module lui-même, tous vérifiés par des gardes locales :
> catalogue (`SolutionCatalogue` via provider), `config/feature-flags.php`,
> `Company::KNOWN_MODULES` (leçon #7220/#7235), registre BC + CODEOWNERS + arêtes MAT-002,
> parité docs architecture ×4 (compteur de modules !), fixture `CreatesMvpSchema` (#5443),
> couverture OpenAPI (63 ops sinon drift), préfixes protégés front (`protected-prefixes.ts`
> + `proxy.ts` + `sw.js` — les tests Jest du dépôt le rattrapent). Lancer TOUTES les gardes
> `dev-hub/tools/check-*.sh` AVANT le push évite chaque aller-retour CI.

Ce fichier doit etre lu au debut de chaque nouvelle session agent. Il doit aussi etre mis a jour a chaque push ou merge vers `main`, comme le `CHANGELOG.md`, des qu'une lecon operationnelle peut eviter de perdre du temps plus tard.

> **Leçons historiques : voir `docs/GESTION_PROJET/LECONS_AGENTS.md`** (leçons datées,
> post-mortems et historiques d'incidents extraits de ce guide — #7843).
> Bibliothèque transversale des pièges connus (vue rapide) : `docs/GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md`.
> Toute nouvelle leçon opérationnelle = mise à jour AGENTS.md **et**, si c'est un piège
> rejouable, une ligne dans la bibliothèque des erreurs. Flux pas-à-pas :
> `docs/GOUVERNANCE/RETEX_FLUX.md` (constat → issue [LECON] → leçon, protocole P04).

> **NOUVEL AGENT ? Commence par lire `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (2 min) pour une carte de reference rapide. Ce fichier AGENTS.md est le guide complet.**

> **Cadre de protocoles opposables (P01-P07) : `docs/PROTOCOLES/README.md`** — validation marché
> (P01), onboarding (P02), vitrine & présentation (P03), issues/tâches par expérience (P04),
> design harmonisé (P05), desktop Windows/macOS par BC (P06), architecture dev/prod (P07).
> État, propriétaires et travaux ouverts : `docs/PROTOCOLES/REGISTRE_PROTOCOLES.md`.
> **Revue mensuelle** le dernier jour ouvré (gabarit `.github/ISSUE_TEMPLATE/revue_mensuelle.md`) ;
> moisson des leçons le 1er du mois (`docs/GESTION_PROJET/MOISSON_LECONS.md`).
> En cas de conflit de règle : `.specify/constitution.md` > `AGENTS.md` > `docs/PROTOCOLES/` >
> `docs/GOUVERNANCE/` > `docs/ops/`.

## ⚡ Spec-Driven Development — Spec Kit (NOUVEAU 2026-08-14)

Leopardo utilise desormais **GitHub Spec Kit** pour structurer tout travail significatif.
Lire `.specify/constitution.md` — c'est la loi fondamentale du projet.

### Commandes disponibles (GitHub Copilot skills)

| Commande | Role |
|----------|------|
| `/speckit-constitution` | Principes directeurs du projet |
| `/speckit-specify` | Creer une spec avant de coder |
| `/speckit-clarify` | Clarifier les ambiguites (avant plan) |
| `/speckit-plan` | Plan technique et architecture |
| `/speckit-tasks` | Generer les taches actionnables |
| `/speckit-analyze` | Verifier coherence avant implementation |
| `/speckit-implement` | Executer les taches |
| `/speckit-converge` | Detecter le travail restant |

### Presets actifs (injectes automatiquement dans toute spec)

| Preset | Se declenche sur |
|--------|-----------------|
| `leopardo-payroll` | Toute spec touchant `api/app/Modules/Payroll/` |
| `leopardo-multitenancy` | Toute spec ajoutant une table ou un endpoint API |
| `leopardo-dz` | Specs paie algeriennes (IRG/CNAS) |
| `leopardo-cemac` | Specs paie zone CEMAC (CM/GA/CG/XAF) |
| `leopardo-cedeao` | Specs paie zone CEDEAO (CI/SN/BF/ML/XOF) |

### Workflow standard (remplace la creation manuelle d'issues)

```
1. /speckit-specify  "description de ce que tu veux construire"
2. /speckit-clarify  (si ambiguite)
3. /speckit-plan     (architecture)
4. /speckit-analyze  (verifier dependances manquantes)
5. /speckit-tasks    (generer les taches)
6. /speckit-implement (coder)
```

### Regle anti-doublon (CRITIQUE — protocole durci issue #2400)

L'auto-assignation seule ne protege pas la fenetre implementation : plusieurs
agents peuvent travailler en parallele sur la meme issue (constate le
2026-08-15 : #2333 ×3 PRs, #2329 ×2 PRs, #2264 ×2, #2326 ×2 branches...).
Protocol OBLIGATOIRE avant de commencer a coder :

1. **Verifier TOUTES les branches, pas seulement les PRs** — le nom de
   branche EST le lock :
   ```bash
   gh api repos/kitokoh/leopardo-hr/branches --paginate | grep -i "<issue>"
   gh pr list --state open --json number,title,headRefName
   ```
   Une branche `fix/<issue>-*` existante = l'issue est prise → contribuer
   dessus ou s'arreter (constitution §I « deux agents ne peuvent pas
   implementer la meme spec »).
2. **Marker branch immediat** : des le self-assign
   (`gh issue edit <N> --add-assignee @me`), pousser une branche
   `fix/<issue>-<slug>` avec un commit vide de claim (message
   « claim marker #N »). Le premier-arrive conserve sa branche ; tout agent
   qui voit la branche pour la meme issue contribue dessus ou s'arrete.
3. **Nommage de branche UNIQUE par issue** : un seul `fix/<issue>-*` par
   issue. Pas de suffixes multiples (`fix/2333-a`, `fix/2333-b`...).
4. **Fermeture des doublons** : toute PR dupliquee sur une meme issue est
   fermee avec un commentaire de renvoi vers la PR canonique (1 PR = 1 issue).

### Affectation par Bounded Context (labels BC — registre #5859)

Le registre automatise des bounded contexts (`dev-hub/governance/
bounded-context-registry.json`, BC-01 PLATFORM .. BC-26 DELIVERY) est la carte
canonique. Toute issue ouverte est etiquettee avec son BC (`BC-01 PLATFORM`,
`BC-02 TENANT`, `BC-11 CRM`, `BC-15 FUEL`, `BC-16 EDU`, ...).

1. **Affectation par BC, jamais par surface vague** : le fondateur/chef de
   projet confie le travail par BC — ex. « travaille sur les issues
   etiquetees BC-15 FUEL » ou « issues du BC-01 PLATFORM ». Un agent
   selectionne UNIQUEMENT des issues du BC qui lui a ete confie.
2. **Un seul agent par BC a la fois** : deux agents peuvent travailler en
   parallele sur des BC DIFFERENTS, jamais sur le meme BC. Le verrou
   reste la regle anti-doublon ci-dessus (`fix/<issue>-<slug>` + claim
   marker) : si une branche existe deja pour une issue du BC, contribuer
   dessus ou s'arreter.
2bis. **Lot d'issues d'un meme BC : une branche par lot, pas une par issue**
   (`docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`, 2026-08-29) — quand
   plusieurs issues du meme BC sont confiees a un agent, il ouvre UNE branche
   `bc/<code-bc>-<slug>` et y accumule un commit par issue livree, puis ferme
   toutes les issues du lot dans une seule PR (`Closes #N` repete par issue).
   Objectif : reduire le nombre de branches/PRs/runs CI par lot (constat
   `docs/infra/02_alignement/CI_SATURATION.md`). Le protocole `fix/<issue>-*`
   (une branche par issue) reste la norme pour une issue isolee hors lot, un
   hotfix, ou le programme CRM qui garde son protocole dedie plus strict
   (`CRM_BRANCH_PROTOCOL.md`, une issue = une branche = une PR).
3. **Nouvelle issue sans label BC** : verifier le registre
   `bounded-context-registry.json` (chemin racine -> BC) et ajouter le
   label avant de commencer.
4. **Une branche reste dans son BC** : pas d'import cross-BC (garde Module
   Structure Validator), pas de migration tenant sans `company_id` (garde
   Hygiene Guards).

### Garde migrations AVANT push (issue #1962 — 3 occurrences le 2026-08-24)

Toute PR ajoutant ou renommant une migration (`api/database/migrations/*`)
doit verifier AVANT push l'absence de collision de prefixe de sequence
(`YYYY_MM_DD_0000NN`) — Laravel indexe les migrations par basename, une
collision rend `main` ROUGE pour TOUTES les PRs (garde Hygiene Guards) :

```bash
bash dev-hub/tools/check-migration-basename-collisions.sh
```

Un prefixe deja pris = renumero ter (ex. `000006` -> `000007`) en gardant
l'ordre chronologique (la migration la plus ancienne conserve son prefixe).
Le commit precedent `fix/1962-*` est l'exemple canonique.

## Garde « une table, une migration » (#7452 / tranche #7455)

Deux migrations qui font `Schema::create('<table>')` sont **une seule et même
déclaration** : la première exécutée gagne (`if (! schemaTableExists())`) et les
suivantes sont ignorées **silencieusement**. Quand leurs colonnes divergent, tout
le code écrit contre la dernière génération casse en `column "x" does not exist`
— souvent masqué par une cascade `25P02` (223 échecs de `tests/Feature/Travel`).

- **Ne jamais** créer une table par un second `Schema::create` : pour rattraper
  une colonne, une migration `Schema::table` **idempotente** (`schemaHasColumn`).
- Vérifier localement avant push :
  ```bash
  python3 dev-hub/tools/check-duplicate-schema-create.py --base origin/main --strict --fail-on-duplicate
  ```
  (garde CI `.github/workflows/migration-duplication-guard.yml`). **Depuis l'audit de
  clôture #7452 (2026-09-18), la dette est SOLDÉE à l'échelle du dépôt (0 table
  dupliquée, 0 divergente — tranches Travel #7467 et EDU #7571) et la garde tourne
  en zéro tolérance par défaut** : toute PR qui redéclare une table existante est
  rouge, même sans divergence de colonnes. L'inventaire historique reste dans
  `docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md` (`--write` régénère le document).
- La résorption s'est faite **module par module** (#7452, #7417, #7410) — terminée.
  Historique de la tranche Travel et recette détaillée de consolidation d'un module :
  voir `docs/GESTION_PROJET/LECONS_AGENTS.md`.

## Garde post-merge `Closes #` (issue #2512)

Une PR qui **mentionne** une issue (`#1234`) sans mot-clé `Closes #` (ou
Fixes/Resolves) dans le **body** ne ferme JAMAIS l'issue au merge : elle
reste ouverte même une fois le correctif livré. Règles :

- Le mot-clé `Closes #N` doit être dans le **body** de la PR (le titre seul
  est fragile : GitHub ne le traite pas toujours).
- Les mentions entre parenthèses `(#1234)` ou dans le contexte ne ferment
  rien — si l'issue doit être close, ajouter `Closes #1234` dans le body.
- Garde de détection : `dev-hub/tools/check-issues-left-open-by-merged-prs.sh
  <owner/repo>` liste les issues référencées par des PRs mergées mais restées
  ouvertes sans PR en cours (rapport non bloquant — fermeture manuelle avec
  preuve code : commentaire + état closed).
- Fallback : fermeture manuelle avec vérification du code sur main.

## Garde anti « ghost close » (issue #4816)

Une issue ne doit être **clôturée que lorsqu'un correctif est réellement
mergé** (auto-close par `Closes #N` sur main) **ou** avec un commentaire
motivé (`wontfix` / `superseded` / renvoi vers le ticket canonique).
Clôturer « pour faire propre » sans code casse le backlog : le visuel devient
vert alors que le correctif n'existe pas (vague du 2026-08-17 : #4690/#4687/
#4688/#4305/#4410 fermées non résolues, vérifié sur main).

- Règle : **jamais de `gh issue close` sans commit de merge associé OU sans
  commentaire de motivation explicite.**
- Garde de détection : `dev-hub/tools/check-issues-closed-without-merge.sh
  <owner/repo>` liste les issues clôturées sans commit de fermeture ET sans
  PR mergée les référençant (rapport non bloquant — ré-ouverture/correction
  manuelle avec preuve code, même esprit que #2512).
- Si une issue a été clôturée à tort : la ré-ouvrir, créer le ticket de
  correctif dédié, et référencer la vérification code dans un commentaire.

## SLA bugs pilotes (issue #5155)

- **Promesse** : un bug **bloquant** pilote (paie / pointage / login impossible
  en prod) se regle en **moins de 24 h** (deploiement prod inclus).
- **Canal** : issue avec le template `PILOT_BLOCKER`
  (`.github/ISSUE_TEMPLATE/pilot_blocker.yml`) + label `pilot-blocker` — voir
  `docs/ops/SLA_PILOTES.md`.
- **Triage** : bloquant = paie/pointage/login impossible ; tout le reste est P2.
- **Hotfix** : branche `hotfix/<issue>-<slug>`, CI minimale (tests paie + E2E
  funnel), deploy prod < 24 h, post-mortem court en cas de recidive.
- **Metrique** : delai moyen de resolution des bloquants, tableau hebdo dans
  le bilan du vendredi.

## Regles obligatoires

- **Drain de crise (#7562, 2026-09-16)** — quand plusieurs agents ont livré le **même sujet** sur des branches concurrentes, qu'aucune PR n'est mergeable (protection `strict` : chaque merge invalide les autres) et que la file CI est le facteur limitant : appliquer **`docs/GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md` §6** (une PR de lot, un sujet = une implémentation canonique, pilotage de la file, outillage local, critères de sortie). **Ce n'est pas un mode de travail** : les 4 checks requis gardent leur rôle, aucune PR ne se merge avec un check requis rouge, et le drain suivant se fait PR par PR. Un drain se termine quand il ne reste QUE `main`, propre et verte — jamais « presque ».

- **Protocole branches CRM (#5746)** : pour toute issue du programme CRM (#5705→#5731, #5735→#5746), suivre `docs/GOUVERNANCE/CRM_BRANCH_PROTOCOL.md` — une issue = une branche = une PR ; marker branch immédiat après claim ; base `main` à jour ; migrations avec réf issue dans le nom ; jamais d'auto-merge d'une PR rouge ; arrêt si `main` rouge. Le garde `dev-hub/tools/check-crm-branch-protocol.sh` (workflow `crm-branch-protocol.yml`) signale doublons de branches, PRs sans `Closes #N` et PRs trop grosses.

- Les leçons datées historiques (garde mock #4164, quota Vercel #4868, rafale de
  pushes swe-qa-360…) sont dans `docs/GESTION_PROJET/LECONS_AGENTS.md`.

- **Lecon 2026-09-08 (#6590)** : les fichiers generes mobile NE SONT PLUS
  COMMITES (`.gitignore` racine : `*.g.dart`, `*.freezed.dart`,
  `**/lib/l10n/generated/`) — regeneres en CI. Tout job qui analyse, teste ou
  build une app consommant `leopardo_core` doit d'abord regenerer dans
  `front/mobile_apps/leopardo_core` : `flutter pub get && flutter gen-l10n &&
  dart run build_runner build --delete-conflicting-outputs` (le codegen drift
  + json_serializable exige les dev_dependencies du package core resolues —
  un `pub get` dans le dossier core est requis, celui de l'app ne suffit
  pas). La garde ARB l10n (#4762) tourne desormais APRES `flutter gen-l10n`
  dans le job `flutter-analyze` (projet leopardo_core) — plus dans le job
  guard (checkout brut sans generes). Tout ajout de cle ARB sans regenerer
  reste capture a la compile (#4762) ; ne jamais re-commiter un genere.

- **REGLE D'OR POUR LES NOUVEAUX MODULES** : Avant de commencer a coder un nouveau module ou de generer des tickets (GitHub Issues) pour celui-ci, un agent DOIT OBLIGATOIREMENT creer un fichier Markdown de specification dans le dossier `docs/specifications/` (ex: `docs/specifications/MODULE_RECRUTEMENT.md`). Ce n'est qu'apres validation explicite de ce document par le proprietaire que les issues GitHub peuvent etre creees.

- Avant de travailler sur une branche existante, faire `git fetch origin main` puis comparer avec `origin/main`.
- `main` distant est la source de verite. Le local doit rester aligne sur `origin/main` apres chaque intervention terminee.
- Ne pas pousser directement sur `main` si la branche est protegee. Creer un PR, attendre les checks GitHub Actions, puis merger et supprimer la branche.
- Apres un merge dans `main`, supprimer la branche distante et nettoyer les branches locales devenues inutiles.
- Ne jamais perdre les stashes existants. Verifier `git stash list` avant toute operation destructive.
- Chaque changement de comportement, migration, CI ou procedure doit avoir une entree `CHANGELOG.md`.
- **CHANGELOG.md (issue #2417)** : toute PR ajoute son entree sous `## [Unreleased]` avec la categorie adaptee (`### Added` / `### Changed` / `### Fixed` / `### Removed`) — Keep a Changelog. Les sections versionnees (`## [x.y.z] - date`) sont creees a la release ; l'historique integral vit dans les notes de release GitHub et dans l'historique git (`CHANGELOG_ARCHIVE.md` a ete sorti du depot — #7654, derniere version : blob `6a3819a`).
- Chaque connaissance utile pour les prochains agents doit etre ajoutee ici.

## 🗺️ Cartographie de l'Ecosysteme Leopardo (A respecter strictement)

Le projet est une **Suite d'Applications** (1 App = 1 Metier). Voici les roles definis "noir sur blanc" :

### Les 8 Applications Mobiles Flutter (`front/mobile_apps/`)
- **`leopardo_employee`** : Application employee (self-service) — pointage GPS, absences, soldes, notifications.
- **`leopardo_manager`** : Application dediee a la gestion du tenant (entreprise). Vue globale, affectation des roles, evolution.
- **`leopardo_hr`** : Application dediee aux Ressources Humaines. Suivi des employes, presences/absences, taches, et gestion du recrutement (ATS).
- **`leopardo_marketing`** : Application dediee aux marketeurs. Planification et publication en "1-clic" sur les differents reseaux sociaux.
- **`leopardo_platform_admin`** : Application ultra-securisee pour le Super-Admin (proprietaire du SaaS) pour gerer les abonnements et l'infrastructure.
- **`leopardo_accounting`** : Application dediee a la comptabilite (facturation, suivi des impayes). Integree a melos et a la CI mobile (voir `front/mobile_apps/README.md`).
- **`leopardo_travel_agent`** : Application dediee aux agents/vendeurs de la verticale TravelAgency — vente guichet multi-passagers, encaissement cash, check-in QR, manifeste, caisse PDV (TRAVEL-701 #6088 / TRAVEL-810 #6100).
- **`leopardo_cameras`** : Application dediee a la surveillance video « Leopardo Cameras » (BC-19 DEVICE, #7426) — mur des cameras du tenant, visionnage direct via stream-token (chaine video MediaMTX #7424), evenements et alertes (#7427). Reservee aux responsables (module `cameras` + `api.manager`) ; integree a melos et a la CI mobile.

> `leopardo_core` est le package partage (design system, API client, modeles, l10n) consomme par les 8 apps.
> La liste canonique des apps mobiles est `front/mobile_apps/README.md` (a jour avec melos.yaml).
> Le **kiosk/biometrie n'est PAS une app Flutter** : c'est une web app offline-first (`front/zkteco-kiosk`,
> pointage local `/local/punch` + bridge ZKTeco) — cf. `front/zkteco-kiosk/README.md`.
>
> **Regle mobile = OPTION (#9275)** : le mobile n'est jamais une obligation. Toute operation de stock
> livree en mobile doit exister en web (le web est la reference). La parite est documentee dans
> `docs/mobile/STOCK_MOBILE.md` et gardee par `dev-hub/tools/check-mobile-stock-parity.sh`.

### L'Ecosysteme Web (`front/`)
- **La Web App Client (`front/web` et admin-dashboard)** : Le portail web client est **unique**. Un employe, un RH ou un Manager se connecte au meme portail, mais l'interface s'adapte dynamiquement et change completement en fonction du role (RBAC).
- **La Web App Super-Admin** : Interface web reservee exclusivement a l'administration de la plateforme Leopardo (SaaS).

### Freeze scope 60 jours (issue #5147, plan 60 jours)
Toute feature hors du périmètre autorisé de `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` est **refusée en revue** avec renvoi vers ce document. Les exceptions passent par une issue `[FREEZE-EXCEPTION]` décidée par le fondateur — jamais par l'agent lui-même.

## ⚠️ NOUVELLE METHODE DE GESTION DE PROJET (Juillet 2026)

**ATTENTION AGENTS** : Les anciens dossiers `docs/PLAN_ACTION/` et `docs/PLAN_ACTION2/` sont **obsoletes et archives**. Il est **strictement interdit** de lire ces dossiers pour chercher du travail ou d'y creer de nouveaux fichiers Markdown de planification.

La gestion du projet Leopardo se fait desormais **exclusivement via GitHub Issues et GitHub Projects**.

### Regles de selection d'une tache (GitHub Issues)

1. **Lister les tickets ouverts** : `gh issue list --limit 50 --state open --json number,title,labels,assignees`.
2. **Filtrer** : Ne choisissez **que** les issues qui n'ont pas d'assignes (`assignees` vide) ET qui possedent des criteres d'acceptation clairs dans leur description (`gh issue view <number>`). Idealement, cherchez le label `Agent-Ready` ou `good first issue`.
3. **S'assigner** : Avant de coder, vous DEVEZ vous assigner l'issue, ou annoncer que vous la prenez pour eviter que deux agents ne fassent la meme chose.
4. **Fermeture automatique (CRITIQUE)** : Votre Pull Request (PR) **doit obligatoirement** contenir `Closes #<numero_issue>` dans sa description pour fermer l'issue automatiquement au merge.

### Comment demander une review

- Une fois le travail complet et verifie localement (tests pertinents, `shellcheck`/lint si applicable), passez la PR draft en "Ready for review" : `gh pr ready <numero>`.
- Ne jamais merger sa propre PR sans que les checks CI obligatoires (`gh pr checks <numero>`) soient verts.
- Assurez-vous que la description de la PR indique clairement quelle issue P0/P1 est resolue.

### Bibliotheque de prompts operationnels

Le dossier `dev-hub/prompts/` contient des prompts numerotes prets a l'emploi pour piloter les agents. Chaque prompt est un fichier Markdown autonome avec des instructions executables.

- **Carte rapide** : `dev-hub/prompts/00_AGENT_QUICK_CARD.md` — resume des regles vitales (2 min)
- **Vider le backlog** : `dev-hub/prompts/01_DRAIN_BACKLOG.md` — traiter tous les tickets
- **Audits** : prompts 02, 05-09 — auditer chaque surface du projet
- **CI/Merge** : prompts 03, 12 — reparer la CI, merger les branches
- **Anti-regression** : `dev-hub/prompts/13_REGRESSION_GUARD.md` — traquer les patterns interdits
- Voir `dev-hub/prompts/README.md` pour l'index complet

## Prérequis Git LFS (#4124)

`assets/**` (design, screenshots) et les icônes mobiles sont trackés en **vrai
Git LFS** (pointeurs) — les médias vitrine (`front/web/public/**`) sont des
binaires réels hors LFS. Un agent clonant sans git-lfs verra des fichiers
pointeurs (~130 o) pour les assets LFS : installer git-lfs (`git lfs install`)
pour les résoudre au checkout.

## Strategie CI rapide

> La strategie CI rapide (workflows, fast-path, saturation du pipeline, relances) a ete
> deplacee vers `docs/ops/STRATEGIE_CI_RAPIDE.md` (issue #6698 — desengorgement d'AGENTS.md).

## Pieges connus

> Les pièges datés (drain de crise 2026-09-16, merge lane 2026-09-08, dérive dev
> Render 2026-09-15, audits 2026-05-13/14, incidents Vercel…) sont consignés dans
> `docs/GESTION_PROJET/LECONS_AGENTS.md`.

### Tier dev multi-stacks (#8659, 2026-10-17)

- Le tier dev tourne en **rotation de stacks** (registre `deploy/dev-stacks.json`, runbook `docs/ops/DEV_STACK_ROTATION.md`) : avant de toucher au déploiement dev, lire `DEV_ACTIVE_STACK` et le statut des stacks — un service Render « mort » est souvent juste suspendu billing (quota 750 h/mois).
- **Un keepalive 24/7 détruit le quota gratuit qu'il protège** (incident du 2026-09-20 : stack « a » suspendue ~10 jours après épuisement). Tout ping de keepalive doit rester **fenêtré** (06–23 UTC, cadence 10 min) et ne cibler que la stack ACTIVE — jamais une standby.
- La bascule dev se fait via `scripts/dev-stack-switch.sh` ou le workflow `dev-stack-switch.yml` — jamais en éditant `DEV_API_BASE_URL` à la main sans healthcheck préalable de la cible.

### Bascules search_path PostgreSQL (BOS-019/#8204, ADR-0027)

- Toute bascule `SET search_path` passe par `TenantManager::withinSearchPath()` (try/finally garanti, validation fail-closed de la chaîne), ou par `App\Support\SearchPathGuard::run()` quand on bascule en dehors du cycle de vie tenant (action web, commande, service plateforme) : le helper capture `SHOW search_path` et le restaure en `finally`, y compris sur exception. Jamais de `DB::statement('SET search_path …')` nu — la garde `dev-hub/tools/check-search-path-guard.py` (job requis `Module Structure Validator`, baseline ratchet) fait échouer toute occurrence nouvelle. Le middleware `EnsureKioskSearchPathReset` (#3368) reste un filet, pas une permission.
- Le mode « un schéma par tenant » est MORT (garde `Company::booted()` conservée) : aucune nouvelle branche `tenancy_type === 'schema'` — inventaire borné des références legacy dans `docs/architecture/adr/0028-shared-schema-definitif.md`.

### Frontieres routes modules

- `routes/modules/rh.php` porte le socle RH transverse (employes, contrats, absences, rapports courants) alors que `routes/modules/hr_extended.php` porte les extensions post-MVP. Avant de deplacer une route, verifier le controller et le scenario de test associe.
- Les routes IA experimentales voice/agent restent sous feature AI + rate limit ; toute exposition plus large doit passer par une feature flag explicite et une couverture RBAC.
- Dans les extensions RH (`RecruitmentController`, `TrainingController`, `EmployeeLoanController`, `ExpenseClaimController`), les index doivent toujours demarrer par `where('company_id', $actor->company_id)` et les references employees/departments/positions/trainers/interviewers doivent etre validees dans le tenant courant.

### Paie multi-pays et exports bancaires

- Les tables `tax_slabs` et `social_contributions` sont creees par les migrations tenant. Le seeder `PayrollCountryConfigSeeder` doit etre lance dans le schema tenant courant, pas depuis un contexte public qui n'a pas ces tables.
- Les exports bancaires doivent utiliser les colonnes reelles de `employees` : `iban` et `bank_account`. Ne pas reintroduire `rib` ou `bank_name` sans migration correspondante.
- Les declarations sociales CNAS/CNSS/DSN doivent lire les salaries via le modele `Employee`, pas via `DB::table('employees')`, afin de respecter les casts `encrypted` (`national_id`). Les identifiants entreprise viennent de `companies.metadata` (`nis`, `affiliate_number`, `siret`, `tax_id`) ; ne pas reintroduire `companies.tax_id` ni `employees.hire_date`.
- Pour les barèmes fiscaux de paie, les tranches documentees sont inclusives (`0-5000`, `5001-20000`). Utiliser le helper progressif de `AbstractCountryRules` pour eviter les erreurs d'unite aux bornes.
- Pour tester `PayrollRunController` sans rendre la suite fragile face aux baremes/salary structures, binder un faux `PayrollCalculator` dans le container et verifier le contrat controller : run calcule, pay slip cree, validation/cancel et isolation tenant.

### Render et migrations PostgreSQL

Render peut rejouer des migrations dans un environnement ou certaines tables existent deja. Les migrations publiques doivent donc etre idempotentes.

Exemples resolus le 2026-05-06 :

- `2026_05_02_000003_create_company_requests_table.php` doit verifier `Schema::hasTable('company_requests')` avant `Schema::create`.
- `2026_05_02_100001_create_users_and_company_requests_tables.php` doit verifier l'existence de `users`, `company_requests` et `user_employee_links`.
- Si une migration touche une table tenant comme `employees`, verifier le `search_path` PostgreSQL et proteger avec `Schema::hasTable`.

Lecons 2026-09-06 (issues #6916/#6924, pre-flight migrations dev/prod) :

- **Les migrations du boot passent par l'hôte DIRECT de la base, jamais le
  pooler Neon** (`api/docker-entrypoint.sh` : `DB_MIGRATE_URL` si posée, sinon
  derivee de `DB_URL` en retirant `-pooler`) — le pooler en mode transaction
  aborte toute migration DDL en `SQLSTATE 25P02` (deploy `update_failed`).
- **Le search_path des migrations tenant au boot est `shared_tenants,public`**
  (aligné sur le runtime), pas `shared_tenants` strict : des tables billing
  héritées (`invoices`...) vivent dans `public` alors que leurs migrations sont
  dans le dossier tenant. Toute migration tenant qui ALTÈRE une table héritée
  doit rester non qualifiée + gardée (`schemaTableExists`/`schemaHasColumn`,
  #1613) ; ne pas réintroduire de search_path strict pour le dossier tenant.
- Garde migrations #1962 avant push sur toute PR touchant
  `api/database/migrations/*` : `bash dev-hub/tools/check-migration-basename-collisions.sh`.

### Main local divergent

Le poste local peut avoir un `main` divergent (`ahead`/`behind`). Dans ce cas :

- Ne pas tenter de fast-forward aveugle.
- Travailler depuis `origin/main` via une branche propre.
- Une fois les travaux merges, remettre le local en phase avec `origin/main` seulement apres avoir confirme qu'aucun changement local utile ne sera perdu.

## Procedure PR et merge

1. Creer une branche courte depuis `origin/main`.
2. Faire le changement minimal.
3. Ajouter `CHANGELOG.md` et `AGENTS.md` si une connaissance doit etre conservee.
4. Push la branche et creer un PR.
5. Observer avec `gh pr checks <numero>`.
6. Corriger uniquement les rouges.
7. Quand les GitHub Actions requis sont verts, merger avec `gh pr merge <numero> --merge --delete-branch`.
8. Verifier que le PR est `MERGED` avec `gh pr view <numero> --json state,mergedAt,mergeCommit`.
9. Verifier que la branche distante est supprimee avec `git ls-remote --heads origin <branche>`.

## Nettoyage branches

Objectif demande le 2026-05-06 : en local, ne garder que `main` aligne sur `origin/main`.

Procedure recommandee :

- Verifier `git status --short --branch`.
- Verifier les stashes avec `git stash list`.
- Supprimer les branches locales non `main` apres merge ou abandon explicite.
- Pour les branches distantes, commencer par les PR ouverts. Merger uniquement si les changements apportent une nouveaute utile a `main`, puis supprimer la branche.
- Ne pas supprimer une branche distante non analysee si elle contient du travail non merge ou non remplace.

## Federation de branches

- Pour les vieilles branches mobiles ou mixtes tres en retard sur `main`, ne pas merger la branche complete si le diff embarque des centaines de suppressions hors sujet.
- Preferer recuperer uniquement les fichiers utiles avec `git checkout <branche> -- <fichier>` dans une branche federatrice propre creee depuis `origin/main`.
- Cette approche a ete confirmee utile le 2026-05-06 pour reutiliser seulement les apports de `#269`, `#275` et `#298` sans reintroduire le bruit historique de branches anciennes.

## Historique utile & lecons

> Le journal « Historique utile » et les lecons associees ont ete archives dans
> `docs/archive/AGENTS_HISTORIQUE_UTILE.md` (issue #6698). Les leçons datées et
> post-mortems extraits de ce guide vivent dans `docs/GESTION_PROJET/LECONS_AGENTS.md`
> (issue #7843). Les regles actives de ce fichier font foi ; l'historique reste
> disponible pour tracabilite.

