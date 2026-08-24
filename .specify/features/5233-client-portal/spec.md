# Feature Specification: Portail client web — espace document sécurisé (Closes #5233)

**Feature Branch**: `mod/accounting/5233-client-portal`
**Created**: 2026-08-24 | **Status**: In progress
**Issue**: #5233 (P2, web, Comptabilité Phase B)
**Spec**: `.specify/features/5233-client-portal/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **portail client web**. Backend des partages livré par #5225 (PR #5357, `mod/accounting/5225-doc-email`) — cette spec est **stackée dessus** : le contrat des endpoints publics est celui de #5225, aucun fichier backend du partage n'est dupliqué ici.

## Contexte

Le client (contact de facturation) reçoit un email avec un lien sécurisé (`DocumentShareService::portalUrl()` → `{frontend_url}/documents/shared/{token}`) pour consulter/télécharger le document comptable partagé. Le backend (#5225) expose deux endpoints **publics** (le token est la credential, pattern CabinetShare #1817) :

- `GET /api/v1/accounting/documents/shared/{token}` → `{data: {number, type, type_label, status, issue_date, currency, total_ttc, expires_at}}` (404 `DOCUMENT_SHARE_NOT_FOUND` si token inconnu/expiré)
- `GET /api/v1/accounting/documents/shared/{token}/download` → flux PDF (404 `DOCUMENT_PDF_NOT_READY` si PDF absent)

#5233 (cette spec) construit **l'espace web public** du portail : page `/documents/shared/[token]` dans `front/web` (Next.js), i18n ×4 (fr/ar/tr/en), accès strictement limité au document partagé, aucun gate de session (route publique — la sécurité est le token).

Hors périmètre : le paiement en ligne (passerelle — décision fondateur #5272/ADR-0017), l'app mobile (Phase B/C).

## User Stories & Testing

### US-1 — Consultation du document partagé (P2)

En tant que client destinataire d'un lien sécurisé, je veux voir le résumé de mon document (numéro, type, statut, date d'émission, devise, total TTC, expiration) sans me connecter.

**Acceptance Scenarios**:
1. Given un token valide non expiré, When j'ouvre `/documents/shared/{token}`, Then le résumé du document s'affiche (données du endpoint public).
2. Given le token, When le statut est `overdue`, Then le badge l'indique (libellé localisé ×4).
3. Given la locale `ar`, When j'ouvre la page, Then le rendu est RTL et les libellés sont en arabe.
4. Given la locale par défaut (Accept-Language), When j'ouvre la page, Then `type_label` suit la langue du navigateur (le endpoint backend localise déjà).

### US-2 — Téléchargement du PDF (P2)

1. Given un document partagé avec PDF généré, When je clique « Télécharger le PDF », Then le PDF est téléchargé depuis `/download`.
2. Given un partage sans PDF prêt, When je clique, Then message d'erreur explicite (pas de crash).

### US-3 — Liens invalides / expirés (RGPD) (P1)

1. Given un token inconnu ou expiré, When j'ouvre la page, Then un écran « lien invalide ou expiré » s'affiche (aucune fuite de données).
2. Given le lien expiré, Then aucun appel inutile au endpoint de téléchargement n'est proposé.
3. Given une erreur réseau/backend (502/503 cold start), Then retry + message d'erreur réessayable.

### US-4 — i18n ×4 et accessibilité (P2)

1. Given chaque locale (fr/en/tr/ar), Then toutes les chaînes UI sont localisées (aucune chaîne hardcodée — scan CI #2755).
2. Given la page, Then elle est navigable au clavier et les libellés sont accessibles (aria).

## Requirements

### Functional Requirements

- FR-1 : page publique Next.js `front/web/src/app/documents/shared/[token]/page.tsx` — aucune redirection login, aucun cookie requis.
- FR-2 : fetch du endpoint public `info` via le client API existant (`apiFetch` — retry cold-start, header `Accept-Language` depuis la préférence locale).
- FR-3 : rendu : numéro, type (libellé localisé backend), statut (badge localisé ×4), date d'émission, devise + total TTC (formaté selon locale), expiration.
- FR-4 : bouton « Télécharger le PDF » → endpoint `download` (lien direct ou fetch + blob).
- FR-5 : états d'erreur : 404 (token inconnu/expiré) → écran dédié ; erreur réseau → message + bouton réessayer.
- FR-6 : metadata SSR localisée (title/description) via `x-vitrine-lang` (pattern #4004) ; `<html lang/dir>` géré par l'infra existante.
- FR-7 : préfixe `/documents` ajouté à `VITRINE_LANG_PREFIXES` (source unique #3377/#4004) + matcher middleware — route PUBLIQUE (hors `PROTECTED_PREFIXES`, hors robots disallow).
- FR-8 : clés i18n ajoutées dans `shared/i18n/locales/{fr,en,tr,ar}.json` (parité ×4, source unique #3853) puis sync `sync-web.js`.

### Non-Requirements

- Pas de gate de session sur la route (le token EST la credential).
- Pas de paiement en ligne (décision fondateur #5272).
- Pas de modification des endpoints backend #5225.
- Pas de nouveau design system — tokens UI existants (`glass-*`, lucide-react, Tailwind).

## Technical Notes

- Le client API existant (`@/lib/api-client`) gère retry cold-start (502/503) et injecte `Accept-Language` — réutiliser `apiFetch`, pas de fetch brut.
- La locale SSR vient de l'en-tête `x-vitrine-lang` (posé par le middleware pour les préfixes vitrine) — pattern des pages `(landing)`.
- `type_label` est déjà localisé par le backend (locale de la requête) ; les libellés de statut et l'UI sont localisés côté client via `t()`.
- Formatage monétaire : `Intl.NumberFormat` selon locale (fr-FR / en-US / tr-TR / ar-EG), pattern `formatPrice` du checkout (#4791).
- RTL : la racine `layout.tsx` applique `lang/dir` selon la locale SSR (vérifier `applyDocumentLocale`) ; tests `rtl-direction.test.tsx` existants.

## DoD

- [x] Espace client web public : résumé + téléchargement + erreurs (404/expiré/réseau)
- [x] Accès limité au contact : le token seul donne accès, jamais d'identifiant de document exposé dans l'URL (le token opaque est la seule référence)
- [x] Parcours testé : tests Jest (mock API) — succès, 404, téléchargement, i18n ×4, RTL
- [x] i18n ×4 : 0 chaîne hardcodée (scan CI #2755), parité des clés (26 clés `accountingPortal.*` ×4, validate.js OK)
- [x] CHANGELOG + PR `Closes #5233` (stackée sur #5357)
