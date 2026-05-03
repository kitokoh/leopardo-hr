export type PricingPlan = {
  name: string;
  price: string;
  period: string;
  description: string;
  features: string[];
  cta: string;
  popular: boolean;
  gradient: string;
};

export const pricingPlans: PricingPlan[] = [
  {
    name: 'Starter',
    price: '29',
    period: '/mois',
    description: 'Ideal pour les petites equipes',
    features: [
      'Jusqu\'a 10 employes',
      'Pointage de base',
      'Gestion des absences',
      'Support email',
      'Export PDF',
    ],
    cta: 'Commencer gratuitement',
    popular: false,
    gradient: 'from-slate-600 to-slate-700',
  },
  {
    name: 'Business',
    price: '79',
    period: '/mois',
    description: 'Pour les entreprises en croissance',
    features: [
      'Jusqu\'a 100 employes',
      'Paie automatisee',
      'Leo IA Assistant',
      'API & Webhooks',
      'Rapports avances',
      'Support prioritaire 24/7',
    ],
    cta: 'Essai gratuit 14 jours',
    popular: true,
    gradient: 'from-emerald-500 to-cyan-500',
  },
  {
    name: 'Enterprise',
    price: 'Sur devis',
    period: '',
    description: 'Solution sur mesure illimitee',
    features: [
      'Employes illimites',
      'Deploiement on-premise',
      'SSO / SAML',
      'SLA garanti 99.99%',
      'Gestionnaire dedie',
      'Formation personnalisee',
    ],
    cta: 'Contacter les ventes',
    popular: false,
    gradient: 'from-violet-600 to-purple-700',
  },
];
