/**
 * Module page content for the vitrine
 */

import type { AppLocale } from '@/lib/i18n'

export const modulePageContent = {
  employes: {
    sections: {
      heroBadge: "Gestion RH Complète",
      problemBadge: "Les Défis",
      solutionBadge: "Notre Solution",
      featuresTitle: "Fonctionnalités Détaillées",
      featuresSubtitle: "Tout ce dont vous avez besoin",
      featuresBadge: "Puissant & Flexible",
    },
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
    sections: {
      heroBadge: "Cabinet Numérique",
      problemBadge: "Les Défis",
      solutionBadge: "Notre Solution",
      featuresTitle: "Fonctionnalités Détaillées",
      featuresSubtitle: "Tout ce dont vous avez besoin",
      featuresBadge: "Sécurisé & Conforme",
    },
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
    sections: {
      heroBadge: "Paie Automatisée",
      problemBadge: "Les Défis",
      solutionBadge: "Notre Solution",
      featuresTitle: "Fonctionnalités Détaillées",
      featuresSubtitle: "Tout ce dont vous avez besoin",
      featuresBadge: "Complet & Fiable",
    },
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
    sections: {
      heroBadge: "Marketing Complet",
      problemBadge: "Les Défis",
      solutionBadge: "Notre Solution",
      featuresTitle: "Fonctionnalités Détaillées",
      featuresSubtitle: "Tout ce dont vous avez besoin",
      featuresBadge: "Puissant & Flexible",
    },
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
    sections: {
      heroBadge: "Complete HR Management",
      problemBadge: "The Challenges",
      solutionBadge: "Our Solution",
      featuresTitle: "Detailed Features",
      featuresSubtitle: "Everything you need",
      featuresBadge: "Powerful & Flexible",
    },
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
  documents: {
    sections: {
      heroBadge: "Digital Document Hub",
      problemBadge: "The Challenges",
      solutionBadge: "Our Solution",
      featuresTitle: "Detailed Features",
      featuresSubtitle: "Everything you need",
      featuresBadge: "Secure & Compliant",
    },
    hero: {
      headline: "Secure Digital Cabinet for Your Documents",
      subheadline: "Compliant storage, sharing and archiving",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "See the demo",
        href: "/demo?module=documents",
      },
    },
    problem: {
      title: "Documents scattered and unsecured?",
      subtitle: "The risks of not having a centralized solution",
      items: [
        {
          title: "Scattered Documents",
          description: "Emails, folders, USB drives - impossible to find documents",
        },
        {
          title: "Security Risks",
          description: "Unencrypted sensitive data, uncontrolled access",
        },
        {
          title: "Complex Sharing",
          description: "Sharing by email, multiple versions, confusion",
        },
        {
          title: "Non-Compliance",
          description: "No traceability, missing audit trail, GDPR breach",
        },
      ],
    },
    solution: {
      title: "A Secure Digital Cabinet",
      subtitle: "Centralize and secure all your documents",
      description: "Leopardo offers a digital cabinet with AES-256 encryption, granular permissions and GDPR compliance.",
      features: [
        {
          title: "Secure Storage",
          description: "AES-256 encryption, automatic backup, redundancy",
        },
        {
          title: "Controlled Sharing",
          description: "Granular permissions, temporary links, audit trail",
        },
        {
          title: "Automatic Archiving",
          description: "Automatic retention, secure destruction, compliance",
        },
        {
          title: "GDPR Compliance",
          description: "Regulation compliance, certifications, legal support",
        },
      ],
    },
    caseStudies: {
      title: "Real-World Use Cases",
      subtitle: "How our clients use the digital cabinet",
      items: [
        {
          title: "Law Firm: Client Files",
          description: "Secure management of confidential client files",
          industry: "Legal",
          metrics: [
            {
              label: "Files managed",
              value: "1000+",
            },
            {
              label: "Time saved",
              value: "20h/month",
            },
            {
              label: "Compliance",
              value: "100%",
            },
          ],
          link: "/case-studies/law-firm",
        },
        {
          title: "HR: Employee Files",
          description: "Centralizing confidential employee files",
          industry: "HR",
          metrics: [
            {
              label: "Employees",
              value: "500+",
            },
            {
              label: "Security",
              value: "AES-256",
            },
            {
              label: "Access",
              value: "Controlled",
            },
          ],
          link: "/case-studies/hr-files",
        },
        {
          title: "Finance: Accounting Documents",
          description: "Secure archiving of accounting documents",
          industry: "Finance",
          metrics: [
            {
              label: "Documents",
              value: "10K+",
            },
            {
              label: "Retention",
              value: "Automatic",
            },
            {
              label: "Audit",
              value: "Complete",
            },
          ],
          link: "/case-studies/accounting",
        },
      ],
    },
    testimonials: {
      title: "Client Testimonials",
      subtitle: "What our clients say about the digital cabinet",
      items: [
        {
          quote: "Finally a secure solution for our confidential documents!",
          author: "Maître Dubois",
          role: "Lawyer",
          company: "Dubois & Partners Law Firm",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Guaranteed GDPR compliance and a simple interface. Perfect!",
          author: "Isabelle Moreau",
          role: "HR Manager",
          company: "Moreau Group",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "Secure sharing with our clients. Very professional.",
          author: "Thomas Lefevre",
          role: "CFO",
          company: "Finance Solutions",
          avatar: "/avatars/lefevre.svg",
          rating: 5,
        },
        {
          quote: "Excellent support and fast onboarding. Recommended!",
          author: "Claire Rousseau",
          role: "Manager",
          company: "Consulting Pro",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Frequently Asked Questions",
      subtitle: "Find the answers to your questions",
      items: [
        {
          question: "What is the encryption level?",
          answer: "We use AES-256 encryption, the industry standard for maximum security.",
        },
        {
          question: "How does sharing work?",
          answer: "You can share documents with granular permissions (read, edit, delete). Links can be temporary.",
        },
        {
          question: "How much storage space is available?",
          answer: "Storage depends on your plan. Pilot: 100GB, Operations: 1TB, Enterprise: Unlimited.",
        },
        {
          question: "How does archiving work?",
          answer: "You define retention rules. Documents are automatically archived or deleted according to your settings.",
        },
        {
          question: "Can you guarantee GDPR compliance?",
          answer: "Yes, we are GDPR certified and offer legal support to ensure your compliance.",
        },
        {
          question: "How long does setup take?",
          answer: "Setup takes less than 5 minutes. You can start uploading documents immediately.",
        },
      ],
    },
    cta: {
      headline: "Secure your documents now",
      subheadline: "14-day free trial, no commitment",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "Request a demo",
        href: "/demo?module=documents",
      },
    },
  },
  comptabilite: {
    sections: {
      heroBadge: "Automated Payroll",
      problemBadge: "The Challenges",
      solutionBadge: "Our Solution",
      featuresTitle: "Detailed Features",
      featuresSubtitle: "Everything you need",
      featuresBadge: "Complete & Reliable",
    },
    hero: {
      headline: "Automated Payroll with Guaranteed Compliance",
      subheadline: "Accurate calculations, generated payslips, accounting exports",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "See the demo",
        href: "/demo?module=comptabilite",
      },
    },
    problem: {
      title: "Manual payroll = Errors and wasted time",
      subtitle: "The challenges of traditional payroll management",
      items: [
        {
          title: "Manual Calculations",
          description: "Errors, omissions, complex calculations, lost time",
        },
        {
          title: "Complex Compliance",
          description: "Changing rates, regulations, hard-to-keep-update updates",
        },
        {
          title: "Accounting Exports",
          description: "Different formats, manual integrations, errors",
        },
        {
          title: "Salary Advances",
          description: "Manual requests, complex calculations, forgotten deductions",
        },
      ],
    },
    solution: {
      title: "Complete Automated Payroll",
      subtitle: "Full automation of your payroll management",
      description: "Leopardo automates all payroll calculations, generates payslips and exports directly to your accounting.",
      features: [
        {
          title: "Automatic Calculation",
          description: "Multi-currency, automatic contributions, up-to-date rates",
        },
        {
          title: "Generated Payslips",
          description: "Automatic PDFs, email delivery, secure archiving",
        },
        {
          title: "Accounting Exports",
          description: "Standard formats, direct integration, error-free",
        },
        {
          title: "Automated Advances",
          description: "Requests, validation, automatic deduction",
        },
      ],
    },
    caseStudies: {
      title: "Real-World Use Cases",
      subtitle: "How our clients manage their payroll",
      items: [
        {
          title: "SME: 50 Employees",
          description: "Automated monthly payroll for a growing SME",
          industry: "SME",
          metrics: [
            {
              label: "Time saved",
              value: "8h/month",
            },
            {
              label: "Errors",
              value: "0",
            },
            {
              label: "Satisfaction",
              value: "100%",
            },
          ],
          link: "/case-studies/sme-payroll",
        },
        {
          title: "Startup: Automated Advances",
          description: "Salary advance management for a startup",
          industry: "Startup",
          metrics: [
            {
              label: "Advances/month",
              value: "20+",
            },
            {
              label: "Processing",
              value: "Automatic",
            },
            {
              label: "Errors",
              value: "0",
            },
          ],
          link: "/case-studies/startup-advances",
        },
        {
          title: "Group: Multi-Entity",
          description: "Multi-entity, multi-currency payroll for a group",
          industry: "Group",
          metrics: [
            {
              label: "Entities",
              value: "5",
            },
            {
              label: "Currencies",
              value: "3",
            },
            {
              label: "Employees",
              value: "500+",
            },
          ],
          link: "/case-studies/group-payroll",
        },
      ],
    },
    testimonials: {
      title: "Client Testimonials",
      subtitle: "What our clients say about automated payroll",
      items: [
        {
          quote: "Zero errors for 2 years. Incredible!",
          author: "Jean Martin",
          role: "Accountant",
          company: "Finance Pro",
          avatar: "/avatars/martin.svg",
          rating: 5,
        },
        {
          quote: "3x faster than Excel. We save 10 hours a month!",
          author: "Sophie Leclerc",
          role: "HR Manager",
          company: "Tech Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "Direct accounting exports. No manual manipulation!",
          author: "Marc Dubois",
          role: "Chartered Accountant",
          company: "Dubois & Associates",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Guaranteed compliance. We sleep well!",
          author: "Nathalie Rousseau",
          role: "Director",
          company: "Rousseau Group",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Frequently Asked Questions",
      subtitle: "Find the answers to your questions",
      items: [
        {
          question: "Do you support multiple currencies?",
          answer: "Yes, we support more than 150 currencies with automatic conversion and up-to-date rates.",
        },
        {
          question: "How does compliance work?",
          answer: "Our rates and rules are updated automatically according to local regulations. You are always compliant.",
        },
        {
          question: "Can you export to my accounting software?",
          answer: "Yes, we export directly to popular accounting software (Sage, Ciel, etc.).",
        },
        {
          question: "How do advances work?",
          answer: "Employees request an advance through the app. Managers approve. The deduction is automatic on the next payslip.",
        },
        {
          question: "How long does setup take?",
          answer: "Setup takes less than 5 minutes. You can generate your first payroll immediately.",
        },
        {
          question: "Can you manage multiple entities?",
          answer: "Yes, you can manage multiple entities with different configurations from a single dashboard.",
        },
      ],
    },
    cta: {
      headline: "Automate your payroll today",
      subheadline: "14-day free trial, no commitment",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "Request a demo",
        href: "/demo?module=comptabilite",
      },
    },
  },
  marketing: {
    sections: {
      heroBadge: "Complete Marketing Suite",
      problemBadge: "The Challenges",
      solutionBadge: "Our Solution",
      featuresTitle: "Detailed Features",
      featuresSubtitle: "Everything you need",
      featuresBadge: "Powerful & Flexible",
    },
    hero: {
      headline: "Integrated Marketing Tools for SMEs",
      subheadline: "Email, SMS, social networks in one place",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "See the demo",
        href: "/demo?module=marketing",
      },
    },
    problem: {
      title: "Scattered and expensive marketing tools?",
      subtitle: "The challenges of fragmented marketing management",
      items: [
        {
          title: "Multiple Tools",
          description: "Email, SMS, social networks - each in a different tool",
        },
        {
          title: "High Costs",
          description: "Multiple subscriptions, separate invoices, uncontrolled budget",
        },
        {
          title: "No Overview",
          description: "Impossible to see overall campaign performance",
        },
        {
          title: "Complex Integration",
          description: "No link with your HR data, manual segmentation",
        },
      ],
    },
    solution: {
      title: "Complete Integrated Marketing",
      subtitle: "All your marketing tools in one place",
      description: "Leopardo offers email, SMS, social networks and analytics integrated with your HR platform.",
      features: [
        {
          title: "Email Marketing",
          description: "Templates, segmentation, automation, A/B testing",
        },
        {
          title: "SMS Marketing",
          description: "Targeted campaigns, tracking, HR integration",
        },
        {
          title: "Social Networks",
          description: "Automatic sharing, scheduling, analytics",
        },
        {
          title: "Centralized Analytics",
          description: "ROI, engagement, conversions - all in one place",
        },
      ],
    },
    caseStudies: {
      title: "Real-World Use Cases",
      subtitle: "How our clients use marketing",
      items: [
        {
          title: "Recruitment: Targeted Campaigns",
          description: "Targeted and automated recruitment campaigns",
          industry: "HR",
          metrics: [
            {
              label: "Applications",
              value: "3x+",
            },
            {
              label: "Cost per applicant",
              value: "-40%",
            },
            {
              label: "Response rate",
              value: "35%",
            },
          ],
          link: "/case-studies/recruitment",
        },
        {
          title: "Engagement: Employee Newsletters",
          description: "Internal newsletters to engage employees",
          industry: "Engagement",
          metrics: [
            {
              label: "Open rate",
              value: "45%",
            },
            {
              label: "Clicks",
              value: "15%",
            },
            {
              label: "Satisfaction",
              value: "90%",
            },
          ],
          link: "/case-studies/employee-engagement",
        },
        {
          title: "Promotion: Customer Campaigns",
          description: "Promotional campaigns towards customers",
          industry: "Marketing",
          metrics: [
            {
              label: "Conversion rate",
              value: "8%",
            },
            {
              label: "ROI",
              value: "300%",
            },
            {
              label: "Customers acquired",
              value: "50+",
            },
          ],
          link: "/case-studies/customer-campaigns",
        },
      ],
    },
    testimonials: {
      title: "Client Testimonials",
      subtitle: "What our clients say about marketing",
      items: [
        {
          quote: "More effective and cheaper campaigns. Excellent!",
          author: "Luc Moreau",
          role: "Marketing Manager",
          company: "Tech Marketing",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "45% email open rate. Incredible!",
          author: "Céline Dupont",
          role: "Marketing Lead",
          company: "Growth Co",
          avatar: "/avatars/dupont.svg",
          rating: 5,
        },
        {
          quote: "Perfect HR integration. Automatic segmentation!",
          author: "David Leclerc",
          role: "Marketing Director",
          company: "Digital Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "300% ROI on our campaigns. Recommended!",
          author: "Valérie Rousseau",
          role: "CMO",
          company: "Rousseau Group",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Frequently Asked Questions",
      subtitle: "Find the answers to your questions",
      items: [
        {
          question: "Can you segment by HR data?",
          answer: "Yes, you can segment automatically by department, location, role, etc.",
        },
        {
          question: "Which channels are supported?",
          answer: "Email, SMS and social networks (Facebook, Instagram, LinkedIn, X/Twitter).",
        },
        {
          question: "Can I schedule campaigns in advance?",
          answer: "Yes, plan your campaigns with a shared calendar and automatic sending.",
        },
        {
          question: "Can I track ROI?",
          answer: "Yes, centralized analytics track ROI, engagement and conversions.",
        },
        {
          question: "Is it suitable for agencies?",
          answer: "Yes, agencies manage all client campaigns from a single workspace.",
        },
        {
          question: "How long does setup take?",
          answer: "Connect your channels in under 10 minutes and launch your first campaign.",
        },
      ],
    },
    cta: {
      headline: "Grow with integrated marketing",
      subheadline: "14-day free trial, no commitment",
      ctaPrimary: {
        text: "Free trial",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "Request a demo",
        href: "/demo?module=marketing",
      },
    },
  }

}

const modulePageContentTr: Partial<ModulePageContent> = {
  employes: {
    sections: {
      heroBadge: "Kapsamlı İK Yönetimi",
      problemBadge: "Zorluklar",
      solutionBadge: "Çözümümüz",
      featuresTitle: "Detaylı Özellikler",
      featuresSubtitle: "İhtiyacınız olan her şey",
      featuresBadge: "Güçlü ve Esnek",
    },
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
  documents: {
    sections: {
      heroBadge: "Dijital Belge Merkezi",
      problemBadge: "Zorluklar",
      solutionBadge: "Çözümümüz",
      featuresTitle: "Detaylı Özellikler",
      featuresSubtitle: "İhtiyacınız olan her şey",
      featuresBadge: "Güvenli ve Uyumlu",
    },
    hero: {
      headline: "Belgeleriniz için Güvenli Dijital Kasa",
      subheadline: "Uyumlu depolama, paylaşım ve arşivleme",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "Demoyu gör",
        href: "/demo?module=documents",
      },
    },
    problem: {
      title: "Belgeler dağınık ve güvensiz mi?",
      subtitle: "Merkezi bir çözümün olmamasının riskleri",
      items: [
        {
          title: "Dağınık Belgeler",
          description: "E-postalar, klasörler, USB bellekler - Belgeleri bulmak imkânsız",
        },
        {
          title: "Güvenlik Riskleri",
          description: "Şifrelenmemiş hassas veriler, kontrolsüz erişim",
        },
        {
          title: "Karmaşık Paylaşım",
          description: "E-postayla paylaşım, çoklu sürümler, karışıklık",
        },
        {
          title: "Uyumsuzluk",
          description: "İzlenebilirlik yok, denetim izi yok, GDPR ihlali",
        },
      ],
    },
    solution: {
      title: "Güvenli Bir Dijital Kasa",
      subtitle: "Tüm belgelerinizi merkezileştirin ve güvenceye alın",
      description: "Leopardo; AES-256 şifreleme, ayrıntılı izinler ve GDPR uyumluluğu sunan bir dijital kasa sağlar.",
      features: [
        {
          title: "Güvenli Depolama",
          description: "AES-256 şifreleme, otomatik yedekleme, yedeklilik",
        },
        {
          title: "Kontrollü Paylaşım",
          description: "Ayrıntılı izinler, geçici bağlantılar, denetim izi",
        },
        {
          title: "Otomatik Arşivleme",
          description: "Otomatik saklama, güvenli imha, uyumluluk",
        },
        {
          title: "GDPR Uyumluluğu",
          description: "Yönetmeliklere uyum, sertifikalar, yasal destek",
        },
      ],
    },
    caseStudies: {
      title: "Gerçek Kullanım Örnekleri",
      subtitle: "Müşterilerimiz dijital kasayı nasıl kullanıyor",
      items: [
        {
          title: "Hukuk Bürosu: Müvekkil Dosyaları",
          description: "Gizli müvekkil dosyalarının güvenli yönetimi",
          industry: "Hukuk",
          metrics: [
            {
              label: "Yönetilen dosya",
              value: "1000+",
            },
            {
              label: "Kazanılan süre",
              value: "20sa/ay",
            },
            {
              label: "Uyumluluk",
              value: "100%",
            },
          ],
          link: "/case-studies/law-firm",
        },
        {
          title: "İK: Çalışan Dosyaları",
          description: "Gizli çalışan dosyalarının merkezileştirilmesi",
          industry: "İK",
          metrics: [
            {
              label: "Çalışan",
              value: "500+",
            },
            {
              label: "Güvenlik",
              value: "AES-256",
            },
            {
              label: "Erişim",
              value: "Kontrollü",
            },
          ],
          link: "/case-studies/hr-files",
        },
        {
          title: "Finans: Muhasebe Belgeleri",
          description: "Muhasebe belgelerinin güvenli arşivlenmesi",
          industry: "Finans",
          metrics: [
            {
              label: "Belge",
              value: "10K+",
            },
            {
              label: "Saklama",
              value: "Otomatik",
            },
            {
              label: "Denetim",
              value: "Tam",
            },
          ],
          link: "/case-studies/accounting",
        },
      ],
    },
    testimonials: {
      title: "Müşteri Yorumları",
      subtitle: "Müşterilerimiz dijital kasa hakkında ne diyor",
      items: [
        {
          quote: "Sonunda gizli belgelerimiz için güvenli bir çözüm!",
          author: "Av. Dubois",
          role: "Avukat",
          company: "Dubois & Ortakları Hukuk Bürosu",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Garantili GDPR uyumu ve basit arayüz. Harika!",
          author: "Isabelle Moreau",
          role: "İK Sorumlusu",
          company: "Moreau Grubu",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "Müşterilerimizle güvenli paylaşım. Çok profesyonel.",
          author: "Thomas Lefevre",
          role: "Finans Direktörü",
          company: "Finance Solutions",
          avatar: "/avatars/lefevre.svg",
          rating: 5,
        },
        {
          quote: "Mükemmel destek ve hızlı kurulum. Tavsiye ederim!",
          author: "Claire Rousseau",
          role: "Yönetici",
          company: "Consulting Pro",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Sıkça Sorulan Sorular",
      subtitle: "Sorularınızın yanıtlarını bulun",
      items: [
        {
          question: "Şifreleme düzeyi nedir?",
          answer: "Maksimum güvenlik için sektör standardı olan AES-256 şifrelemesini kullanıyoruz.",
        },
        {
          question: "Paylaşım nasıl çalışır?",
          answer: "Belgeleri ayrıntılı izinlerle (okuma, düzenleme, silme) paylaşabilirsiniz. Bağlantılar geçici olabilir.",
        },
        {
          question: "Depolama alanı ne kadar?",
          answer: "Alan planınıza bağlıdır. Pilot: 100GB, Operations: 1TB, Enterprise: Sınırsız.",
        },
        {
          question: "Arşivleme nasıl çalışır?",
          answer: "Saklama kurallarını siz belirlersiniz. Belgeler ayarlarınıza göre otomatik arşivlenir veya silinir.",
        },
        {
          question: "GDPR uyumluluğunu garanti edebilir misiniz?",
          answer: "Evet, GDPR sertifikalıyız ve uyumunuzu sağlamak için yasal destek sunuyoruz.",
        },
        {
          question: "Kurulum ne kadar sürer?",
          answer: "Kurulum 5 dakikadan kısadır. Hemen belge yüklemeye başlayabilirsiniz.",
        },
      ],
    },
    cta: {
      headline: "Belgelerinizi şimdi güvenceye alın",
      subheadline: "14 gün ücretsiz deneme, taahhüt yok",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "Demo iste",
        href: "/demo?module=documents",
      },
    },
  },
  comptabilite: {
    sections: {
      heroBadge: "Otomatik Bordro",
      problemBadge: "Zorluklar",
      solutionBadge: "Çözümümüz",
      featuresTitle: "Detaylı Özellikler",
      featuresSubtitle: "İhtiyacınız olan her şey",
      featuresBadge: "Eksiksiz ve Güvenilir",
    },
    hero: {
      headline: "Garantili Uyumla Otomatik Maaş Bordrosu",
      subheadline: "Doğru hesaplamalar, oluşturulan bordrolar, muhasebe dışa aktarımları",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "Demoyu gör",
        href: "/demo?module=comptabilite",
      },
    },
    problem: {
      title: "Manuel bordro = Hatalar ve zaman kaybı",
      subtitle: "Geleneksel bordro yönetiminin zorlukları",
      items: [
        {
          title: "Manuel Hesaplamalar",
          description: "Hatalar, eksiklikler, karmaşık hesaplamalar, kayıp zaman",
        },
        {
          title: "Karmaşık Uyumluluk",
          description: "Değişen oranlar, yönetmelikler, zor güncellemeler",
        },
        {
          title: "Muhasebe Dışa Aktarımları",
          description: "Farklı biçimler, manuel entegrasyonlar, hatalar",
        },
        {
          title: "Maaş Avansları",
          description: "Manuel talepler, karmaşık hesaplamalar, unutulan kesintiler",
        },
      ],
    },
    solution: {
      title: "Eksiksiz Otomatik Bordro",
      subtitle: "Bordro yönetiminizin tam otomasyonu",
      description: "Leopardo tüm bordro hesaplamalarını otomatikleştirir, bordroları oluşturur ve doğrudan muhasebenize aktarır.",
      features: [
        {
          title: "Otomatik Hesaplama",
          description: "Çoklu para birimi, otomatik primler, güncel oranlar",
        },
        {
          title: "Oluşturulan Bordrolar",
          description: "Otomatik PDF'ler, e-posta gönderimi, güvenli arşivleme",
        },
        {
          title: "Muhasebe Dışa Aktarımları",
          description: "Standart biçimler, doğrudan entegrasyon, hatasız",
        },
        {
          title: "Otomatik Avanslar",
          description: "Talepler, onay, otomatik kesinti",
        },
      ],
    },
    caseStudies: {
      title: "Gerçek Kullanım Örnekleri",
      subtitle: "Müşterilerimiz bordroyu nasıl yönetiyor",
      items: [
        {
          title: "KOBİ: 50 Çalışan",
          description: "Büyüyen bir KOBİ için otomatik aylık bordro",
          industry: "KOBİ",
          metrics: [
            {
              label: "Kazanılan süre",
              value: "8sa/ay",
            },
            {
              label: "Hatalar",
              value: "0",
            },
            {
              label: "Memnuniyet",
              value: "100%",
            },
          ],
          link: "/case-studies/sme-payroll",
        },
        {
          title: "Startup: Otomatik Avanslar",
          description: "Bir startup için maaş avansı yönetimi",
          industry: "Startup",
          metrics: [
            {
              label: "Avans/ay",
              value: "20+",
            },
            {
              label: "İşlem",
              value: "Otomatik",
            },
            {
              label: "Hatalar",
              value: "0",
            },
          ],
          link: "/case-studies/startup-advances",
        },
        {
          title: "Grup: Çoklu Kuruluş",
          description: "Bir grup için çoklu kuruluş ve çoklu para birimi bordrosu",
          industry: "Grup",
          metrics: [
            {
              label: "Kuruluş",
              value: "5",
            },
            {
              label: "Para birimi",
              value: "3",
            },
            {
              label: "Çalışan",
              value: "500+",
            },
          ],
          link: "/case-studies/group-payroll",
        },
      ],
    },
    testimonials: {
      title: "Müşteri Yorumları",
      subtitle: "Müşterilerimiz otomatik bordro hakkında ne diyor",
      items: [
        {
          quote: "2 yıldır sıfır hata. İnanılmaz!",
          author: "Jean Martin",
          role: "Muhasebeci",
          company: "Finance Pro",
          avatar: "/avatars/martin.svg",
          rating: 5,
        },
        {
          quote: "Excel'den 3 kat hızlı. Ayda 10 saat tasarruf ediyoruz!",
          author: "Sophie Leclerc",
          role: "İK Yöneticisi",
          company: "Tech Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "Doğrudan muhasebe dışa aktarımları. Manuel işlem yok!",
          author: "Marc Dubois",
          role: "Yeminli Mali Müşavir",
          company: "Dubois & Ortakları",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "Garantili uyumluluk. Rahat uyuyoruz!",
          author: "Nathalie Rousseau",
          role: "Yönetici",
          company: "Rousseau Grubu",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Sıkça Sorulan Sorular",
      subtitle: "Sorularınızın yanıtlarını bulun",
      items: [
        {
          question: "Birden fazla para birimini destekliyor musunuz?",
          answer: "Evet, otomatik dönüştürme ve güncel oranlarla 150'den fazla para birimini destekliyoruz.",
        },
        {
          question: "Uyumluluk nasıl çalışır?",
          answer: "Oranlarımız ve kurallarımız yerel yönetmeliklere göre otomatik güncellenir. Her zaman uyumlusunuz.",
        },
        {
          question: "Muhasebe yazılımıma aktarım yapabilir misiniz?",
          answer: "Evet, popüler muhasebe yazılımlarına (Sage, Ciel vb.) doğrudan aktarım yapıyoruz.",
        },
        {
          question: "Avanslar nasıl çalışır?",
          answer: "Çalışanlar uygulama üzerinden avans talep eder. Yöneticiler onaylar. Kesinti sonraki bordroda otomatik yapılır.",
        },
        {
          question: "Kurulum ne kadar sürer?",
          answer: "Kurulum 5 dakikadan kısadır. İlk bordronuzu hemen oluşturabilirsiniz.",
        },
        {
          question: "Birden fazla kuruluşu yönetebilir misiniz?",
          answer: "Evet, tek bir panelden farklı yapılandırmalara sahip birden fazla kuruluşu yönetebilirsiniz.",
        },
      ],
    },
    cta: {
      headline: "Bordronuzu bugün otomatikleştirin",
      subheadline: "14 gün ücretsiz deneme, taahhüt yok",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "Demo iste",
        href: "/demo?module=comptabilite",
      },
    },
  },
  marketing: {
    sections: {
      heroBadge: "Kapsamlı Pazarlama Paketi",
      problemBadge: "Zorluklar",
      solutionBadge: "Çözümümüz",
      featuresTitle: "Detaylı Özellikler",
      featuresSubtitle: "İhtiyacınız olan her şey",
      featuresBadge: "Güçlü ve Esnek",
    },
    hero: {
      headline: "KOBİ'ler için Entegre Pazarlama Araçları",
      subheadline: "E-posta, SMS, sosyal ağlar tek yerde",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "Demoyu gör",
        href: "/demo?module=marketing",
      },
    },
    problem: {
      title: "Dağınık ve pahalı pazarlama araçları mı?",
      subtitle: "Parçalanmış pazarlama yönetiminin zorlukları",
      items: [
        {
          title: "Çoklu Araçlar",
          description: "E-posta, SMS, sosyal ağlar - her biri farklı bir araçta",
        },
        {
          title: "Yüksek Maliyetler",
          description: "Çoklu abonelikler, ayrı faturalar, kontrolsüz bütçe",
        },
        {
          title: "Genel Bakış Yok",
          description: "Kampanya performansını bir arada görmek imkânsız",
        },
        {
          title: "Karmaşık Entegrasyon",
          description: "İK verilerinizle bağlantı yok, manuel segmentasyon",
        },
      ],
    },
    solution: {
      title: "Eksiksiz Entegre Pazarlama",
      subtitle: "Tüm pazarlama araçlarınız tek yerde",
      description: "Leopardo; İK platformunuza entegre e-posta, SMS, sosyal ağlar ve analitik sunar.",
      features: [
        {
          title: "E-posta Pazarlama",
          description: "Şablonlar, segmentasyon, otomasyon, A/B testi",
        },
        {
          title: "SMS Pazarlama",
          description: "Hedefli kampanyalar, takip, İK entegrasyonu",
        },
        {
          title: "Sosyal Ağlar",
          description: "Otomatik paylaşım, planlama, analitik",
        },
        {
          title: "Merkezi Analitik",
          description: "ROI, etkileşim, dönüşümler - hepsi tek yerde",
        },
      ],
    },
    caseStudies: {
      title: "Gerçek Kullanım Örnekleri",
      subtitle: "Müşterilerimiz pazarlamayı nasıl kullanıyor",
      items: [
        {
          title: "İşe Alım: Hedefli Kampanyalar",
          description: "Hedefli ve otomatik işe alım kampanyaları",
          industry: "İK",
          metrics: [
            {
              label: "Başvuru",
              value: "3x+",
            },
            {
              label: "Aday başına maliyet",
              value: "-40%",
            },
            {
              label: "Yanıt oranı",
              value: "35%",
            },
          ],
          link: "/case-studies/recruitment",
        },
        {
          title: "Bağlılık: Çalışan Bültenleri",
          description: "Çalışanları bağlamak için iç bültenler",
          industry: "Bağlılık",
          metrics: [
            {
              label: "Açılma oranı",
              value: "45%",
            },
            {
              label: "Tıklamalar",
              value: "15%",
            },
            {
              label: "Memnuniyet",
              value: "90%",
            },
          ],
          link: "/case-studies/employee-engagement",
        },
        {
          title: "Tanıtım: Müşteri Kampanyaları",
          description: "Müşterilere yönelik tanıtım kampanyaları",
          industry: "Pazarlama",
          metrics: [
            {
              label: "Dönüşüm oranı",
              value: "8%",
            },
            {
              label: "ROI",
              value: "300%",
            },
            {
              label: "Kazanılan müşteri",
              value: "50+",
            },
          ],
          link: "/case-studies/customer-campaigns",
        },
      ],
    },
    testimonials: {
      title: "Müşteri Yorumları",
      subtitle: "Müşterilerimiz pazarlama hakkında ne diyor",
      items: [
        {
          quote: "Daha etkili ve daha ucuz kampanyalar. Mükemmel!",
          author: "Luc Moreau",
          role: "Pazarlama Müdürü",
          company: "Tech Marketing",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "%45 e-posta açılma oranı. İnanılmaz!",
          author: "Céline Dupont",
          role: "Pazarlama Lideri",
          company: "Growth Co",
          avatar: "/avatars/dupont.svg",
          rating: 5,
        },
        {
          quote: "Mükemmel İK entegrasyonu. Otomatik segmentasyon!",
          author: "David Leclerc",
          role: "Pazarlama Direktörü",
          company: "Digital Solutions",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "Kampanyalarımızda %300 ROI. Tavsiye ederim!",
          author: "Valérie Rousseau",
          role: "CMO",
          company: "Rousseau Grubu",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "Sıkça Sorulan Sorular",
      subtitle: "Sorularınızın yanıtlarını bulun",
      items: [
        {
          question: "İK verilerine göre segmentasyon yapabilir misiniz?",
          answer: "Evet, departman, konum, rol vb. ölçütlere göre otomatik segmentasyon yapabilirsiniz.",
        },
        {
          question: "Hangi kanallar destekleniyor?",
          answer: "E-posta, SMS ve sosyal ağlar (Facebook, Instagram, LinkedIn, X/Twitter).",
        },
        {
          question: "Kampanyaları önceden planlayabilir miyim?",
          answer: "Evet, ortak bir takvimle kampanyalarınızı planlayın ve otomatik gönderin.",
        },
        {
          question: "ROI'yi takip edebilir miyim?",
          answer: "Evet, merkezi analitik ROI, etkileşim ve dönüşümleri takip eder.",
        },
        {
          question: "Ajanslar için uygun mu?",
          answer: "Evet, ajanslar tüm müşteri kampanyalarını tek bir çalışma alanından yönetir.",
        },
        {
          question: "Kurulum ne kadar sürer?",
          answer: "Kanallarınızı 10 dakikadan kısa sürede bağlayın ve ilk kampanyanızı başlatın.",
        },
      ],
    },
    cta: {
      headline: "Entegre pazarlamayla büyüyün",
      subheadline: "14 gün ücretsiz deneme, taahhüt yok",
      ctaPrimary: {
        text: "Ücretsiz deneme",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "Demo iste",
        href: "/demo?module=marketing",
      },
    },
  }

}

const modulePageContentAr: Partial<ModulePageContent> = {
  employes: {
    sections: {
      heroBadge: "إدارة موارد بشرية شاملة",
      problemBadge: "التحديات",
      solutionBadge: "حلّنا",
      featuresTitle: "الميزات التفصيلية",
      featuresSubtitle: "كل ما تحتاجه",
      featuresBadge: "قوي ومرن",
    },
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
  documents: {
    sections: {
      heroBadge: "مركز المستندات الرقمي",
      problemBadge: "التحديات",
      solutionBadge: "حلّنا",
      featuresTitle: "الميزات التفصيلية",
      featuresSubtitle: "كل ما تحتاجه",
      featuresBadge: "آمن ومتوافق",
    },
    hero: {
      headline: "خزانة رقمية آمنة لمستنداتك",
      subheadline: "تخزين ومشاركة وأرشفة متوافقة",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "عرض العرض التوضيحي",
        href: "/demo?module=documents",
      },
    },
    problem: {
      title: "مستندات مشتتة وغير آمنة؟",
      subtitle: "مخاطر عدم وجود حل مركزي",
      items: [
        {
          title: "مستندات مشتتة",
          description: "رسائل بريد، مجلدات، أقراص USB - استحالة العثور على المستندات",
        },
        {
          title: "مخاطر أمنية",
          description: "بيانات حساسة غير مشفرة، وصول غير مراقب",
        },
        {
          title: "مشاركة معقدة",
          description: "المشاركة عبر البريد، إصدارات متعددة، ارتباك",
        },
        {
          title: "عدم الامتثال",
          description: "لا تتبّع، لا سجل تدقيق، انتهاك للائحة GDPR",
        },
      ],
    },
    solution: {
      title: "خزانة رقمية آمنة",
      subtitle: "مركزية وتأمين جميع مستنداتك",
      description: "توفر Leopardo خزانة رقمية بتشفير AES-256 وصلاحيات دقيقة وامتثال للائحة GDPR.",
      features: [
        {
          title: "تخزين آمن",
          description: "تشفير AES-256، نسخ احتياطي تلقائي، تكرار",
        },
        {
          title: "مشاركة محكومة",
          description: "صلاحيات دقيقة، روابط مؤقتة، سجل تدقيق",
        },
        {
          title: "أرشفة تلقائية",
          description: "احتفاظ تلقائي، إتلاف آمن، امتثال",
        },
        {
          title: "الامتثال للائحة GDPR",
          description: "الالتزام باللوائح، شهادات، دعم قانوني",
        },
      ],
    },
    caseStudies: {
      title: "حالات استخدام حقيقية",
      subtitle: "كيف يستخدم عملاؤنا الخزانة الرقمية",
      items: [
        {
          title: "مكتب محاماة: ملفات العملاء",
          description: "إدارة آمنة لملفات العملاء السرية",
          industry: "قانوني",
          metrics: [
            {
              label: "ملفات مُدارة",
              value: "1000+",
            },
            {
              label: "وقت موفر",
              value: "20س/شهر",
            },
            {
              label: "امتثال",
              value: "100%",
            },
          ],
          link: "/case-studies/law-firm",
        },
        {
          title: "الموارد البشرية: ملفات الموظفين",
          description: "مركزية ملفات الموظفين السرية",
          industry: "موارد بشرية",
          metrics: [
            {
              label: "موظفون",
              value: "500+",
            },
            {
              label: "الأمان",
              value: "AES-256",
            },
            {
              label: "الوصول",
              value: "محكوم",
            },
          ],
          link: "/case-studies/hr-files",
        },
        {
          title: "المالية: مستندات محاسبية",
          description: "أرشفة آمنة للمستندات المحاسبية",
          industry: "مالية",
          metrics: [
            {
              label: "مستندات",
              value: "10K+",
            },
            {
              label: "الاحتفاظ",
              value: "تلقائي",
            },
            {
              label: "التدقيق",
              value: "كامل",
            },
          ],
          link: "/case-studies/accounting",
        },
      ],
    },
    testimonials: {
      title: "آراء العملاء",
      subtitle: "ماذا يقول عملاؤنا عن الخزانة الرقمية",
      items: [
        {
          quote: "أخيراً حل آمن لمستنداتنا السرية!",
          author: "الأستاذ دوبوا",
          role: "محامٍ",
          company: "مكتب دوبوا وشركاه",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "امتثال مضمون للائحة GDPR وواجهة بسيطة. ممتاز!",
          author: "إيزابيل مورو",
          role: "مسؤولة موارد بشرية",
          company: "مجموعة مورو",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "مشاركة آمنة مع عملائنا. احترافية عالية.",
          author: "توماس لوفيفر",
          role: "مدير مالي",
          company: "حلول المالية",
          avatar: "/avatars/lefevre.svg",
          rating: 5,
        },
        {
          quote: "دعم ممتاز وإعداد سريع. أنصح به!",
          author: "كلير روسو",
          role: "مديرة",
          company: "برو للاستشارات",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "الأسئلة الشائعة",
      subtitle: "اعثر على إجابات لأسئلتك",
      items: [
        {
          question: "ما مستوى التشفير؟",
          answer: "نستخدم تشفير AES-256، المعيار الصناعي لأقصى درجات الأمان.",
        },
        {
          question: "كيف تعمل المشاركة؟",
          answer: "يمكنك مشاركة المستندات بصلاحيات دقيقة (قراءة، تعديل، حذف). يمكن أن تكون الروابط مؤقتة.",
        },
        {
          question: "كم مساحة التخزين؟",
          answer: "تعتمد المساحة على باقتك. Pilot: 100GB، Operations: 1TB، Enterprise: غير محدود.",
        },
        {
          question: "كيف تعمل الأرشفة؟",
          answer: "تحدد قواعد الاحتفاظ. تُؤرشف المستندات أو تُحذف تلقائياً وفق إعداداتك.",
        },
        {
          question: "هل تضمنون الامتثال للائحة GDPR؟",
          answer: "نعم، نحن معتمدون وفق GDPR ونقدم دعماً قانونياً لضمان امتثالك.",
        },
        {
          question: "كم يستغرق الإعداد؟",
          answer: "يستغرق الإعداد أقل من 5 دقائق. يمكنك البدء برفع المستندات فوراً.",
        },
      ],
    },
    cta: {
      headline: "أمّن مستنداتك الآن",
      subheadline: "تجربة مجانية 14 يوماً دون التزام",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=documents",
      },
      ctaSecondary: {
        text: "اطلب عرضاً",
        href: "/demo?module=documents",
      },
    },
  },
  comptabilite: {
    sections: {
      heroBadge: "كشوف رواتب آلية",
      problemBadge: "التحديات",
      solutionBadge: "حلّنا",
      featuresTitle: "الميزات التفصيلية",
      featuresSubtitle: "كل ما تحتاجه",
      featuresBadge: "كامل وموثوق",
    },
    hero: {
      headline: "رواتب آلية مع امتثال مضمون",
      subheadline: "حسابات دقيقة، قسائم رواتب مولّدة، تصديرات محاسبية",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "عرض العرض التوضيحي",
        href: "/demo?module=comptabilite",
      },
    },
    problem: {
      title: "الرواتب اليدوية = أخطاء ووقت ضائع",
      subtitle: "تحديات إدارة الرواتب التقليدية",
      items: [
        {
          title: "حسابات يدوية",
          description: "أخطاء، سهو، حسابات معقدة، وقت ضائع",
        },
        {
          title: "امتثال معقد",
          description: "معدلات متغيرة، لوائح، تحديثات صعبة",
        },
        {
          title: "تصديرات محاسبية",
          description: "صيغ مختلفة، تكاملات يدوية، أخطاء",
        },
        {
          title: "سلف الرواتب",
          description: "طلبات يدوية، حسابات معقدة، خصومات منسية",
        },
      ],
    },
    solution: {
      title: "رواتب آلية متكاملة",
      subtitle: "أتمتة كاملة لإدارة رواتبك",
      description: "تؤتمت Leopardo جميع حسابات الرواتب، وتولّد قسائم الدفع وتصدّر مباشرة إلى محاسبتك.",
      features: [
        {
          title: "حساب تلقائي",
          description: "تعدد العملات، اشتراكات تلقائية، معدلات محدثة",
        },
        {
          title: "قسائم مولّدة",
          description: "PDF تلقائية، إرسال بالبريد، أرشفة آمنة",
        },
        {
          title: "تصديرات محاسبية",
          description: "صيغ قياسية، تكامل مباشر، بلا أخطاء",
        },
        {
          title: "سلف آلية",
          description: "طلبات، موافقات، خصم تلقائي",
        },
      ],
    },
    caseStudies: {
      title: "حالات استخدام حقيقية",
      subtitle: "كيف يدير عملاؤنا رواتبهم",
      items: [
        {
          title: "شركة صغيرة: 50 موظفاً",
          description: "رواتب شهرية آلية لشركة صغيرة في نمو",
          industry: "شركة صغيرة",
          metrics: [
            {
              label: "وقت موفر",
              value: "8س/شهر",
            },
            {
              label: "أخطاء",
              value: "0",
            },
            {
              label: "رضا",
              value: "100%",
            },
          ],
          link: "/case-studies/sme-payroll",
        },
        {
          title: "ناشئة: سلف آلية",
          description: "إدارة سلف الرواتب لشركة ناشئة",
          industry: "ناشئة",
          metrics: [
            {
              label: "سلف/شهر",
              value: "20+",
            },
            {
              label: "المعالجة",
              value: "تلقائية",
            },
            {
              label: "أخطاء",
              value: "0",
            },
          ],
          link: "/case-studies/startup-advances",
        },
        {
          title: "مجموعة: كيانات متعددة",
          description: "رواتب متعددة الكيانات والعملات لمجموعة",
          industry: "مجموعة",
          metrics: [
            {
              label: "كيانات",
              value: "5",
            },
            {
              label: "عملات",
              value: "3",
            },
            {
              label: "موظفون",
              value: "500+",
            },
          ],
          link: "/case-studies/group-payroll",
        },
      ],
    },
    testimonials: {
      title: "آراء العملاء",
      subtitle: "ماذا يقول عملاؤنا عن الرواتب الآلية",
      items: [
        {
          quote: "صفر أخطاء منذ عامين. مذهل!",
          author: "جان مارتان",
          role: "محاسب",
          company: "المالية برو",
          avatar: "/avatars/martin.svg",
          rating: 5,
        },
        {
          quote: "أسرع 3 مرات من Excel. نوفر 10 ساعات شهرياً!",
          author: "صوفي لوكلير",
          role: "مديرة موارد بشرية",
          company: "حلول التقنية",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "تصديرات محاسبية مباشرة. لا معالجة يدوية!",
          author: "مارك دوبوا",
          role: "خبير محاسبة",
          company: "دوبوا وشركاه",
          avatar: "/avatars/dubois.svg",
          rating: 5,
        },
        {
          quote: "امتثال مضمون. ننام باطمئنان!",
          author: "ناتالي روسو",
          role: "مديرة",
          company: "مجموعة روسو",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "الأسئلة الشائعة",
      subtitle: "اعثر على إجابات لأسئلتك",
      items: [
        {
          question: "هل تدعمون عملات متعددة؟",
          answer: "نعم، ندعم أكثر من 150 عملة مع تحويل تلقائي ومعدلات محدثة.",
        },
        {
          question: "كيف يعمل الامتثال؟",
          answer: "يتم تحديث معدلاتنا وقواعدنا تلقائياً وفق اللوائح المحلية. أنت متوافق دائماً.",
        },
        {
          question: "هل يمكنكم التصدير إلى برنامج المحاسبة الخاص بي؟",
          answer: "نعم، نصدّر مباشرة إلى برامج المحاسبة الشهيرة (Sage، Ciel، وغيرها).",
        },
        {
          question: "كيف تعمل السلف؟",
          answer: "يطلب الموظفون سلفة عبر التطبيق. يوافق المديرون. يكون الخصم تلقائياً في قسيمة الرواتب التالية.",
        },
        {
          question: "كم يستغرق الإعداد؟",
          answer: "يستغرق الإعداد أقل من 5 دقائق. يمكنك توليد أول رواتبك فوراً.",
        },
        {
          question: "هل يمكنكم إدارة كيانات متعددة؟",
          answer: "نعم، يمكنك إدارة كيانات متعددة بإعدادات مختلفة من لوحة تحكم واحدة.",
        },
      ],
    },
    cta: {
      headline: "أتمت رواتبك اليوم",
      subheadline: "تجربة مجانية 14 يوماً دون التزام",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=comptabilite",
      },
      ctaSecondary: {
        text: "اطلب عرضاً",
        href: "/demo?module=comptabilite",
      },
    },
  },
  marketing: {
    sections: {
      heroBadge: "حزمة تسويق شاملة",
      problemBadge: "التحديات",
      solutionBadge: "حلّنا",
      featuresTitle: "الميزات التفصيلية",
      featuresSubtitle: "كل ما تحتاجه",
      featuresBadge: "قوي ومرن",
    },
    hero: {
      headline: "أدوات تسويق متكاملة للشركات الصغيرة",
      subheadline: "البريد، الرسائل القصيرة، شبكات التواصل في مكان واحد",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "عرض العرض التوضيحي",
        href: "/demo?module=marketing",
      },
    },
    problem: {
      title: "أدوات تسويق مشتتة ومكلفة؟",
      subtitle: "تحديات إدارة التسويق المجزأة",
      items: [
        {
          title: "أدوات متعددة",
          description: "البريد، الرسائل القصيرة، شبكات التواصل - كلٌّ في أداة مختلفة",
        },
        {
          title: "تكاليف مرتفعة",
          description: "اشتراكات متعددة، فواتير منفصلة، ميزانية غير مضبوطة",
        },
        {
          title: "لا نظرة شاملة",
          description: "استحالة رؤية الأداء الكلي للحملات",
        },
        {
          title: "تكامل معقد",
          description: "لا ربط مع بيانات الموارد البشرية، تقسيم يدوي",
        },
      ],
    },
    solution: {
      title: "تسويق متكامل شامل",
      subtitle: "جميع أدواتك التسويقية في مكان واحد",
      description: "توفر Leopardo البريد الإلكتروني والرسائل القصيرة وشبكات التواصل والتحليلات مدمجة مع منصة الموارد البشرية.",
      features: [
        {
          title: "التسويق بالبريد",
          description: "قوالب، تقسيم، أتمتة، اختبار A/B",
        },
        {
          title: "التسويق بالرسائل القصيرة",
          description: "حملات مستهدفة، تتبّع، تكامل مع الموارد البشرية",
        },
        {
          title: "شبكات التواصل",
          description: "مشاركة تلقائية، جدولة، تحليلات",
        },
        {
          title: "تحليلات مركزية",
          description: "العائد، التفاعل، التحويلات - كل ذلك في مكان واحد",
        },
      ],
    },
    caseStudies: {
      title: "حالات استخدام حقيقية",
      subtitle: "كيف يستخدم عملاؤنا التسويق",
      items: [
        {
          title: "التوظيف: حملات مستهدفة",
          description: "حملات توظيف مستهدفة ومؤتمتة",
          industry: "موارد بشرية",
          metrics: [
            {
              label: "طلبات",
              value: "3x+",
            },
            {
              label: "تكلفة المرشح",
              value: "-40%",
            },
            {
              label: "معدل الاستجابة",
              value: "35%",
            },
          ],
          link: "/case-studies/recruitment",
        },
        {
          title: "التفاعل: نشرات الموظفين",
          description: "نشرات داخلية لتفاعل الموظفين",
          industry: "تفاعل",
          metrics: [
            {
              label: "معدل الفتح",
              value: "45%",
            },
            {
              label: "نقرات",
              value: "15%",
            },
            {
              label: "رضا",
              value: "90%",
            },
          ],
          link: "/case-studies/employee-engagement",
        },
        {
          title: "الترويج: حملات العملاء",
          description: "حملات ترويجية موجهة للعملاء",
          industry: "تسويق",
          metrics: [
            {
              label: "معدل التحويل",
              value: "8%",
            },
            {
              label: "العائد",
              value: "300%",
            },
            {
              label: "عملاء مكتسبون",
              value: "50+",
            },
          ],
          link: "/case-studies/customer-campaigns",
        },
      ],
    },
    testimonials: {
      title: "آراء العملاء",
      subtitle: "ماذا يقول عملاؤنا عن التسويق",
      items: [
        {
          quote: "حملات أكثر فعالية وأقل تكلفة. ممتاز!",
          author: "لوك مورو",
          role: "مدير تسويق",
          company: "تسويق التقنية",
          avatar: "/avatars/moreau.svg",
          rating: 5,
        },
        {
          quote: "معدل فتح البريد 45%. مذهل!",
          author: "سيلين دوبون",
          role: "مسؤولة تسويق",
          company: "غروث كو",
          avatar: "/avatars/dupont.svg",
          rating: 5,
        },
        {
          quote: "تكامل مثالي مع الموارد البشرية. تقسيم تلقائي!",
          author: "دافيد لوكلير",
          role: "مدير تسويق",
          company: "حلول رقمية",
          avatar: "/avatars/leclerc.svg",
          rating: 5,
        },
        {
          quote: "عائد 300% على حملاتنا. أنصح به!",
          author: "فاليري روسو",
          role: "مديرة تسويق أولى",
          company: "مجموعة روسو",
          avatar: "/avatars/rousseau.svg",
          rating: 5,
        },
      ],
    },
    faq: {
      title: "الأسئلة الشائعة",
      subtitle: "اعثر على إجابات لأسئلتك",
      items: [
        {
          question: "هل يمكنكم التقسيم حسب بيانات الموارد البشرية؟",
          answer: "نعم، يمكنك التقسيم تلقائياً حسب القسم والموقع والدور وغيرها.",
        },
        {
          question: "ما القنوات المدعومة؟",
          answer: "البريد الإلكتروني والرسائل القصيرة وشبكات التواصل (فيسبوك، إنستغرام، لينكدإن، إكس/تويتر).",
        },
        {
          question: "هل يمكنني جدولة الحملات مسبقاً؟",
          answer: "نعم، خطط حملاتك بتقويم مشترك وأرسل تلقائياً.",
        },
        {
          question: "هل يمكنني تتبع العائد؟",
          answer: "نعم، تتبع التحليلات المركزية العائد والتفاعل والتحويلات.",
        },
        {
          question: "هل تناسب الوكالات؟",
          answer: "نعم، تدير الوكالات جميع حملات العملاء من مساحة عمل واحدة.",
        },
        {
          question: "كم يستغرق الإعداد؟",
          answer: "اربط قنواتك في أقل من 10 دقائق وأطلق أول حملة.",
        },
      ],
    },
    cta: {
      headline: "انمُ بتسويق متكامل",
      subheadline: "تجربة مجانية 14 يوماً دون التزام",
      ctaPrimary: {
        text: "تجربة مجانية",
        href: "/signup?module=marketing",
      },
      ctaSecondary: {
        text: "اطلب عرضاً",
        href: "/demo?module=marketing",
      },
    },
  }

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
