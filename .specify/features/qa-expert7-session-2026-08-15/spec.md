# Feature Specification: Session QA Expert 7 2026-08-15 (live + audit main)

**Feature Branch**: `docs/qa-expert7-session-2026-08-15` + branches par issue (`fix/<issue>-*`)

**Created**: 2026-08-15 | **Status**: Draft → Implémentation en cours

**Input**: Mission du propriétaire (session 2026-08-15) — en tant qu'expert, implémenter le max
des tâches ouvertes, tester l'app dans tous les sens (vitrine, web, admin, mobiles, workflows,
APIs, logiques, onboarding, cohérence) ; tout manquement → spec/tasks/issues selon la méthode
Spec Kit ; implémenter les manquements en fin de test ; implémenter le max d'issues ouvertes ;
merger le max de branches ; `main` doit rester VERT (plusieurs agents en parallèle).

## User Stories & Testing

### User Story 1 — La vitrine est de nouveau joignable (P1)

Un visiteur peut atteindre la vitrine à son domaine canonique `leopardo-rh.com` (DNS résolu,
HTTP 200, sitemap/robots cohérents).

**Pourquoi P1** : constat live E7-01 — `leopardo-rh.com` est NXDOMAIN ; la totalité du funnel
d'acquisition est inaccessible. Rien dans le code ne peut corriger le DNS, mais la détection
doit être tracée (issue ops) et le code doit cesser de pointer des URLs mortes (#3251/#3190).

**Acceptance Scenarios**:
1. **Given** le domaine canonique, **When** `dns.google/resolve?name=leopardo-rh.com&type=A`,
   **Then** un enregistrement existe (NXDOMAIN résolu) — action ops propriétaire.
2. **Given** le code vitrine, **When** audit, **Then** aucune URL morte `leopardo-rh.com`
   non-résolue n'est référencée dans sitemap/canonicals/robots.

### User Story 2 — Les correctifs les plus petits et sûrs des issues ouvertes sont livrés (P1/P2)

Les issues P1/P2 sans branche de lock (#2400) et à périmètre net sont implémentées, testées
(CI verte) et mergées sans casser main.

**Pourquoi P1** : backlog de 157 issues sans branche ; l'agent doit livrer le max de valeur
sans entrer en collision avec les autres agents (lock par branche, `Closes #N` dans le body).

**Acceptance Scenarios**:
1. **Given** une issue sans branche, **When** implémentation, **Then** branche `fix/<issue>-<slug>`
   poussée immédiatement (claim), PR avec `Closes #<issue>` dans le body + entrée CHANGELOG.
2. **Given** la PR, **When** checks CI, **Then** PHPStan strict 0 erreur, lint front 0, tests verts.

### User Story 3 — Les manquements trouvés en test deviennent des constats tracés (P2)

Chaque manquement rencontré pendant les tests est consigné dans le registre (E7-*) et traduit
en issue GitHub format `[QA][P#][surface]` (méthode Spec Kit), sans doublon des vagues
précédentes.

**Acceptance Scenarios**:
1. **Given** un constat vérifié, **When** rédaction, **Then** issue avec preuve (fichier:ligne)
   et référence aux issues existantes couvrant le même sujet.
2. **Given** le registre, **When** fin de session, **Then** chaque constat a un statut
   (issue ouverte / corrigé / référencé).
