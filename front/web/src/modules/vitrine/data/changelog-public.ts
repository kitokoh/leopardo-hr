/**
 * Extrait public du CHANGELOG produit (non automatise).
 * A synchroniser ponctuellement avec CHANGELOG.md a la racine du depot.
 */
export type PublicChangelogRelease = {
  version: string
  isoDate: string
  title: string
  bullets: string[]
}

export const publicChangelogReleases: PublicChangelogRelease[] = [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: 'Première release publique — sécurité, CI et qualité',
    bullets: [
      'Durcissement sécurité complet : SSO SAML/OIDC chiffré au repos, uploads contraints, auth web cookie httpOnly, XSS kiosk éliminé, mobile JWT hors Hive.',
      'Couverture backend 71 % mesurée en CI (gate par module, Payroll ≥ 80 % cible) ; 1 917 tests, 424 endpoints API documentés.',
      'Pagination et contrats de réponse normalisés (Growth, Caméras, DeviceToken, CabinetShare).',
      'CI : 69 actions pinnées SHA, dependency-review bloquant, gate anti-stale SHA, scan secret A-2 réellement exécuté.',
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: 'Correctifs production — cold start, E2E et paie',
    bullets: [
      'Warm-up anti cold-start Render pour l\u2019E2E staging (timeout Playwright 15 s).',
      'Robustesse Vercel : ignoreCommand avec fallback SHA précédent.',
      'Correctifs paie : numéro de facture réellement persisté, workers/scheduler Render démarrés, index FK salary_advances.',
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "Correction de compilation Dart (employee, manager, HR)",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "Résolution de 34 alertes Dependabot",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "Sécurité CI : correctif CodeQL sur deploy-main.yml",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "Module Marketing — schéma et modèles de base",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "Rôle manager marketing invalidable via l'API",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "Drip email onboarding (3 emails de nurturing)",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "Correctif CI : erreurs PHP dans les règles paie pays",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "Sécurité : token SSE retiré des query parameters admin",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "Isolation multi-tenant des jobs en file d'attente",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "Suppression de code mort (PaymentWebhookController)",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "Correctifs CI et tests backend",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "Correctif CI : tests backend réalignés",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "Nettoyage de la documentation projet",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural Phase 2 (modèles, services, FormRequests)",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "Correctif CI bloquant les merges",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural API — suppression des doublons legacy",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync : migration EdgeController vers le module dédié",
    bullets: [
      'Voir le CHANGELOG complet du dépôt pour le détail de cette release.',
    ],
  },
]
