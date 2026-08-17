# Current priorities

> **Mise à jour : 2026-08-17 (revue PM).** Ce fichier reflète l'état réel du backlog
> GitHub au 17/08/2026. Le triage détaillé et évolutif vit dans
> `docs/qa/TRIAGE_2026-08-17.md` et dans les issues GitHub (source de vérité).
> Avant toute action, lire `AGENTS.md` (Spec Kit, protocole anti-doublon #2400) puis `CHANGELOG.md`.

## P0 — Bloqueurs (majoritairement accès humain / hors code)

- **Prod** : démo super-admin KO (`#2646` — seed + `/api/v1/demo-users`), vitrine `leopardo-rh.com` NXDOMAIN (`#3452`), stabilisation prod services gratuits (`#3765`/`#3766`), check Vercel qui bloque le CI (`#4868`).
- **Gouvernance** : appliquer la règle de clôture anti-ghost-close (`#4859`) — toute clôture d'issue exige une preuve (PR mergée ou commit).

## P1 — Code implémentable (prochains agents)

- Quick win : `#4865` (errorBuilder GoRouter localisé ×3 apps).
- `#3245` — dédoublonnage logique self-service (MeController/Estimation/Attendance/HrController).
- `#4842`/`#3885` — OpenAPI drift (routes non documentées ; cluster edge-node/edge).
- `#4862`/`#4881` — résiduel i18n FR admin (~39 chaînes, 10 vues).

## P2 — Chantiers de dette (à découper en issues-filles avant implémentation)

- **i18n mobile** : `#2755` (8 983 chaînes), `#4194` (~1 650 chaînes), `#4843`, `#4409` (cluster mort), `#4194 → #4303` (résiduel, PR #4860).
- **Portail client localisé** : `#4574` (lots — PRs #4857/#4872 en cours).
- **Apps incomplètes** : `#3910` (marketing), `#3912` (platform_admin) — spec-kit obligatoire.
- **Dé-duplication `leopardo_hr`/`leopardo_manager`** : `#2601`.

## P3 — Vision (non prioritaire)

- Marketplace open-core · White-label cabinets · Programme revendeurs · IA RH gouvernée.

## Rappels opérationnels

- Le repo est en activité concurrente (swarm) : clamer une issue avec une branche `fix/<issue>-<slug>` avant d'implémenter (protocole #2400), rebaser avant de pousser, ne jamais force-push sur une branche partagée.
- CI saturée historiquement : annuler les runs orphelins (#2413) avant de pousser ; vérifier l'état remote AVANT de pousser.
