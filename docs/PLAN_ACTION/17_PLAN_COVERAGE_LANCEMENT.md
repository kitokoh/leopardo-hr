# 17 — Plan d'execution coverage et lancement

**Date :** 2026-05-20  
**Positionnement :** dernier lot technique avant acceleration marketing publique.

## Objectif

Amener le socle API au niveau de confiance necessaire pour absorber les premiers volumes marketing sans casser les parcours critiques. Le plan 17 ne reconstruit pas l'existant : il augmente la fiabilite mesurable, ferme les angles morts restants et prepare la prochaine hausse de seuil coverage.

## Lot 17.1 — Coverage backend 60%

- [x] Ajouter un premier lot de tests cibles sur des services critiques peu couverts : `SocialDeclarationGenerator` et `BankExportGenerator`.
- [x] Ajouter un deuxieme lot de tests cibles sur l'integration flotte/GPS : `TraccarService` sans serveur externe.
- [x] Ajouter un troisieme lot de tests cibles sur l'integration calendrier : `CalendarSyncService` Google/Outlook/CalDAV sans appels externes.
- [x] Ajouter un quatrieme lot de tests API paie : declarations sociales CNAS DZ, CNSS MA et DSN FR avec RBAC, isolation tenant et champs reglementaires.
- [ ] Priorite : paie, absences, attendance, notifications, webhooks, billing, onboarding.
- [x] Mesurer la coverage statement apres ce PR : 57,51% (`9341/16242`) sur PR #512, seuil ratchete a `DEFAULT_BACKEND_COVERAGE_MIN=57`.
- [x] Mesurer la coverage statement apres PR #513 : 57,86% (`9397/16242`), seuil conserve a 57 avant prochain ratchet.
- [x] Mesurer la coverage statement apres PR #514 : 58,76% (`9543/16242`), seuil ratchete a `DEFAULT_BACKEND_COVERAGE_MIN=58`.
- [x] Mesurer la coverage statement apres PR #515 : 60,01% (`9748/16243`), seuil ratchete a `DEFAULT_BACKEND_COVERAGE_MIN=60`.
- [x] Publier la mesure dans `CHANGELOG.md` et `AGENTS.md`.
- [x] Continuer les lots cibles jusqu'a un run CI vert >= 60%, puis monter `DEFAULT_BACKEND_COVERAGE_MIN` a `60`.

## Lot 17.2 — Contrats API frontends

- [x] Ajouter des tests de contrat JSON pour les endpoints consommes par admin-dashboard, mobile et kiosque.
- [x] Verifier les erreurs standardisees `error`, `message`, `localized_message` et `errors`.
- [x] Verifier pagination, filtres, tri et payloads vides sur les listes RH critiques : `employees`, `absences`, `attendance`, `me/pay-slips`, `notifications`.

## Lot 17.3 — Vitrine multilingue conversion

- [x] Finaliser `/pricing`, `/demo` et `/integrations` en FR/EN/AR/TR avec support RTL arabe.
- [x] Auditer et finaliser `/blog` en FR/EN/AR/TR.
- [x] Ajouter alternates sitemap/hreflang et metadata canonical compatibles avec le rail `?lang=`.
- [x] Auditer schema.org par locale sur les contenus marketing dynamiques : JSON-LD article avec URL/image absolues et `inLanguage`.
- [x] Conserver le formulaire demo sur endpoint server-side `/api/forms/demo` et transmettre la locale courante.
- [x] Conserver le formulaire newsletter sur endpoint server-side `/api/forms/newsletter` et transmettre la locale courante.
- [x] Ajouter observabilite metier explicite sur les leads demo/newsletter/signup/contact : identifiant lead, locale, source, UTM, logs structures et webhooks CRM/email optionnels.

## Lot 17.4 — Mobile et kiosque readiness

- [x] Ajouter les contrats d'existence routes pour les parcours mobile prioritaires : conges, bulletins, notifications push et pointage.
- [x] Ajouter les contrats kiosk : post-pointage, QR code, sync offline, affichage infos employe et soldes conges.
- [x] Documenter les endpoints obligatoires par frontend dans `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`.
- [x] Ajouter des tests JSON payload mobile detailles pour conges, bulletins et notifications.

## Lot 17.5 — Observabilite lancement

- [ ] Creer un tableau de bord lancement : health API, erreurs 5xx, temps p95, queue depth, jobs failed, leads demo.
- [ ] Ajouter alerting externe pour `/api/v1/health`, `/docs`, vitrine et admin.
- [ ] Formaliser le rollback marketing : comment stopper acquisition, queue, emails, webhooks et deploy.

## Definition of done

- CI verte sur backend, coverage, jobs, admin, vitrine, OpenAPI, security.
- Coverage backend >= 60% avant ratchet du seuil.
- Aucun endpoint frontend critique sans test de contrat.
- Les plans d'action historiques distinguent clairement code livre, operations externes et backlog strategique.
- Le release readiness report est mis a jour avant annonce marketing.
