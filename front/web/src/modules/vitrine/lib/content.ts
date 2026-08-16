/**
 * Module page content for the vitrine
 */

import type { AppLocale } from '@/lib/i18n'

export const modulePageContent = {
  employes: {
    hero: {
      headline: "Gestion RH Simplifiée pour les PME",
      subheadline: "Pointage, absences, schedules et évaluations en un seul endroit",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=employes",
      },
      ctaSecondary: {
        text: "Voir la démo",
        href: "/demo?module=employes",
      },
    },
    problem: {
      title: "Vous gérez vos employés avec Excel et emails?",
      subtitle: "Les défis courants des PME sans solution RH",
      items: [
        {
          title: "Pointage Manuel",
          description: "Erreurs, fraude, et temps perdu à gérer les feuilles de présence",
        },
        {
          title: "Pas de Visibilité",
          description: "Impossible de voir qui est présent, absent ou en congé en temps réel",
        },
        {
          title: "Gestion des Absences Complexe",
          description: "Demandes éparpillées, approbations manuelles, soldes incorrects",
        },
        {
          title: "Schedules Difficiles",
          description: "Planification manuelle, conflits non détectés, communication inefficace",
        },
      ],
    },
    solution: {
      title: "Comment Leopardo Résout Ces Problèmes",
      subtitle: "Une plateforme centralisée pour toute votre gestion RH",
      description: "Leopardo automatise et centralise chaque aspect de la gestion de vos employés, de la paie aux absences.",
      features: [
        {
          title: "Pointage Intelligent",
          description: "NFC, biométrie, QR code ou géolocalisation - Choisissez votre méthode",
        },
        {
          title: "Gestion des Absences",
          description: "Demandes automatisées, approbations multi-niveaux, soldes en temps réel",
        },
        {
          title: "Planification Flexible",
          description: "Calendriers partagés, alertes intelligentes, gestion des conflits",
        },
        {
          title: "Évaluations & Performance",
          description: "Feedback continu, objectifs alignés, suivi de la performance",
        },
      ],
    },
    caseStudies: {
      title: "Cas d'Usage Réels",
      subtitle: "Découvrez comment nos clients utilisent Leopardo",
      items: [
        {
          title: "Startup Tech: De 5 à 50 employés",
          description: "Comment une startup a géré sa croissance avec Leopardo",
          industry: "Technologie",
          metrics: [
            { label: "Temps économisé", value: "15h/semaine" },
            { label: "Erreurs de paie", value: "0" },
            { label: "Satisfaction", value: "98%" },
          ],
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
          link: "/case-studies/factory",
        },
      ],
    },
    testimonials: {
      title: "Témoignages Clients",
      subtitle: "Ce que nos clients disent de Leopardo",
      items: [
        {
          quote: "Leopardo a transformé notre gestion RH. Nous avons économisé 10h par semaine!",
          author: "Marie Dupont",
          role: "Manager RH",
          company: "TechStartup Inc",
          avatar: "/avatars/marie.svg",
          rating: 5,
        },
        {
          quote: "La meilleure solution RH que nous ayons jamais utilisée. Zéro erreur depuis 2 ans.",
          author: "Jean Martin",
          role: "Comptable",
          company: "Finance Pro",
          avatar: "/avatars/jean.svg",
          rating: 5,
        },
        {
          quote: "Support excellent et interface intuitive. Nos employés adorent!",
          author: "Sophie Bernard",
          role: "Directrice",
          company: "Retail Solutions",
          avatar: "/avatars/sophie.svg",
          rating: 5,
        },
        {
          quote: "Enfin une solution qui grandit avec nous. Scalabilité impressionnante.",
          author: "Pierre Leclerc",
          role: "Fondateur",
          company: "Growth Ventures",
          avatar: "/avatars/pierre.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Questions Fréquentes",
      subtitle: "Trouvez les réponses à vos questions",
      items: [
        {
          question: "Quel type de pointage supportez-vous?",
          answer: "Nous supportons plusieurs méthodes: reconnaissance faciale, NFC, QR code, géolocalisation, et pointage manuel. Vous pouvez combiner plusieurs méthodes selon vos besoins.",
        },
        {
          question: "Comment gérez-vous les absences?",
          answer: "Les employés peuvent demander des absences via l'app mobile. Les managers approuvent selon votre workflow. Les soldes sont calculés automatiquement et mis à jour en temps réel.",
        },
        {
          question: "Pouvez-vous gérer plusieurs sites?",
          answer: "Oui, Leopardo supporte plusieurs sites avec des configurations différentes. Vous pouvez gérer tous vos sites depuis un seul tableau de bord.",
        },
        {
          question: "Comment fonctionne la planification?",
          answer: "Vous créez des calendriers partagés, assignez les shifts, et Leopardo détecte automatiquement les conflits. Les employés reçoivent des notifications de leurs shifts.",
        },
        {
          question: "Pouvez-vous intégrer avec notre paie?",
          answer: "Oui, les données de pointage et d'absences sont automatiquement synchronisées avec le module de paie pour un calcul exact.",
        },
        {
          question: "Quel est le délai de mise en place?",
          answer: "Configuration en moins de 5 minutes. Vous pouvez commencer à utiliser Leopardo immédiatement après votre inscription.",
        },
      ],
    },
    cta: {
      headline: "Prêt à simplifier votre gestion RH?",
      subheadline: "Commencez votre essai gratuit de 14 jours dès maintenant",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=employes",
      },
      ctaSecondary: {
        text: "Demander une démo",
        href: "/demo?module=employes",
      },
    },
  },

  documents: {
    hero: {
      headline: "Cabinet Numérique Sécurisé pour vos Documents",
      subheadline: "Stockage, partage et archivage conformes",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "Voir la démo",
        href: "/demo?module=documents",
      },
    },
    problem: {
      title: "Documents éparpillés et non sécurisés?",
      subtitle: "Les risques de ne pas avoir une solution centralisée",
      items: [
        {
          title: "Documents Éparpillés",
          description: "Emails, dossiers, clés USB - Impossible de trouver les documents",
        },
        {
          title: "Risques de Sécurité",
          description: "Données sensibles non chiffrées, accès non contrôlés",
        },
        {
          title: "Partage Compliqué",
          description: "Partage par email, versions multiples, confusion",
        },
        {
          title: "Non-Conformité",
          description: "Pas de traçabilité, audit trail absent, RGPD non respecté",
        },
      ],
    },
    solution: {
      title: "Un Cabinet Numérique Sécurisé",
      subtitle: "Centralisez et sécurisez tous vos documents",
      description: "Leopardo offre un cabinet numérique avec chiffrement AES-256, permissions granulaires et conformité RGPD.",
      features: [
        {
          title: "Stockage Sécurisé",
          description: "Chiffrement AES-256, sauvegarde automatique, redondance",
        },
        {
          title: "Partage Contrôlé",
          description: "Permissions granulaires, liens temporaires, audit trail",
        },
        {
          title: "Archivage Automatique",
          description: "Rétention automatique, destruction sécurisée, conformité",
        },
        {
          title: "Conformité RGPD",
          description: "Respect des réglementations, certifications, support légal",
        },
      ],
    },
    caseStudies: {
      title: "Cas d'Usage Réels",
      subtitle: "Comment nos clients utilisent le cabinet numérique",
      items: [
        {
          title: "Cabinet d'Avocats: Dossiers Clients",
          description: "Gestion sécurisée des dossiers clients confidentiels",
          industry: "Juridique",
          metrics: [
            { label: "Dossiers gérés", value: "1000+" },
            { label: "Temps économisé", value: "20h/mois" },
            { label: "Conformité", value: "100%" },
          ],
          link: "/case-studies/law-firm",
        },
        {
          title: "RH: Dossiers Employés",
          description: "Centralisation des dossiers employés confidentiels",
          industry: "RH",
          metrics: [
            { label: "Employés", value: "500+" },
            { label: "Sécurité", value: "AES-256" },
            { label: "Accès", value: "Contrôlé" },
          ],
          link: "/case-studies/hr-files",
        },
        {
          title: "Finance: Documents Comptables",
          description: "Archivage sécurisé des documents comptables",
          industry: "Finance",
          metrics: [
            { label: "Documents", value: "10K+" },
            { label: "Rétention", value: "Automatique" },
            { label: "Audit", value: "Complet" },
          ],
          link: "/case-studies/accounting",
        },
      ],
    },
    testimonials: {
      title: "Témoignages Clients",
      subtitle: "Ce que nos clients disent du cabinet numérique",
      items: [
        {
          quote: "Enfin une solution sécurisée pour nos documents confidentiels!",
          author: "Maître Dubois",
          role: "Avocat",
          company: "Cabinet Dubois & Associés",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Conformité RGPD garantie et interface simple. Parfait!",
          author: "Isabelle Moreau",
          role: "Responsable RH",
          company: "Groupe Moreau",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "Partage sécurisé avec nos clients. Très professionnel.",
          author: "Thomas Lefevre",
          role: "Directeur Financier",
          company: "Finance Solutions",
          avatar: "/avatars/lefevre.svg",
          rating: 5,
        },
        {
          quote: "Support excellent et mise en place rapide. Recommandé!",
          author: "Claire Rousseau",
          role: "Manager",
          company: "Consulting Pro",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Questions Fréquentes",
      subtitle: "Trouvez les réponses à vos questions",
      items: [
        {
          question: "Quel est le niveau de chiffrement?",
          answer: "Nous utilisons le chiffrement AES-256, le standard de l'industrie pour la sécurité maximale.",
        },
        {
          question: "Comment fonctionne le partage?",
          answer: "Vous pouvez partager des documents avec des permissions granulaires (lecture, modification, suppression). Les liens peuvent être temporaires.",
        },
        {
          question: "Quel est l'espace de stockage?",
          answer: "L'espace dépend de votre plan. Pilot: 100GB, Operations: 1TB, Enterprise: Illimité.",
        },
        {
          question: "Comment fonctionne l'archivage?",
          answer: "Vous définissez des règles de rétention. Les documents sont automatiquement archivés ou supprimés selon vos paramètres.",
        },
        {
          question: "Pouvez-vous garantir la conformité RGPD?",
          answer: "Oui, nous sommes certifiés RGPD et offrons un support légal pour assurer votre conformité.",
        },
        {
          question: "Quel est le délai de mise en place?",
          answer: "Configuration en moins de 5 minutes. Vous pouvez commencer à uploader des documents immédiatement.",
        },
      ],
    },
    cta: {
      headline: "Sécurisez vos documents dès maintenant",
      subheadline: "Essai gratuit de 14 jours, sans engagement",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "Demander une démo",
        href: "/demo?module=documents",
      },
    },
  },

  comptabilite: {
    hero: {
      headline: "Paie Automatisée et Conformité Garantie",
      subheadline: "Calculs exacts, bulletins générés, exports comptables",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "Voir la démo",
        href: "/demo?module=comptabilite",
      },
    },
    problem: {
      title: "Paie manuelle = Erreurs et Temps Perdu",
      subtitle: "Les défis de la gestion de paie traditionnelle",
      items: [
        {
          title: "Calculs Manuels",
          description: "Erreurs, oublis, calculs complexes, temps perdu",
        },
        {
          title: "Conformité Complexe",
          description: "Taux changeants, réglementations, mises à jour difficiles",
        },
        {
          title: "Exports Comptables",
          description: "Formats différents, intégrations manuelles, erreurs",
        },
        {
          title: "Avances sur Salaire",
          description: "Demandes manuelles, calculs complexes, déductions oubliées",
        },
      ],
    },
    solution: {
      title: "Paie Automatisée Complète",
      subtitle: "Automatisation totale de votre gestion de paie",
      description: "Leopardo automatise tous les calculs de paie, génère les bulletins et exporte directement vers votre comptabilité.",
      features: [
        {
          title: "Calcul Automatique",
          description: "Multi-devises, cotisations automatiques, taux à jour",
        },
        {
          title: "Bulletins Générés",
          description: "PDF automatiques, envoi email, archivage sécurisé",
        },
        {
          title: "Exports Comptables",
          description: "Formats standards, intégration directe, sans erreur",
        },
        {
          title: "Avances Automatisées",
          description: "Demandes, validation, déduction automatique",
        },
      ],
    },
    caseStudies: {
      title: "Cas d'Usage Réels",
      subtitle: "Comment nos clients gèrent leur paie",
      items: [
        {
          title: "PME: 50 Employés",
          description: "Paie mensuelle automatisée pour une PME en croissance",
          industry: "PME",
          metrics: [
            { label: "Temps économisé", value: "8h/mois" },
            { label: "Erreurs", value: "0" },
            { label: "Satisfaction", value: "100%" },
          ],
          link: "/case-studies/sme-payroll",
        },
        {
          title: "Startup: Avances Automatisées",
          description: "Gestion des avances sur salaire pour une startup",
          industry: "Startup",
          metrics: [
            { label: "Avances/mois", value: "20+" },
            { label: "Traitement", value: "Automatique" },
            { label: "Erreurs", value: "0" },
          ],
          link: "/case-studies/startup-advances",
        },
        {
          title: "Groupe: Multi-Entités",
          description: "Paie multi-entités et multi-devises pour un groupe",
          industry: "Groupe",
          metrics: [
            { label: "Entités", value: "5" },
            { label: "Devises", value: "3" },
            { label: "Employés", value: "500+" },
          ],
          link: "/case-studies/group-payroll",
        },
      ],
    },
    testimonials: {
      title: "Témoignages Clients",
      subtitle: "Ce que nos clients disent de la paie automatisée",
      items: [
        {
          quote: "Zéro erreur depuis 2 ans. Incroyable!",
          author: "Jean Martin",
          role: "Comptable",
          company: "Finance Pro",
          avatar: "/avatars/martin.svg",
          rating: 5,
        },
        {
          quote: "3x plus rapide qu'Excel. Nous avons économisé 10h par mois!",
          author: "Sophie Leclerc",
          role: "Manager RH",
          company: "Tech Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "Exports comptables directs. Pas de manipulation manuelle!",
          author: "Marc Dubois",
          role: "Expert-Comptable",
          company: "Dubois & Associés",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Conformité garantie. Nous dormons tranquilles!",
          author: "Nathalie Rousseau",
          role: "Directrice",
          company: "Groupe Rousseau",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Questions Fréquentes",
      subtitle: "Trouvez les réponses à vos questions",
      items: [
        {
          question: "Supportez-vous plusieurs devises?",
          answer: "Oui, nous supportons plus de 150 devises avec conversion automatique et taux à jour.",
        },
        {
          question: "Comment fonctionne la conformité?",
          answer: "Nos taux et règles sont mis à jour automatiquement selon les réglementations locales. Vous êtes toujours conforme.",
        },
        {
          question: "Pouvez-vous exporter vers ma comptabilité?",
          answer: "Oui, nous exportons directement vers les logiciels comptables populaires (Sage, Ciel, etc.).",
        },
        {
          question: "Comment fonctionne les avances?",
          answer: "Les employés demandent une avance via l'app. Les managers approuvent. La déduction est automatique sur la paie suivante.",
        },
        {
          question: "Quel est le délai de mise en place?",
          answer: "Configuration en moins de 5 minutes. Vous pouvez générer votre première paie immédiatement.",
        },
        {
          question: "Pouvez-vous gérer plusieurs entités?",
          answer: "Oui, vous pouvez gérer plusieurs entités avec des configurations différentes depuis un seul tableau de bord.",
        },
      ],
    },
    cta: {
      headline: "Automatisez votre paie dès maintenant",
      subheadline: "Essai gratuit de 14 jours, sans engagement",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "Demander une démo",
        href: "/demo?module=comptabilite",
      },
    },
  },

  marketing: {
    hero: {
      headline: "Outils Marketing Intégrés pour PME",
      subheadline: "Email, SMS, réseaux sociaux en un seul endroit",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "Voir la démo",
        href: "/demo?module=marketing",
      },
    },
    problem: {
      title: "Outils Marketing Éparpillés et Chers?",
      subtitle: "Les défis de la gestion marketing fragmentée",
      items: [
        {
          title: "Outils Multiples",
          description: "Email, SMS, réseaux sociaux - Chacun dans un outil différent",
        },
        {
          title: "Coûts Élevés",
          description: "Abonnements multiples, factures séparées, budget incontrôlable",
        },
        {
          title: "Pas de Vue d'Ensemble",
          description: "Impossible de voir la performance globale des campagnes",
        },
        {
          title: "Intégration Complexe",
          description: "Pas de lien avec vos données RH, segmentation manuelle",
        },
      ],
    },
    solution: {
      title: "Marketing Complet Intégré",
      subtitle: "Tous vos outils marketing en un seul endroit",
      description: "Leopardo offre email, SMS, réseaux sociaux et analytics intégrés à votre plateforme RH.",
      features: [
        {
          title: "Email Marketing",
          description: "Templates, segmentation, automation, A/B testing",
        },
        {
          title: "SMS Marketing",
          description: "Campagnes ciblées, tracking, intégration RH",
        },
        {
          title: "Réseaux Sociaux",
          description: "Partage automatique, scheduling, analytics",
        },
        {
          title: "Analytics Centralisées",
          description: "ROI, engagement, conversions - Tout en un seul endroit",
        },
      ],
    },
    caseStudies: {
      title: "Cas d'Usage Réels",
      subtitle: "Comment nos clients utilisent le marketing",
      items: [
        {
          title: "Recrutement: Campagnes Ciblées",
          description: "Campagnes de recrutement ciblées et automatisées",
          industry: "RH",
          metrics: [
            { label: "Candidatures", value: "3x+" },
            { label: "Coût par candidat", value: "-40%" },
            { label: "Taux de réponse", value: "35%" },
          ],
          link: "/case-studies/recruitment",
        },
        {
          title: "Engagement: Newsletters Employés",
          description: "Newsletters internes pour engager les employés",
          industry: "Engagement",
          metrics: [
            { label: "Taux d'ouverture", value: "45%" },
            { label: "Clics", value: "15%" },
            { label: "Satisfaction", value: "90%" },
          ],
          link: "/case-studies/employee-engagement",
        },
        {
          title: "Promotion: Campagnes Clients",
          description: "Campagnes de promotion vers les clients",
          industry: "Marketing",
          metrics: [
            { label: "Taux de conversion", value: "8%" },
            { label: "ROI", value: "300%" },
            { label: "Clients acquis", value: "50+" },
          ],
          link: "/case-studies/customer-campaigns",
        },
      ],
    },
    testimonials: {
      title: "Témoignages Clients",
      subtitle: "Ce que nos clients disent du marketing",
      items: [
        {
          quote: "Campagnes plus efficaces et moins chères. Excellent!",
          author: "Luc Moreau",
          role: "Marketing Manager",
          company: "Tech Marketing",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "Taux d'ouverture email de 45%. Incroyable!",
          author: "Céline Dupont",
          role: "Responsable Marketing",
          company: "Growth Co",
          avatar: "/avatars/dupont.svg",
          rating: 5,
        },
        {
          quote: "Intégration RH parfaite. Segmentation automatique!",
          author: "David Leclerc",
          role: "Marketing Director",
          company: "Digital Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "ROI 300% sur nos campagnes. Recommandé!",
          author: "Valérie Rousseau",
          role: "CMO",
          company: "Rousseau Group",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Questions Fréquentes",
      subtitle: "Trouvez les réponses à vos questions",
      items: [
        {
          question: "Pouvez-vous segmenter par données RH?",
          answer: "Oui, vous pouvez segmenter automatiquement par département, localisation, rôle, etc.",
        },
        {
          question: "Supportez-vous l'automation?",
          answer: "Oui, vous pouvez créer des workflows automatisés basés sur des événements RH.",
        },
        {
          question: "Quel est le coût?",
          answer: "Inclus dans votre plan Leopardo. Pas de coûts supplémentaires pour email, SMS ou réseaux sociaux.",
        },
        {
          question: "Pouvez-vous faire de l'A/B testing?",
          answer: "Oui, vous pouvez tester différentes versions de vos emails et SMS pour optimiser.",
        },
        {
          question: "Comment fonctionne le scheduling?",
          answer: "Vous pouvez programmer vos campagnes pour un moment spécifique ou les envoyer immédiatement.",
        },
        {
          question: "Quel est le délai de mise en place?",
          answer: "Configuration en moins de 5 minutes. Vous pouvez envoyer votre première campagne immédiatement.",
        },
      ],
    },
    cta: {
      headline: "Lancez vos campagnes marketing dès maintenant",
      subheadline: "Essai gratuit de 14 jours, sans engagement",
      ctaPrimary: {
        text: "Essai gratuit",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "Demander une démo",
        href: "/demo?module=marketing",
      },
    },
  },
};

/* ─────────────────────────────────────────────────────────────
   LOCALISATION (issue #4196) — lot 1 : module « employes ».
   Le contenu FR ci-dessus (modulePageContent) sert de référence et
   de fallback. Les locales en/tr/ar fournissent leurs traductions
   module par module ; `getModulePageContent(locale)` fusionne la
   locale sur le FR (les modules non encore traduits retombent sur FR,
   pattern des lots #4206/#4191). Les lots suivants complètent
   documents / comptabilite / marketing.
────────────────────────────────────────────────────────────── */

type ModulePageContent = typeof modulePageContent

const modulePageContentEn: Partial<ModulePageContent> = {
  employes: {
    hero: {
      headline: 'Simplified HR Management for SMEs',
      subheadline: 'Time tracking, absences, schedules and reviews in one place',
      ctaPrimary: { text: 'Free trial', href: '/signup?module=employes' },
      ctaSecondary: { text: 'See the demo', href: '/demo?module=employes' },
    },
    problem: {
      title: 'Still managing employees with Excel and emails?',
      subtitle: 'Common challenges of SMEs without an HR solution',
      items: [
        { title: 'Manual time tracking', description: 'Errors, fraud, and wasted time managing paper timesheets' },
        { title: 'No visibility', description: 'No way to see who is present, absent or on leave in real time' },
        { title: 'Complex leave management', description: 'Scattered requests, manual approvals, wrong balances' },
        { title: 'Difficult scheduling', description: 'Manual planning, undetected conflicts, inefficient communication' },
      ],
    },
    solution: {
      title: 'How Leopardo Solves These Problems',
      subtitle: 'A centralized platform for all your HR management',
      description: 'Leopardo automates and centralizes every aspect of managing your employees, from payroll to absences.',
      features: [
        { title: 'Smart time tracking', description: 'NFC, biometrics, QR code or geolocation — choose your method' },
        { title: 'Leave management', description: 'Automated requests, multi-level approvals, real-time balances' },
        { title: 'Flexible scheduling', description: 'Shared calendars, smart alerts, conflict management' },
        { title: 'Reviews & performance', description: 'Continuous feedback, aligned goals, performance tracking' },
      ],
    },
    caseStudies: {
      title: 'Real Use Cases',
      subtitle: 'See how our customers use Leopardo',
      items: [
        {
          title: 'Tech Startup: from 5 to 50 employees',
          description: 'How a startup managed its growth with Leopardo',
          industry: 'Technology',
          metrics: [
            { label: 'Time saved', value: '15h/week' },
            { label: 'Payroll errors', value: '0' },
            { label: 'Satisfaction', value: '98%' },
          ],
          link: '/case-studies/startup',
        },
        {
          title: 'Retail: 50 point-of-sale locations',
          description: 'Centralized time tracking for a store chain',
          industry: 'Retail',
          metrics: [
            { label: 'Locations', value: '50' },
            { label: 'Employees managed', value: '500+' },
            { label: 'Cost reduction', value: '30%' },
          ],
          link: '/case-studies/retail',
        },
        {
          title: 'Factory: advanced biometrics',
          description: 'Biometric time tracking for a 200-employee factory',
          industry: 'Manufacturing',
          metrics: [
            { label: 'Employees', value: '200' },
            { label: 'Accuracy', value: '99.9%' },
            { label: 'Fraud reduced', value: '95%' },
          ],
          link: '/case-studies/factory',
        },
      ],
    },
    testimonials: {
      title: 'Customer Testimonials',
      subtitle: 'What our customers say about Leopardo',
      items: [
        {
          quote: 'Leopardo transformed our HR management. We save 10 hours a week!',
          author: 'Marie Dupont',
          role: 'HR Manager',
          company: 'TechStartup Inc',
          avatar: '/avatars/marie.svg',
          rating: 5,
        },
        {
          quote: 'The best HR solution we have ever used. Zero errors for 2 years.',
          author: 'Jean Martin',
          role: 'Accountant',
          company: 'Finance Pro',
          avatar: '/avatars/jean.svg',
          rating: 5,
        },
        {
          quote: 'Excellent support and an intuitive interface. Our employees love it!',
          author: 'Sophie Bernard',
          role: 'Director',
          company: 'Retail Solutions',
          avatar: '/avatars/sophie.svg',
          rating: 5,
        },
        {
          quote: 'Finally a solution that grows with us. Impressive scalability.',
          author: 'Pierre Leclerc',
          role: 'Founder',
          company: 'Growth Ventures',
          avatar: '/avatars/pierre.svg',
          rating: 5,
        },
      ],
    },
    faq: {
      title: 'Frequently Asked Questions',
      subtitle: 'Find answers to your questions',
      items: [
        {
          question: 'Which time tracking methods do you support?',
          answer: 'We support several methods: facial recognition, NFC, QR code, geolocation and manual entry. You can combine methods to fit your needs.',
        },
        {
          question: 'How do you manage absences?',
          answer: 'Employees request time off from the mobile app. Managers approve according to your workflow. Balances are computed automatically and updated in real time.',
        },
        {
          question: 'Can you manage multiple sites?',
          answer: 'Yes, Leopardo supports multiple sites with different configurations. Manage all your sites from a single dashboard.',
        },
        {
          question: 'How does scheduling work?',
          answer: 'Create shared calendars, assign shifts, and Leopardo automatically detects conflicts. Employees are notified of their shifts.',
        },
        {
          question: 'Can you integrate with our payroll?',
          answer: 'Yes, time tracking and absence data are automatically synchronized with the payroll module for accurate calculations.',
        },
        {
          question: 'How long does it take to get started?',
          answer: 'Setup takes less than 5 minutes. You can start using Leopardo immediately after signing up.',
        },
      ],
    },
    cta: {
      headline: 'Ready to simplify your HR management?',
      subheadline: 'Start your free 14-day trial today',
      ctaPrimary: { text: 'Free trial', href: '/signup?module=employes' },
      ctaSecondary: { text: 'Request a demo', href: '/demo?module=employes' },
    },
  },
}

const modulePageContentTr: Partial<ModulePageContent> = {
  employes: {
    hero: {
      headline: "KOBİ'ler için Basitleştirilmiş İK Yönetimi",
      subheadline: 'Yoklama, izinler, planlamalar ve değerlendirmeler tek yerde',
      ctaPrimary: { text: 'Ücretsiz deneme', href: '/signup?module=employes' },
      ctaSecondary: { text: 'Demoyu gör', href: '/demo?module=employes' },
    },
    problem: {
      title: 'Çalışanlarınızı hâlâ Excel ve e-postalarla mı yönetiyorsunuz?',
      subtitle: "İK çözümü olmayan KOBİ'lerin karşılaştığı zorluklar",
      items: [
        { title: 'Manuel yoklama', description: 'Hatalar, sahtecilik ve kağıt puantajlarla harcanan zaman' },
        { title: 'Görünürlük yok', description: 'Kimin işte, kimin izinli olduğunu gerçek zamanlı görememek' },
        { title: 'Karmaşık izin yönetimi', description: 'Dağınık talepler, manuel onaylar, hatalı bakiyeler' },
        { title: 'Zor planlama', description: 'Manuel planlama, fark edilmeyen çakışmalar, verimsiz iletişim' },
      ],
    },
    solution: {
      title: 'Leopardo Bu Sorunları Nasıl Çözer',
      subtitle: 'Tüm İK yönetiminiz için merkezi bir platform',
      description: 'Leopardo, bordrodan izinlere kadar çalışan yönetiminizin her yönünü otomatikleştirir ve merkezileştirir.',
      features: [
        { title: 'Akıllı yoklama', description: 'NFC, biyometri, QR kod veya konum — yönteminizi seçin' },
        { title: 'İzin yönetimi', description: 'Otomatik talepler, çok seviyeli onaylar, gerçek zamanlı bakiyeler' },
        { title: 'Esnek planlama', description: 'Paylaşılan takvimler, akıllı uyarılar, çakışma yönetimi' },
        { title: 'Değerlendirme ve performans', description: 'Sürekli geri bildirim, uyumlu hedefler, performans takibi' },
      ],
    },
    caseStudies: {
      title: 'Gerçek Kullanım Örnekleri',
      subtitle: "Müşterilerimizin Leopardo'yu nasıl kullandığını görün",
      items: [
        {
          title: "Teknoloji girişimi: 5'ten 50 çalışana",
          description: 'Bir girişim büyümesini Leopardo ile nasıl yönetti',
          industry: 'Teknoloji',
          metrics: [
            { label: 'Tasarruf edilen zaman', value: '15s/hafta' },
            { label: 'Bordro hatası', value: '0' },
            { label: 'Memnuniyet', value: '%98' },
          ],
          link: '/case-studies/startup',
        },
        {
          title: 'Perakende: 50 satış noktası',
          description: 'Bir mağaza zinciri için merkezi yoklama',
          industry: 'Perakende',
          metrics: [
            { label: 'Satış noktası', value: '50' },
            { label: 'Yönetilen çalışan', value: '500+' },
            { label: 'Maliyet düşüşü', value: '%30' },
          ],
          link: '/case-studies/retail',
        },
        {
          title: 'Fabrika: gelişmiş biyometri',
          description: '200 çalışanlı bir fabrika için biyometrik yoklama',
          industry: 'Üretim',
          metrics: [
            { label: 'Çalışan', value: '200' },
            { label: 'Doğruluk', value: '%99,9' },
            { label: 'Azaltılan sahtecilik', value: '%95' },
          ],
          link: '/case-studies/factory',
        },
      ],
    },
    testimonials: {
      title: 'Müşteri Görüşleri',
      subtitle: 'Müşterilerimizin Leopardo hakkında söyledikleri',
      items: [
        {
          quote: 'Leopardo İK yönetimimizi dönüştürdü. Haftada 10 saat tasarruf ediyoruz!',
          author: 'Marie Dupont',
          role: 'İK Müdürü',
          company: 'TechStartup Inc',
          avatar: '/avatars/marie.svg',
          rating: 5,
        },
        {
          quote: 'Şimdiye kadar kullandığımız en iyi İK çözümü. 2 yıldır sıfır hata.',
          author: 'Jean Martin',
          role: 'Muhasebeci',
          company: 'Finance Pro',
          avatar: '/avatars/jean.svg',
          rating: 5,
        },
        {
          quote: 'Mükemmel destek ve sezgisel arayüz. Çalışanlarımız bayılıyor!',
          author: 'Sophie Bernard',
          role: 'Direktör',
          company: 'Retail Solutions',
          avatar: '/avatars/sophie.svg',
          rating: 5,
        },
        {
          quote: 'Sonunda bizimle büyüyen bir çözüm. Etkileyici ölçeklenebilirlik.',
          author: 'Pierre Leclerc',
          role: 'Kurucu',
          company: 'Growth Ventures',
          avatar: '/avatars/pierre.svg',
          rating: 5,
        },
      ],
    },
    faq: {
      title: 'Sık Sorulan Sorular',
      subtitle: 'Sorularınızın yanıtlarını bulun',
      items: [
        {
          question: 'Hangi yoklama yöntemlerini destekliyorsunuz?',
          answer: 'Birçok yöntemi destekliyoruz: yüz tanıma, NFC, QR kod, konum ve manuel giriş. İhtiyaçlarınıza göre yöntemleri birleştirebilirsiniz.',
        },
        {
          question: 'İzinleri nasıl yönetiyorsunuz?',
          answer: 'Çalışanlar izin taleplerini mobil uygulamadan yapar. Yöneticiler iş akışınıza göre onaylar. Bakiyeler otomatik hesaplanır ve gerçek zamanlı güncellenir.',
        },
        {
          question: 'Birden fazla şubeyi yönetebilir misiniz?',
          answer: 'Evet, Leopardo farklı yapılandırmalara sahip birden fazla şubeyi destekler. Tüm şubelerinizi tek bir panelden yönetebilirsiniz.',
        },
        {
          question: 'Planlama nasıl çalışır?',
          answer: 'Paylaşılan takvimler oluşturun, vardiyaları atayın; Leopardo çakışmaları otomatik tespit eder. Çalışanlar vardiyalarından haberdar edilir.',
        },
        {
          question: 'Bordromuzla entegre olabilir misiniz?',
          answer: 'Evet, yoklama ve izin verileri doğru hesaplama için bordro modülüyle otomatik senkronize edilir.',
        },
        {
          question: 'Kurulum ne kadar sürer?',
          answer: "Kurulum 5 dakikadan kısa sürer. Kaydolduktan hemen sonra Leopardo'yu kullanmaya başlayabilirsiniz.",
        },
      ],
    },
    cta: {
      headline: 'İK yönetiminizi basitleştirmeye hazır mısınız?',
      subheadline: '14 günlük ücretsiz denemenize hemen başlayın',
      ctaPrimary: { text: 'Ücretsiz deneme', href: '/signup?module=employes' },
      ctaSecondary: { text: 'Demo talep edin', href: '/demo?module=employes' },
    },
  },
}

const modulePageContentAr: Partial<ModulePageContent> = {
  employes: {
    hero: {
      headline: 'إدارة موارد بشرية مبسّطة للشركات الصغيرة والمتوسطة',
      subheadline: 'تسجيل الحضور والإجازات والجداول والتقييمات في مكان واحد',
      ctaPrimary: { text: 'تجربة مجانية', href: '/signup?module=employes' },
      ctaSecondary: { text: 'شاهد العرض', href: '/demo?module=employes' },
    },
    problem: {
      title: 'هل ما زلت تدير موظفيك عبر Excel والبريد الإلكتروني؟',
      subtitle: 'التحديات الشائعة للشركات الصغيرة دون حل موارد بشرية',
      items: [
        { title: 'تسجيل حضور يدوي', description: 'أخطاء واحتيال ووقت ضائع في إدارة أوراق الحضور' },
        { title: 'لا رؤية', description: 'لا يمكن معرفة من هو حاضر أو غائب أو في إجازة في الوقت الفعلي' },
        { title: 'إدارة إجازات معقدة', description: 'طلبات متفرقة وموافقات يدوية وأرصدة خاطئة' },
        { title: 'جداول صعبة', description: 'تخطيط يدوي وتعارضات غير مكتشفة وتواصل غير فعّال' },
      ],
    },
    solution: {
      title: 'كيف يحل ليوباردو هذه المشاكل',
      subtitle: 'منصة مركزية لكل إدارة الموارد البشرية',
      description: 'يعمل ليوباردو على أتمتة ومركزة كل جانب من إدارة موظفيك، من الرواتب إلى الإجازات.',
      features: [
        { title: 'تسجيل حضور ذكي', description: 'NFC أو بصمة أو رمز QR أو تحديد الموقع — اختر طريقتك' },
        { title: 'إدارة الإجازات', description: 'طلبات آلية وموافقات متعددة المستويات وأرصدة في الوقت الفعلي' },
        { title: 'جدولة مرنة', description: 'تقويمات مشتركة وتنبيهات ذكية وإدارة التعارضات' },
        { title: 'التقييمات والأداء', description: 'ملاحظات مستمرة وأهداف متوافقة وتتبع الأداء' },
      ],
    },
    caseStudies: {
      title: 'حالات استخدام حقيقية',
      subtitle: 'اكتشف كيف يستخدم عملاؤنا ليوباردو',
      items: [
        {
          title: 'شركة ناشئة تقنية: من 5 إلى 50 موظفاً',
          description: 'كيف أدارت شركة ناشئة نموها مع ليوباردو',
          industry: 'تقنية',
          metrics: [
            { label: 'وقت موفّر', value: '15 ساعة/أسبوع' },
            { label: 'أخطاء الرواتب', value: '0' },
            { label: 'رضا', value: '98%' },
          ],
          link: '/case-studies/startup',
        },
        {
          title: 'تجارة التجزئة: 50 نقطة بيع',
          description: 'تسجيل حضور مركزي لسلسلة متاجر',
          industry: 'تجزئة',
          metrics: [
            { label: 'نقاط البيع', value: '50' },
            { label: 'موظفون مُدارون', value: '500+' },
            { label: 'خفض التكاليف', value: '30%' },
          ],
          link: '/case-studies/retail',
        },
        {
          title: 'مصنع: بصمة متقدمة',
          description: 'تسجيل حضور بالبصمة لمصنع يضم 200 موظف',
          industry: 'صناعة',
          metrics: [
            { label: 'موظفون', value: '200' },
            { label: 'دقة', value: '99.9%' },
            { label: 'انخفاض الاحتيال', value: '95%' },
          ],
          link: '/case-studies/factory',
        },
      ],
    },
    testimonials: {
      title: 'آراء العملاء',
      subtitle: 'ماذا يقول عملاؤنا عن ليوباردو',
      items: [
        {
          quote: 'غيّر ليوباردو إدارة الموارد البشرية لدينا. نوفّر 10 ساعات أسبوعياً!',
          author: 'ماري دوبون',
          role: 'مديرة موارد بشرية',
          company: 'TechStartup Inc',
          avatar: '/avatars/marie.svg',
          rating: 5,
        },
        {
          quote: 'أفضل حل للموارد البشرية استخدمناه على الإطلاق. صفر أخطاء منذ عامين.',
          author: 'جان مارتن',
          role: 'محاسب',
          company: 'Finance Pro',
          avatar: '/avatars/jean.svg',
          rating: 5,
        },
        {
          quote: 'دعم ممتاز وواجهة بديهية. موظفونا يعشقونها!',
          author: 'صوفي برنار',
          role: 'مديرة',
          company: 'Retail Solutions',
          avatar: '/avatars/sophie.svg',
          rating: 5,
        },
        {
          quote: 'أخيراً حل ينمو معنا. قابلية توسع مذهلة.',
          author: 'بيير لوكلير',
          role: 'مؤسس',
          company: 'Growth Ventures',
          avatar: '/avatars/pierre.svg',
          rating: 5,
        },
      ],
    },
    faq: {
      title: 'الأسئلة الشائعة',
      subtitle: 'اعثر على إجابات لأسئلتك',
      items: [
        {
          question: 'ما طرق تسجيل الحضور التي تدعمونها؟',
          answer: 'ندعم عدة طرق: التعرف على الوجه، NFC، رمز QR، تحديد الموقع، والإدخال اليدوي. يمكنك دمج الطرق حسب احتياجاتك.',
        },
        {
          question: 'كيف تديرون الإجازات؟',
          answer: 'يطلب الموظفون الإجازة من تطبيق الجوال. يوافق المديرون وفق سير عملك. تُحسب الأرصدة تلقائياً وتُحدَّث في الوقت الفعلي.',
        },
        {
          question: 'هل يمكنكم إدارة عدة مواقع؟',
          answer: 'نعم، يدعم ليوباردو عدة مواقع بإعدادات مختلفة. يمكنك إدارة جميع مواقعك من لوحة تحكم واحدة.',
        },
        {
          question: 'كيف تعمل الجدولة؟',
          answer: 'أنشئ تقاويم مشتركة وعيّن الورديات، ويكتشف ليوباردو التعارضات تلقائياً. يتلقى الموظفون إشعارات بوردياتهم.',
        },
        {
          question: 'هل يمكنكم التكامل مع نظام الرواتب لدينا؟',
          answer: 'نعم، تتم مزامنة بيانات الحضور والإجازات تلقائياً مع وحدة الرواتب لحساب دقيق.',
        },
        {
          question: 'كم يستغرق الإعداد؟',
          answer: 'يستغرق الإعداد أقل من 5 دقائق. يمكنك البدء في استخدام ليوباردو فور التسجيل.',
        },
      ],
    },
    cta: {
      headline: 'مستعد لتبسيط إدارة الموارد البشرية لديك؟',
      subheadline: 'ابدأ تجربتك المجانية لمدة 14 يوماً الآن',
      ctaPrimary: { text: 'تجربة مجانية', href: '/signup?module=employes' },
      ctaSecondary: { text: 'اطلب عرضاً', href: '/demo?module=employes' },
    },
  },
}

/**
 * Catalogues localisés — lot 1 (#4196) : seul « employes » est traduit
 * en/tr/ar ; les autres modules retombent sur le FR (fusion par module).
 */
export const modulePageContentByLocale: Record<AppLocale, Partial<ModulePageContent>> = {
  fr: modulePageContent,
  en: modulePageContentEn,
  tr: modulePageContentTr,
  ar: modulePageContentAr,
}

export function getModulePageContent(locale: AppLocale): ModulePageContent {
  const partial = modulePageContentByLocale[locale] ?? modulePageContentByLocale.en
  return { ...modulePageContent, ...partial }
}
