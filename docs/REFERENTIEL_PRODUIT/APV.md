# APV — Architecture Produit Vivante (manifeste 1 page)

> Source de vérité architecturale de Leopardo RH. Valide avril 2026.
> Consolidation de `docs/vision/Leopardo_RH_APV_v2.pdf` + `docs/vision/Leopardo_RH_Design_System_v3.pdf`.
> Toute décision de développement qui contredit ce document doit d'abord modifier ce document, jamais l'inverse.

## Le pitch en une phrase

> L'app s'ouvre sur une conversation, se déploie module par module, et apprend à mesure qu'on l'utilise — en partant toujours du téléphone.

## Les 4 piliers

1. **Conversationnelle** — La Home est un chat (Leo IA + chips + alertes), pas un dashboard de KPIs.
2. **Mobile-first** — Toute spec commence par l'écran mobile. Le web est un back-office pour managers, jamais la surface principale.
3. **Modulaire plugin** — Chaque module (RH, Finance, Caméras, Muhasebe, etc.) est un plugin activable par entreprise via `companies.features`. Le core n'importe aucun module.
4. **Vivante** — L'app enseigne en fonctionnant, révèle progressivement ses features, évolue sans casser (API versionnée, JSONB `metadata`, feature flags).

## Les 5 questions à poser à chaque PR

1. Est-ce dans le bon module ? (le core ne contient que auth, navigation, Leo)
2. Est-ce spec'é en mobile d'abord ? (un écran web sans équivalent mobile n'est légitime que pour un outil back-office managers)
3. Est-ce découvrable sans formation ? (Leo doit pouvoir pointer vers une aide)
4. Est-ce évolutif sans casser ? (contrat API versionné + JSONB extensible + feature flag si risqué)
5. Est-ce que ça respecte les 12 Lois ?

## Les 12 Lois

| N° | Loi | Rappel |
|---|---|---|
| L.01 | Home = Conversation | Pas de dashboard KPIs comme premier écran. Chat + chips + alertes. |
| L.02 | Mobile = source de vérité | Le web adapte les décisions mobile, jamais l'inverse. L'employé n'utilise que le mobile. |
| L.03 | Core n'importe aucun module | `leopardo_core` n'a jamais de dépendance sur `module_finance`, `module_cameras`, etc. |
| L.04 | Modules ne se connaissent pas | Finance ne peut pas importer Camera. Communication via événements du core. |
| L.05 | Couleur = domaine | Vert = RH, Ambre = Finance, Bleu = Sécurité, Violet = IA. Associations immuables. |
| L.06 | Leo enseigne, l'interface confirme | Leo donne le contexte, l'écran fait l'action. Pas de tutoriels modaux. |
| L.07 | Grille partagée = source de vérité | `AppColors.dart` + `tailwind.config.js` + `docs/COULEURS.md` bougent ensemble dans la même PR. |
| L.08 | Un module = un package | Chaque module est un package Flutter + un route group Laravel. Jamais d'import direct entre modules. |
| L.09 | Contrat module immutable par release | Une breaking change → major version du contrat. Modules existants ont 6 mois pour migrer. |
| L.10 | Nouvelles données → JSONB d'abord | Les colonnes dédiées arrivent quand la donnée est stabilisée (après Phase 2). |
| L.11 | Progressive disclosure avant feature toggle | Avant de cacher une feature, vérifier si elle peut être révélée progressivement selon le stade utilisateur. |
| L.12 | Leo est spécialisé, pas généraliste | Leo ne parle que du produit Leopardo RH. Toute question hors-domaine est redirigée. |

## Décisions validées (avril 2026)

- **Mobile-first strict** : l'employé n'a aucun accès web. `role=employee` bloqué en login web. Le web `/me` sera supprimé au profit d'une redirection "télécharge l'app mobile".
- **Modules activables à la demande** : chaque company peut avoir ses features activées indépendamment. Exemple : une company active `cameras`, une autre active `muhasebe`, chacune paie uniquement ce qu'elle utilise.
- **Modules Phase 2 non implémentés maintenant** : Finance et Caméras ont leurs spécifications archivées dans `docs/vision/` mais ne sont pas développés. Priorité = stabiliser le MVP RH + pointage + déployer 3 pilotes clients.
- **Leo IA scaffolé vide** : le composant `ChatInputBar` + `QuickActionsRow` + `AlertBanners` est construit maintenant, l'intégration API externe (OpenAI / Claude) est Phase 2 derrière un feature flag `leo_ai_enabled`.

## Stades d'apprentissage (simplifié pour MVP)

| Stade | Condition | Ce qui est visible |
|---|---|---|
| `new` | `app_actions_count < 10` | Home simplifiée, chips limités à 3, messages Leo guidants |
| `regular` | `app_actions_count >= 10` | Interface complète du plan souscrit |

> Les 5 paliers `discovery → ambassador` du document v1 sont reportés Phase 2 après collecte de métriques réelles d'usage.

## Ce qui est sur le papier mais NON actif maintenant

| Feature | Activation | Déclencheur |
|---|---|---|
| Dart deferred imports / lazy loading | Phase 2 | ≥ 5 modules actifs ou APK > 100MB |
| Webpack Module Federation (web) | Phase 2 | Équipe externe développe un module |
| Shorebird code push | Phase 2 | Premier bug critique en production qui nécessite un hotfix hors stores |
| Leo API externe | Phase 2 | Après validation MVP et budget IA confirmé |
| 5 paliers `discovery → ambassador` | Phase 2 | Après métriques d'usage réelles |

## Gouvernance

Ce document est modifiable. Une modification d'une Loi nécessite (1) une justification écrite dans le commit, (2) une PR dédiée, (3) une mise à jour du `CHANGELOG.md`. La date et l'auteur sont trackés dans `git blame`.

Version 1.0 — Avril 2026.
