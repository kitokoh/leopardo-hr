/**
 * Constants for the vitrine module
 */

// Navigation items
export const navigationItems = [
  { label: "Fonctionnalités", href: "#features" },
  { label: "Tarifs", href: "/pricing" },
  { label: "Témoignages", href: "#testimonials" },
  { label: "FAQ", href: "#faq" },
  { label: "Blog", href: "/blog" },
];

// Social links
export const socialLinks = [
  { name: "Twitter", url: "https://twitter.com/leopardo", icon: "twitter" },
  { name: "LinkedIn", url: "https://linkedin.com/company/leopardo", icon: "linkedin" },
  { name: "Facebook", url: "https://facebook.com/leopardo", icon: "facebook" },
  { name: "Instagram", url: "https://instagram.com/leopardo", icon: "instagram" },
];

// Footer links
export const footerLinks = {
  product: [
    { label: "Fonctionnalités", href: "#features" },
    { label: "Tarifs", href: "/pricing" },
    { label: "Sécurité", href: "/security" },
    { label: "Roadmap", href: "/roadmap" },
  ],
  company: [
    { label: "À propos", href: "/about" },
    { label: "Blog", href: "/blog" },
    { label: "Carrières", href: "/careers" },
    { label: "Contact", href: "/contact" },
  ],
  resources: [
    { label: "Documentation", href: "/docs" },
    { label: "Guides", href: "/guides" },
    { label: "Webinaires", href: "/webinars" },
    { label: "Support", href: "/support" },
  ],
  legal: [
    { label: "Politique de confidentialité", href: "/privacy" },
    { label: "Conditions d'utilisation", href: "/terms" },
    { label: "Cookies", href: "/cookies" },
    { label: "Mentions légales", href: "/legal" },
  ],
};

// Pricing plans
export const pricingPlans = [
  {
    name: "Starter",
    price: 29,
    currency: "EUR",
    period: "mois",
    description: "Pour les petites équipes",
    features: [
      "Jusqu'à 10 employés",
      "Pointage numérique",
      "Gestion des absences",
      "Paie automatisée",
      "Support email",
    ],
    cta: {
      text: "Essai gratuit",
      href: "/signup?plan=starter",
    },
  },
  {
    name: "Business",
    price: 79,
    currency: "EUR",
    period: "mois",
    description: "Pour les PME en croissance",
    features: [
      "Jusqu'à 100 employés",
      "Toutes les fonctionnalités Starter",
      "Cabinet numérique",
      "Marketing digital",
      "Support prioritaire",
      "Intégrations avancées",
    ],
    cta: {
      text: "Essai gratuit",
      href: "/signup?plan=business",
    },
    highlighted: true,
    badge: "POPULAIRE",
  },
  {
    name: "Enterprise",
    price: null,
    currency: "EUR",
    period: "mois",
    description: "Pour les grandes entreprises",
    features: [
      "Employés illimités",
      "Toutes les fonctionnalités",
      "Déploiement personnalisé",
      "Support 24/7",
      "SLA garanti",
      "Formations incluses",
    ],
    cta: {
      text: "Contacter les ventes",
      href: "/contact?type=enterprise",
    },
  },
];

// Employee ranges
export const employeeRanges = [
  { value: "1-10", label: "1-10 employés" },
  { value: "11-50", label: "11-50 employés" },
  { value: "51-200", label: "51-200 employés" },
  { value: "201-500", label: "201-500 employés" },
  { value: "500+", label: "500+ employés" },
];

// Blog categories
export const blogCategories = [
  { id: "rh", label: "RH", color: "emerald" },
  { id: "productivite", label: "Productivité", color: "cyan" },
  { id: "tendances", label: "Tendances", color: "blue" },
  { id: "guides", label: "Guides", color: "purple" },
];

// FAQ items
export const faqItems = [
  {
    question: "Puis-je changer de plan?",
    answer:
      "Oui, vous pouvez changer de plan à tout moment. Les changements prendront effet au prochain cycle de facturation.",
  },
  {
    question: "Essai gratuit inclus?",
    answer:
      "Oui, tous les plans incluent un essai gratuit de 14 jours sans carte bancaire requise.",
  },
  {
    question: "Contrat long terme?",
    answer:
      "Nous proposons des contrats mensuels ou annuels. Les contrats annuels bénéficient d'une réduction de 20%.",
  },
  {
    question: "Support client disponible?",
    answer:
      "Oui, nous offrons un support email pour tous les plans et un support prioritaire pour les plans Business et Enterprise.",
  },
  {
    question: "Données sécurisées?",
    answer:
      "Oui, toutes les données sont chiffrées avec AES-256 et stockées sur des serveurs sécurisés conformes à la RGPD.",
  },
];

// Testimonials
export const testimonials = [
  {
    quote: "Leopardo a transformé notre gestion RH. Nous avons économisé 10h par semaine!",
    author: "Marie Dupont",
    role: "Manager RH",
    company: "TechStartup Inc",
    avatar: "/avatars/marie.jpg",
    rating: 5,
  },
  {
    quote: "La meilleure solution RH que nous ayons jamais utilisée. Zéro erreur depuis 2 ans.",
    author: "Jean Martin",
    role: "Comptable",
    company: "Finance Pro",
    avatar: "/avatars/jean.jpg",
    rating: 5,
  },
  {
    quote: "Support excellent et interface intuitive. Nos employés adorent!",
    author: "Sophie Bernard",
    role: "Directrice",
    company: "Retail Solutions",
    avatar: "/avatars/sophie.jpg",
    rating: 5,
  },
];

// Case studies
export const caseStudies = [
  {
    title: "Startup Tech: De 5 à 50 employés",
    description: "Comment une startup a géré sa croissance avec Leopardo",
    industry: "Technologie",
    metrics: [
      { label: "Temps économisé", value: "15h/semaine" },
      { label: "Erreurs de paie", value: "0" },
      { label: "Satisfaction", value: "98%" },
    ],
    image: "/case-studies/startup.jpg",
    link: "/case-studies/startup",
  },
  {
    title: "Retail: 50 points de vente",
    description: "Centralisation du pointage pour une chaîne de magasins",
    industry: "Retail",
    metrics: [
      { label: "Points de vente", value: "50" },
      { label: "Employés gérés", value: "500+" },
      { label: "Réduction coûts", value: "30%" },
    ],
    image: "/case-studies/retail.jpg",
    link: "/case-studies/retail",
  },
  {
    title: "Usine: Biométrie avancée",
    description: "Pointage biométrique pour une usine de 200 employés",
    industry: "Industrie",
    metrics: [
      { label: "Employés", value: "200" },
      { label: "Précision", value: "99.9%" },
      { label: "Fraude réduite", value: "95%" },
    ],
    image: "/case-studies/factory.jpg",
    link: "/case-studies/factory",
  },
];

// Features
export const features = [
  {
    icon: "users",
    title: "Gestion RH Complète",
    description: "Gérez vos employés, absences et schedules en un seul endroit",
  },
  {
    icon: "credit-card",
    title: "Paie Automatisée",
    description: "Calculs exacts et bulletins générés automatiquement",
  },
  {
    icon: "lock",
    title: "Sécurité Maximale",
    description: "Chiffrement AES-256 et conformité RGPD garantie",
  },
  {
    icon: "zap",
    title: "Performance",
    description: "Chargement rapide et interface intuitive",
  },
];

// Animation delays
export const animationDelays = {
  xs: 0.05,
  sm: 0.1,
  md: 0.15,
  lg: 0.2,
  xl: 0.3,
};

// Breakpoints
export const breakpoints = {
  xs: 320,
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
  "2xl": 1536,
};

// Colors
export const colors = {
  primary: "#10b981", // Emerald 500
  secondary: "#06b6d4", // Cyan 500
  success: "#10b981",
  warning: "#f59e0b",
  error: "#ef4444",
  info: "#3b82f6",
};

// Contact information
export const contactInfo = {
  email: "support@leopardo.com",
  phone: "+33 1 23 45 67 89",
  address: "123 Rue de la Paix, 75000 Paris, France",
  hours: "Lun-Ven: 9h-18h (CET)",
};

// API endpoints
export const apiEndpoints = {
  signup: "/api/forms/signup",
  demo: "/api/forms/demo",
  contact: "/api/forms/contact",
  newsletter: "/api/forms/newsletter",
  analytics: "/api/analytics/track",
};

// Error messages
export const errorMessages = {
  generic: "Une erreur est survenue. Veuillez réessayer.",
  network: "Erreur de connexion. Vérifiez votre connexion internet.",
  validation: "Veuillez vérifier vos données.",
  unauthorized: "Vous n'êtes pas autorisé à accéder à cette ressource.",
  notFound: "La ressource demandée n'a pas été trouvée.",
  serverError: "Erreur serveur. Veuillez réessayer plus tard.",
};

// Success messages
export const successMessages = {
  signupSuccess: "Inscription réussie! Vérifiez votre email.",
  demoSuccess: "Demande de démo envoyée! Nous vous contacterons bientôt.",
  contactSuccess: "Message envoyé! Nous vous répondrons bientôt.",
  newsletterSuccess: "Inscription à la newsletter réussie!",
};
