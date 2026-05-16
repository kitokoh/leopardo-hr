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
    version: '4.16.59',
    isoDate: '2026-05-16',
    title: 'API et admin — liste des bulletins par tenant',
    bullets: [
      'Nouveau endpoint `GET /api/v1/pay-slips` (pagination manager, filtres run et statut).',
      "Vue paie admin : chargement des bulletins sans enchaîner un appel par run (réduction N+1).",
      'Documentation OpenAPI et tests Feature associés.',
    ],
  },
  {
    version: '4.16.58',
    isoDate: '2026-05-16',
    title: 'Admin-dashboard — paie et congés',
    bullets: [
      'Paie : runs paginés, PDF bulletins via session authentifiée, exports CSV navigateur.',
      'Congés : soldes et politiques réels, approbation / refus via API, pagination absences.',
      'Liste absences enrichie pour les managers (`employee_name`, `type`).',
    ],
  },
  {
    version: '4.16.57',
    isoDate: '2026-05-16',
    title: 'Performance paie — cache rapports et PDF asynchrones',
    bullets: [
      'Cache tenant pour `GET /api/v1/reports/headcount` (TTL configurable).',
      'Après validation d’un run : job de pré-génération des PDF bulletins (warmup).',
      'Distribution des PDF depuis fichier stocké lorsque disponible.',
    ],
  },
  {
    version: '4.16.56',
    isoDate: '2026-05-15',
    title: 'API — versionnement et quotas par plan',
    bullets: [
      'Middleware de version API (`X-API-Version`) et versions supportées exposées.',
      'Limiter `api-plan` : quotas par offre commerciale après authentification tenant.',
    ],
  },
  {
    version: '4.16.55',
    isoDate: '2026-05-14',
    title: 'Monitoring — Sentry, Slack et requêtes lentes',
    bullets: [
      'Enrichissement du contexte Sentry par tenant / utilisateur.',
      'Notifications Slack pour événements critiques.',
      'Commande planifiée de détection des requêtes PostgreSQL lentes.',
    ],
  },
]
