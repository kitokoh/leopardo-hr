# ADR — Exposition MCP externe d'un BC pilote (assistant BC-23)

- **Statut :** décision — spike #6861 (issue D1, EPIC #6846) ; **pas d'implémentation en v1**
- **Date :** 2026-09-06
- **Périmètre :** API Laravel — `app/AI` (BC-23 AI), contrat interne `AIToolDefinition` (#6850)
- **ADR parent :** `docs/specifications/SOLUTION_COMMAND_ASSISTANT_MCP.md` (§12, phase 2)
- **Issue de référence :** #6861 — « spike — exposition d'un BC pilote en serveur MCP standard (SDK PHP) pour clients externes »

---

## Contexte

Le contrat d'outil interne de l'assistant (façon MCP : déclarations JSON Schema
par BC, découverte par profil — issue #6850) est **interne au monolithe**. La
phase 2 de la spec envisage d'exposer un BC pilote en **serveur MCP standard**
(Model Context Protocol) pour des **clients externes** (clients MCP existants :
IDE, assistants tiers, intégrations).

Le spike #6861 demandait une **décision écrite**, pas de code de production.

## Options considérées

1. **Serveur MCP externe en v1** (SDK PHP officiel `modelcontextprotocol/php-sdk`,
   transport HTTP/SSE ou stdio) exposant les outils d'un BC pilote.
2. **Contrat interne seulement en v1** — les outils restent consommés par le
   pipeline LLM de BC-23 (A3/A4/C1) ; l'exposition externe MCP est différée.
3. **Pont hybride** : adapter MCP externe branché sur la même découverte interne
   (un BC expose = un « provider » MCP de plus).

## Décision

**Option 2 pour v1 — pas de serveur MCP externe.** Maintien du contrat interne
(A3) comme seul point de déclaration des outils. Réévaluer (option 3, pont
hybride sur la découverte interne) quand **au moins un client externe réel**
exprimera un besoin, avec un BC pilote à fort retour (candidats : BC-05
Planning — outils absence/planning ; BC-24 Travel).

### Pourquoi

- **Aucun besoin externe avéré** : les consommateurs v1 sont les managers/RH
  dans l'app (US1–US7 de la spec) — l'hôte LLM reste interne.
- **Coût de surface** : un serveur MCP externe ajoute un contrat de plus à
  maintenir (transport, auth, versioning, découvrabilité) alors que la
  découverte interne (A3) n'est pas encore stabilisée.
- **Sécurité** : exposer des outils « write/send » à des clients externes
  élargit la surface de vérification (ToolExecutionGuard A4, P0) sans gain v1.
- **Le contrat interne est le bon investissement** : dessiné « façon MCP »
  (JSON Schema 2020-12, `permission`, `sensitivity`), il rendra le pont
  hybride (option 3) peu coûteux plus tard — pas de dette structurelle.

### Conséquences

- Hors périmètre v1 : SDK MCP, routes/transports externes, auth machine-to-machine.
- La découverte interne (A3) doit rester **strictement alignée sur le standard
  MCP** (nommage snake_case, JSON Schema) pour préserver l'option 3.
- Revoir cette ADR si : un client externe est demandé, ou si un BC déclare un
  besoin d'outils consommés hors du pipeline BC-23.
- Aucun changement de code applicatif ; décision tracée dans #6861.
