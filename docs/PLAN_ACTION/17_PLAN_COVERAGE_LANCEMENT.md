# 17 — Plan d'execution coverage et lancement

**Date :** 2026-05-20  
**Positionnement :** dernier lot technique avant acceleration marketing publique.

## Objectif

Amener le socle API au niveau de confiance necessaire pour absorber les premiers volumes marketing sans casser les parcours critiques. Le plan 17 ne reconstruit pas l'existant : il augmente la fiabilite mesurable, ferme les angles morts restants et prepare la prochaine hausse de seuil coverage.

## Lot 17.1 — Coverage backend 60%

- [ ] Ajouter des tests cibles sur les services et controllers les plus critiques non couverts.
- [ ] Priorite : paie, absences, attendance, notifications, webhooks, billing, onboarding.
- [ ] Mesurer la coverage statement apres chaque PR et ne monter `DEFAULT_BACKEND_COVERAGE_MIN` a `60` qu'apres un run CI vert >= 60%.
- [ ] Publier la mesure dans `CHANGELOG.md` et `AGENTS.md`.

## Lot 17.2 — Contrats API frontends

- [ ] Ajouter des tests de contrat JSON pour les endpoints consommes par admin-dashboard, vitrine, mobile et kiosque.
- [ ] Verifier les erreurs standardisees `message`, `errors`, `code`, `request_id`.
- [ ] Verifier pagination, filtres, tri et payloads vides sur les listes RH critiques.

## Lot 17.3 — Vitrine multilingue conversion

- [ ] Finaliser `/pricing`, `/demo`, `/integrations`, `/blog` en FR/EN/AR/TR.
- [ ] Ajouter schema.org, sitemap, robots et metadata par locale.
- [ ] Brancher les formulaires demo/newsletter sur une API ou un endpoint server-side observable.

## Lot 17.4 — Mobile et kiosque readiness

- [ ] Ajouter les parcours mobile prioritaires : conges, bulletins, notifications push.
- [ ] Ajouter les contrats kiosk : post-pointage, QR code, affichage infos employe.
- [ ] Documenter les endpoints obligatoires par frontend.

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
