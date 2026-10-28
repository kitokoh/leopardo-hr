# Décisions fondateur / PM — session du 2026-10-28

> **Objet** : implémenter les issues ouvertes de `Kitokoh/leopardo-hr` jusqu'à épuisement du
> périmètre codable, et trancher les points qui bloquaient sans dire qui doit agir.
> **Registre** : chaque décision dit *ce qui a été tranché*, *pourquoi*, *quelle preuve existe*,
> et *ce qui reste hors de portée d'un agent* (action propriétaire).
> **Politique appliquée** : `docs/GOUVERNANCE/MERGE_RAPIDE_CHECKS_REQUIS.md` — les 4 checks requis
> doivent être verts, merge en squash, jamais de force-push.

---

## D1 — Une seule implémentation par défaut : #8955/#8970/#8973 retenues, #8953/#8967/#8972 closes

**Contexte.** Six PR concurrentes corrigeaient des issues qui se recouvrent :

| PR | Branche | Cible | Verdict |
|---|---|---|---|
| #8968 | `fix/8725-8726-mvp-schema-fixture-parity` | déblocage CI transverse (#8725/#8726) | **mergée** |
| #8953 | `feat/8199-modules-consolidate` | palier 1 de #8199 | **close** — déjà sur `main` via #8938 |
| #8955 | `refactor/8199-unified-module-write` | palier 2 de #8199 + `Closes #8954` | **retenue** |
| #8967 | `refactor/backend-architecture` | lot #7962 + #8954 + #7595 | **close** — lot fourre-tout |
| #8970 | `refactor/7962-kiosk-provisioning-action` | #7962 | **retenue** |
| #8971 | `fix/outbox-contract-boundaries` | tranche de #8396 | **retenue** |
| #8972 | `fix/8954-metadata-modules-mirror` | #8954 seul | **close** — doublon de #8955 |
| #8973 | `refactor/7959-unify-module-route-middleware` | #7959 | **retenue** |
| #8974 | `feat/8231-lea-onboarding-ui` | #8231 | **retenue** |

**Décision.** Le correctif qui sert **aussi l'issue mère** est retenu ; les correctifs isolés du même
défaut sont fermés.

**Pourquoi.** #8972 et #8955 corrigent tous deux #8954 avec deux `ModuleMirrorWriter` distincts. Les
garder tous les deux recrée exactement le défaut que #8199 demande de supprimer : deux sources de
vérité pour une même vue dérivée. Même raisonnement pour #8967, qui porte en plus un périmètre sans
issue ouverte (#7595 est **close**, livrée par #7614).

**Preuve.** #8953 : `git ls-tree origin/main | grep ModulesConsolidate` → les 3 fichiers sont sur
`main`. #8967 : commit `97d64c78` réintroduit 268 lignes de vue webadmin pour une issue fermée.

---

## D2 — Un faux rouge de garde se corrige dans la garde, jamais dans le registre

**Contexte.** `Hygiene Guards` échouait sur #8973 : `check-golden-journeys.sh` ne trouvait pas 10
routes (`GJ-02 absences`, `GJ-04 accounting`, `GJ-05 crm`).

**Décision.** Corriger le **parseur**, pas la liste des golden journeys.

**Pourquoi.** Ces routes existent : elles sont montées par `ModuleRoutes::tenantModule('absences', ...)`,
un helper introduit par #7959. Le parseur ne connaissait que `Route::prefix(...)`, donc **toutes** les
routes des fichiers convertis disparaissaient de l'analyse. La correction « évidente » — retirer des
parcours du registre — aurait supprimé de la couverture métier pour faire passer une garde. C'est le
pire échange possible : on perd de la protection réelle pour un vert cosmétique.

**Preuve.** `check-golden-journeys.sh api` → exit 0, **11 parcours / 2364 routes résolues**
(avant : exit 1, 10 routes introuvables). Non-régression vérifiée sur `main` : exit 0, 2364 routes.

**Règle posée.** Quand une garde signale un manque et que le code est juste, la garde a un bug de
lecture. Le registre décrit la réalité métier ; la garde doit apprendre à la lire.

---

## D3 — Le catalogue i18n généré n'est jamais une source de vérité

**Contexte.** #8974 échouait sur `validate-and-sync` : `I18N_VALIDATION_FAILED — checksum mismatch
web:{fr,en,ar,tr}`.

**Décision.** Les clés `lea.*` sont ajoutées à `shared/i18n/locales/*.json` (la **source**) ; les
catalogues `front/*/src/lib/i18n/locales/*.json` sont **régénérés** par `sync-web.js`.

**Pourquoi.** Ajouter les clés directement dans le catalogue généré « marche » localement puis
disparaît au sync suivant, en laissant un checksum incohérent. Le symptôme (checksum) est éloigné de
la cause (mauvais fichier édité), ce qui rend le piège coûteux à rediagnostiquer.

**Preuve.** `sync-web.js` → `I18N_SYNC_WEB_OK` ; `validators/validate.js` → `I18N_VALIDATION_OK`
(4 locales) ; `jest LeaOnboarding` → **7/7** ; `tsc --noEmit` et `eslint --max-warnings 0` → OK.

---

## D4 — Une extraction sans test ne protège rien (#7962)

**Contexte.** #8970 extrayait `ProvisionKiosk` du `KioskController` (993 lignes) **sans aucun test**.

**Décision.** Ajouter `ProvisionKioskActionTest` (4 tests) avant merge, et typer la sortie de
normalisation plutôt que l'entrée.

**Pourquoi.** Le contrôleur portait deux règles non évidentes qu'une extraction peut perdre en
silence : la normalisation BIO-006 (`card` → `badge` — si elle disparaît, le pointage par badge est
refusé **sans erreur de provisioning**) et la garde fail-closed #6678 (ne rendre des credentials que
si la ligne est résolvable ; sinon supprimer l'orpheline et échouer bruyamment). Un test qui exerce
l'action directement est le seul contrat qui survit à la prochaine refactorisation du contrôleur.

Côté typage : `punch_methods` vient d'une requête **non validée**. Annoncer `list<string>` en entrée
est un mensonge qui masque les charges malformées. L'entrée est donc `array<array-key, mixed>|null`,
et la normalisation produit une copie explicitement `list<string>`.

**Preuve.** `php artisan test --filter=ProvisionKioskActionTest` → **4/4, 6 assertions** ;
`phpstan-strict.neon` → `No errors`.

---

## D5 — La liaison contrat → implémentation se fait en un point, pas six fois (#8396)

**Contexte.** #8971 : les six `*OutboxPublisher` faisaient `new DatabaseOutboxPublisher(...)`,
ce que `IdentityLayerBoundariesArchitectureTest` (#8717) refuse.

**Décision.** La liaison se fait **une seule fois**, dans `AppServiceProvider`. Les verticales ne
dépendent plus que du contrat `OutboxPublisher`.

**Pourquoi.** Lier six fois, c'est six endroits à corriger lors d'une rotation de configuration, et
un oubli passe inaperçu jusqu'à la **perte silencieuse d'un événement** — un défaut qui ne se voit
qu'en production, des semaines plus tard. Un point de composition unique rend le remplacement de
transport (base, Redis, KMS) mécanique et vérifiable.

**Limite assumée.** C'est **une tranche** de #8396 (≈364 échecs de tests sur `main`), pas la
résorption complète. L'issue reste ouverte.

**Preuve.** `IdentityLayerBoundariesArchitectureTest` → **5/5 OK** ; PHPStan strict → 0 erreur sur
les 7 fichiers de la PR.

---

## D6 — Ce qui exige une action propriétaire n'est pas « implémentable » : c'est escaladé

**Contexte.** Sur 22 issues ouvertes, plusieurs portent le label `blocked` pour une raison qui n'est
pas technique. Aucun agent ne peut les fermer, et prétendre le contraire serait un faux rapport.

| Issue | Nature du blocage | Qui peut agir | Ce qui est livré à la place |
|---|---|---|---|
| #7657 | `BRANCH_PROTECTION_TOKEN` + secrets backup S3 non posés | propriétaire du dépôt / console | diagnostic écrit + preuve que le code est prêt |
| #8191 | audit scheduler-duplicates sur **copie prod** | propriétaire (accès prod) | procédure prête, aucune exécution possible ici |
| #8285 | panne Cloudflare « Workers Builds: gestionemploye » | dashboard Cloudflare | diagnostic (échec instantané = build non lancé, pas un bug de code) |
| #8880 | désignation d'un responsable terrain à Dakar | décision humaine | — |
| #7941 | validation experte juridique par marché | experts légaux | — |
| #7924 | conformité légale multi-marchés (épic, draft) | juridique | — |
| #8727-#8730 | migrations Pages (drafts, dépendantes) | séquencement produit | — |
| #8448 | KDS restaurant offline-first (épic) | arbitrage produit | — |
| #8210 | webhooks sortants unifiés (épic) | arbitrage produit | — |

**Décision.** Ne **jamais** fermer une issue bloquée par une action propriétaire en la déclarant
« implémentée ». Documenter le blocage, dire qui doit agir, et laisser l'issue ouverte — sinon le
tableau de bord du projet ment sur son état réel.

---

## D7 — État des issues déjà livrées sans le savoir (#7967, #7994)

**Contexte.** Deux issues `critical`/`high-priority` semblaient non traitées.

| Issue | Demandé | Réalité sur `main` |
|---|---|---|
| #7967 | queue + scheduler en `plan: free` avec spin-down | `render.prod.yaml` : worker `plan: standard`, `numInstances: 1` |
| #7994 | travel-web job prod en skip permanent, marketplace sans workflow | skip supprimé ; `.github/workflows/marketplace-deploy.yml` existe ; runs verts sur `push: main` |

**Décision.** Le code est livré ; reste la **vérification d'exécution** côté propriétaire (un
déploiement Vercel prod réellement déclenché, un job traité par le worker standard). Commentaire de
clôture avec preuve fichier + run, **sans** fermer tant que l'exécution prod n'est pas constatée —
c'est exactement la distinction que D6 impose.

---

## Récapitulatif des décisions

1. **Une implémentation par défaut** ; le correctif qui sert l'issue mère gagne (D1).
2. **Un faux rouge se corrige dans la garde** (D2).
3. **Le généré n'est jamais la source** (D3).
4. **Pas d'extraction sans test** ; le type garanti est celui de sortie (D4).
5. **Un point de composition, pas six** (D5).
6. **Une issue bloquée par une action propriétaire reste ouverte** (D6).
7. **« Livré dans le code » ≠ « livré en prod »** (D7).
