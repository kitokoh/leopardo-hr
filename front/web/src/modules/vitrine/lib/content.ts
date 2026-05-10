/**
 * Module page content for the vitrine
 */

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
        {
          quote: "Enfin une solution qui grandit avec nous. Scalabilité impressionnante.",
          author: "Pierre Leclerc",
          role: "Fondateur",
          company: "Growth Ventures",
          avatar: "/avatars/pierre.jpg",
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
          avatar: "/avatars/dubois.jpg",
          rating: 5,
        },
        {
          quote: "Conformité RGPD garantie et interface simple. Parfait!",
          author: "Isabelle Moreau",
          role: "Responsable RH",
          company: "Groupe Moreau",
          avatar: "/avatars/moreau.jpg",
          rating: 5,
        },
        {
          quote: "Partage sécurisé avec nos clients. Très professionnel.",
          author: "Thomas Lefevre",
          role: "Directeur Financier",
          company: "Finance Solutions",
          avatar: "/avatars/lefevre.jpg",
          rating: 5,
        },
        {
          quote: "Support excellent et mise en place rapide. Recommandé!",
          author: "Claire Rousseau",
          role: "Manager",
          company: "Consulting Pro",
          avatar: "/avatars/rousseau.jpg",
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
          answer: "L'espace dépend de votre plan. Starter: 100GB, Business: 1TB, Enterprise: Illimité.",
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
          avatar: "/avatars/martin.jpg",
          rating: 5,
        },
        {
          quote: "3x plus rapide qu'Excel. Nous avons économisé 10h par mois!",
          author: "Sophie Leclerc",
          role: "Manager RH",
          company: "Tech Solutions",
          avatar: "/avatars/leclerc.jpg",
          rating: 5,
        },
        {
          quote: "Exports comptables directs. Pas de manipulation manuelle!",
          author: "Marc Dubois",
          role: "Expert-Comptable",
          company: "Dubois & Associés",
          avatar: "/avatars/dubois.jpg",
          rating: 5,
        },
        {
          quote: "Conformité garantie. Nous dormons tranquilles!",
          author: "Nathalie Rousseau",
          role: "Directrice",
          company: "Groupe Rousseau",
          avatar: "/avatars/rousseau.jpg",
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
          avatar: "/avatars/moreau.jpg",
          rating: 5,
        },
        {
          quote: "Taux d'ouverture email de 45%. Incroyable!",
          author: "Céline Dupont",
          role: "Responsable Marketing",
          company: "Growth Co",
          avatar: "/avatars/dupont.jpg",
          rating: 5,
        },
        {
          quote: "Intégration RH parfaite. Segmentation automatique!",
          author: "David Leclerc",
          role: "Marketing Director",
          company: "Digital Solutions",
          avatar: "/avatars/leclerc.jpg",
          rating: 5,
        },
        {
          quote: "ROI 300% sur nos campagnes. Recommandé!",
          author: "Valérie Rousseau",
          role: "CMO",
          company: "Rousseau Group",
          avatar: "/avatars/rousseau.jpg",
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
