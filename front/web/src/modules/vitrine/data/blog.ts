import type { AppLocale } from '@/lib/i18n';

export interface BlogPost {
  slug: string;
  title: string;
  excerpt: string;
  content: string;
  image: string;
  date: Date;
  author: {
    name: string;
    avatar: string;
  };
  category: string;
  readingTime: number;
  tags: string[];
  /** QA 2026-08-15 (#2657) : contenu daté (2023/2024) conservé mais marqué
   *  comme archivé — les dates de publication restent fidèles au contenu. */
  archived?: boolean;
}

export const blogPosts: BlogPost[] = [
  {
    slug: 'guide-complet-gestion-rh-startup',
    title: 'Guide Complet: Gestion RH pour Startups',
    excerpt: 'Découvrez comment mettre en place une gestion RH efficace dès le départ dans votre startup.',
    content: `# Guide Complet: Gestion RH pour Startups

## Introduction

La gestion des ressources humaines est cruciale pour le succès d'une startup. Dans ce guide, nous vous montrerons comment mettre en place une gestion RH efficace dès le départ.

## 1. Définir Votre Stratégie RH

Avant de recruter, vous devez définir votre stratégie RH:
- Quels rôles avez-vous besoin?
- Quel budget avez-vous?
- Quels sont vos valeurs d'entreprise?

## 2. Recruter les Bonnes Personnes

Le recrutement est l'étape la plus importante. Vous devez:
- Définir les profils recherchés
- Utiliser les bons canaux de recrutement
- Conduire des entretiens efficaces

## 3. Onboarding et Formation

Une fois recruté, vous devez:
- Accueillir correctement les nouveaux employés
- Fournir une formation complète
- Assigner un mentor

## 4. Gestion des Performances

Vous devez suivre les performances:
- Fixer des objectifs clairs
- Faire des retours réguliers
- Reconnaître les succès

## 5. Rétention des Talents

Pour garder vos meilleurs talents:
- Offrir une compensation compétitive
- Créer une culture d'entreprise positive
- Offrir des opportunités de croissance

## Conclusion

Une bonne gestion RH est la clé du succès d'une startup. En suivant ces étapes, vous pouvez construire une équipe forte et engagée.`,
    image: '/blog/startup-rh.svg',
    date: new Date('2024-01-15'),
    archived: true,
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.svg',
    },
    category: 'RH',
    readingTime: 8,
    tags: ['startup', 'RH', 'recrutement', 'gestion'],
  },
  {
    slug: 'automatiser-paie-excel-vers-logiciel',
    title: 'Passer de Excel à un Logiciel de Paie: Guide Pratique',
    excerpt: 'Comment migrer votre gestion de paie d\'Excel vers un logiciel automatisé sans stress.',
    content: `# Passer de Excel à un Logiciel de Paie: Guide Pratique

## Pourquoi Passer de Excel?

Excel est un outil puissant, mais il n'est pas conçu pour la gestion de paie. Les risques incluent:
- Erreurs de calcul
- Perte de données
- Manque de conformité
- Temps perdu

## Étape 1: Évaluer Votre Situation Actuelle

Avant de migrer, vous devez:
- Documenter votre processus actuel
- Identifier les données à migrer
- Évaluer vos besoins

## Étape 2: Choisir le Bon Logiciel

Recherchez un logiciel qui:
- Supporte votre pays/région
- Offre une migration facile
- Fournit un bon support

## Étape 3: Préparer Vos Données

Avant la migration:
- Nettoyez vos données
- Formatez-les correctement
- Testez l'import

## Étape 4: Migrer Progressivement

Ne migrez pas tout d'un coup:
- Commencez par un petit groupe
- Testez le processus
- Ajustez si nécessaire

## Étape 5: Former Votre Équipe

Assurez-vous que votre équipe:
- Comprend le nouveau système
- Sait comment l'utiliser
- Connaît les ressources d'aide

## Conclusion

La migration de Excel vers un logiciel de paie peut sembler intimidante, mais avec une bonne planification, c'est un processus fluide qui vous fera gagner du temps et réduira les erreurs.`,
    image: '/blog/paie-excel.svg',
    date: new Date('2024-01-10'),
    archived: true,
    author: {
      name: 'Jean Martin',
      avatar: '/avatars/jean.svg',
    },
    category: 'Paie',
    readingTime: 10,
    tags: ['paie', 'excel', 'automatisation', 'migration'],
  },
  {
    slug: 'tendances-rh-2024',
    title: 'Les Tendances RH à Surveiller en 2024',
    excerpt: 'Découvrez les tendances RH qui façonneront le monde du travail en 2024.',
    content: `# Les Tendances RH à Surveiller en 2024

## 1. Travail Hybride et Flexible

Le travail hybride est devenu la norme. Les entreprises doivent:
- Offrir de la flexibilité
- Gérer les équipes distribuées
- Maintenir la culture d'entreprise

## 2. Bien-être des Employés

Le bien-être est une priorité:
- Santé mentale
- Équilibre travail-vie
- Programmes de bien-être

## 3. Diversité et Inclusion

Les entreprises se concentrent sur:
- Recruter diversement
- Créer une culture inclusive
- Mesurer l'impact

## 4. Upskilling et Reskilling

Les compétences changent rapidement:
- Investir dans la formation
- Développer les talents internes
- Préparer l'avenir

## 5. Automatisation RH

La technologie transforme la RH:
- Automatiser les tâches répétitives
- Utiliser l'IA pour les décisions
- Améliorer l'expérience employé

## Conclusion

2024 sera une année de transformation pour la RH. Les entreprises qui s'adaptent à ces tendances seront mieux positionnées pour le succès.`,
    image: '/blog/tendances-rh.svg',
    date: new Date('2024-01-05'),
    archived: true,
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.svg',
    },
    category: 'Tendances',
    readingTime: 7,
    tags: ['tendances', 'RH', '2024', 'futur'],
  },
  {
    slug: 'productivite-5-conseils-economiser-temps',
    title: '5 Conseils pour Économiser du Temps en Gestion RH',
    excerpt: 'Découvrez comment économiser des heures chaque semaine en optimisant votre gestion RH.',
    content: `# 5 Conseils pour Économiser du Temps en Gestion RH

## 1. Automatiser les Tâches Répétitives

Les tâches répétitives consomment du temps:
- Automatiser la paie
- Automatiser les absences
- Automatiser les rapports

## 2. Centraliser Vos Données

Disperser les données coûte du temps:
- Utiliser une plateforme centralisée
- Éviter les doublons
- Accéder facilement aux informations

## 3. Utiliser des Templates

Les templates vous font gagner du temps:
- Templates de contrats
- Templates de lettres
- Templates de rapports

## 4. Déléguer Efficacement

Vous ne pouvez pas tout faire:
- Déléguer les tâches appropriées
- Former votre équipe
- Faire confiance

## 5. Utiliser la Technologie

La technologie est votre allié:
- Logiciels RH
- Outils de collaboration
- Outils d'automatisation

## Conclusion

En suivant ces conseils, vous pouvez économiser des heures chaque semaine et vous concentrer sur les tâches stratégiques.`,
    image: '/blog/productivite.svg',
    date: new Date('2023-12-28'),
    archived: true,
    author: {
      name: 'Fatima Dupont',
      avatar: '/avatars/fatima.svg',
    },
    category: 'Productivité',
    readingTime: 6,
    tags: ['productivité', 'temps', 'efficacité', 'conseils'],
  },
  {
    slug: 'conformite-rgpd-donnees-employes',
    title: 'Conformité RGPD: Protéger les Données de Vos Employés',
    excerpt: 'Guide complet pour assurer la conformité RGPD dans votre gestion des données employés.',
    content: `# Conformité RGPD: Protéger les Données de Vos Employés

## Qu'est-ce que le RGPD?

Le RGPD (Règlement Général sur la Protection des Données) est une loi européenne qui protège les données personnelles.

## Vos Obligations

En tant qu'employeur, vous devez:
- Collecter les données légalement
- Les protéger adéquatement
- Respecter les droits des employés
- Signaler les violations

## Données Employés Sensibles

Les données employés incluent:
- Informations personnelles
- Données de santé
- Données financières
- Données de performance

## Mesures de Protection

Pour protéger les données:
- Chiffrer les données
- Limiter l'accès
- Faire des sauvegardes
- Avoir une politique de confidentialité

## Droits des Employés

Les employés ont le droit de:
- Accéder à leurs données
- Corriger leurs données
- Supprimer leurs données
- Exporter leurs données

## Conclusion

La conformité RGPD est essentielle. En mettant en place les bonnes mesures, vous protégez vos employés et votre entreprise.`,
    image: '/blog/rgpd.svg',
    date: new Date('2023-12-20'),
    archived: true,
    author: {
      name: 'Jean Martin',
      avatar: '/avatars/jean.svg',
    },
    category: 'Conformité',
    readingTime: 9,
    tags: ['RGPD', 'conformité', 'données', 'sécurité'],
  },
  {
    slug: 'culture-entreprise-engagement-employes',
    title: 'Construire une Culture d\'Entreprise Positive',
    excerpt: 'Comment créer une culture d\'entreprise qui engage et motive vos employés.',
    content: `# Construire une Culture d'Entreprise Positive

## Qu'est-ce qu'une Culture d'Entreprise?

La culture d'entreprise est l'ensemble des valeurs, croyances et comportements d'une organisation.

## Pourquoi C'est Important?

Une bonne culture:
- Augmente l'engagement
- Réduit le turnover
- Améliore la productivité
- Attire les talents

## Éléments Clés

Une bonne culture inclut:
- Valeurs claires
- Communication ouverte
- Reconnaissance
- Opportunités de croissance

## Comment la Construire

Pour construire une bonne culture:
- Définir vos valeurs
- Communiquer régulièrement
- Reconnaître les succès
- Investir dans le développement

## Mesurer l'Impact

Vous pouvez mesurer la culture par:
- Sondages d'engagement
- Taux de rétention
- Productivité
- Satisfaction client

## Conclusion

Une bonne culture d'entreprise est un investissement qui paie. Elle crée un environnement où les employés veulent travailler et où l'entreprise prospère.`,
    image: '/blog/culture.svg',
    date: new Date('2023-12-15'),
    archived: true,
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.svg',
    },
    category: 'Culture',
    readingTime: 8,
    tags: ['culture', 'engagement', 'employés', 'valeurs'],
  },
  {
    slug: 'pointage-biometrique-avantages',
    title: 'Pointage Biométrique: Avantages et Implémentation',
    excerpt: 'Découvrez comment le pointage biométrique peut améliorer votre gestion RH.',
    content: `# Pointage Biométrique: Avantages et Implémentation

## Qu'est-ce que le Pointage Biométrique?

Le pointage biométrique utilise des caractéristiques biologiques uniques:
- Reconnaissance faciale
- Empreinte digitale
- Reconnaissance d'iris
- Reconnaissance vocale

## Avantages

Le pointage biométrique offre:
- Sécurité accrue
- Prévention de la fraude
- Précision
- Facilité d'utilisation

## Implémentation

Pour implémenter le pointage biométrique:
- Évaluer vos besoins
- Choisir la technologie
- Installer les équipements
- Former les employés

## Considérations Légales

Vous devez:
- Respecter la vie privée
- Obtenir le consentement
- Respecter le RGPD
- Avoir une politique claire

## Coûts

Les coûts incluent:
- Équipements
- Logiciels
- Installation
- Maintenance

## Conclusion

Le pointage biométrique est une solution moderne qui améliore la sécurité et l'efficacité. Avec une bonne implémentation, c'est un investissement rentable.`,
    image: '/blog/biometrique.svg',
    date: new Date('2023-12-10'),
    archived: true,
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.svg',
    },
    category: 'Technologie',
    readingTime: 7,
    tags: ['biométrie', 'pointage', 'technologie', 'sécurité'],
  },
  {
    slug: 'gestion-absences-conges-efficace',
    title: 'Gestion Efficace des Absences et Congés',
    excerpt: 'Optimisez votre gestion des absences et congés avec les meilleures pratiques.',
    content: `# Gestion Efficace des Absences et Congés

## Défis Courants

La gestion des absences est complexe:
- Suivi manuel
- Erreurs de calcul
- Manque de visibilité
- Processus lents

## Meilleures Pratiques

Pour une gestion efficace:
- Définir une politique claire
- Utiliser un système centralisé
- Automatiser les calculs
- Communiquer régulièrement

## Politique d'Absences

Votre politique doit couvrir:
- Types d'absences
- Processus de demande
- Délais d'approbation
- Calcul des soldes

## Système de Gestion

Un bon système doit:
- Permettre les demandes faciles
- Automatiser les approbations
- Calculer les soldes
- Générer des rapports

## Communication

Vous devez:
- Communiquer la politique
- Répondre aux questions
- Fournir un support
- Mettre à jour régulièrement

## Conclusion

Une gestion efficace des absences améliore la satisfaction des employés et réduit les erreurs administratives.`,
    image: '/blog/absences.svg',
    date: new Date('2023-12-05'),
    archived: true,
    author: {
      name: 'Fatima Dupont',
      avatar: '/avatars/fatima.svg',
    },
    category: 'RH',
    readingTime: 6,
    tags: ['absences', 'congés', 'gestion', 'politique'],
  },
  {
    slug: 'recrutement-digital-sourcing-talents',
    title: 'Recrutement Digital: Sourcer les Meilleurs Talents',
    excerpt: 'Stratégies modernes pour recruter les meilleurs talents dans l\'ère numérique.',
    content: `# Recrutement Digital: Sourcer les Meilleurs Talents

## Évolution du Recrutement

Le recrutement a changé:
- Candidats recherchent en ligne
- Réseaux sociaux importants
- Marque employeur cruciale
- Processus plus rapide

## Canaux de Recrutement

Utilisez plusieurs canaux:
- LinkedIn
- Réseaux sociaux
- Annuaires d'emploi
- Recommandations
- Événements

## Stratégie de Sourcing

Pour sourcer efficacement:
- Définir le profil idéal
- Utiliser les bons mots-clés
- Être actif sur les réseaux
- Cultiver votre réseau

## Marque Employeur

Votre marque employeur doit:
- Montrer votre culture
- Partager les succès
- Engager les employés
- Être authentique

## Processus de Recrutement

Optimisez votre processus:
- Simplifier les candidatures
- Répondre rapidement
- Faire des entretiens efficaces
- Prendre des décisions rapides

## Conclusion

Le recrutement digital est essentiel pour attirer les meilleurs talents. En utilisant les bons canaux et stratégies, vous pouvez construire une équipe exceptionnelle.`,
    image: '/blog/recrutement.svg',
    date: new Date('2023-11-30'),
    archived: true,
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.svg',
    },
    category: 'Recrutement',
    readingTime: 8,
    tags: ['recrutement', 'digital', 'talents', 'sourcing'],
  },
  {
    slug: 'formation-developpement-competences',
    title: 'Formation et Développement: Investir dans Vos Talents',
    excerpt: 'Comment mettre en place un programme de formation efficace pour développer vos talents.',
    content: `# Formation et Développement: Investir dans Vos Talents

## Importance de la Formation

La formation est cruciale:
- Développe les compétences
- Augmente l'engagement
- Réduit le turnover
- Améliore la performance

## Types de Formation

Offrez différents types:
- Formation technique
- Formation en leadership
- Formation en soft skills
- Formation en conformité

## Planification de la Formation

Pour planifier efficacement:
- Évaluer les besoins
- Définir les objectifs
- Choisir les méthodes
- Mesurer l'impact

## Méthodes de Formation

Utilisez diverses méthodes:
- Formation en classe
- E-learning
- Mentorat
- Coaching
- Apprentissage sur le terrain

## Mesure du ROI

Mesurez l'impact:
- Compétences acquises
- Performance améliorée
- Rétention
- Satisfaction

## Conclusion

Investir dans la formation de vos employés est un investissement dans l'avenir de votre entreprise. Cela crée une équipe plus compétente et engagée.`,
    image: '/blog/formation.svg',
    date: new Date('2023-11-25'),
    archived: true,
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.svg',
    },
    category: 'Développement',
    readingTime: 7,
    tags: ['formation', 'développement', 'compétences', 'apprentissage'],
  },
  {
    slug: 'guide-paie-algerie-irg-cnas',
    title: 'Guide de la paie en Algérie (IRG, CNAS) : obligations et outils',
    excerpt: "IRG, CNAS, SNMG, G50, DAS : le guide pratique de la paie en Algérie pour les PME, avec les obligations à connaître et les critères pour choisir un logiciel de paie adapté.",
    content: `# Guide de la paie en Algérie (IRG, CNAS) : obligations et outils

La paie algérienne a ses propres règles : un barème IRG progressif, des cotisations CNAS partagées entre employeur et salarié, un salaire minimum national (SNMG), des déclarations mensuelles (G50) et annuelles (DAS) à des échéances strictes. Pour une PME, chaque bulletin est à la fois un document social, un document fiscal et une pièce comptable. Ce guide rassemble les repères essentiels pour structurer votre gestion de la paie en Algérie, puis compare les approches outillées — du tableur au logiciel de paie dédié.

Avertissement important : les montants, taux et barèmes cités ici sont des repères indicatifs, relevés en septembre 2026. Les lois de finances algériennes évoluent chaque année : avant tout paramétrage ou calcul réel, vérifiez les valeurs en vigueur auprès de la Direction Générale des Impôts (DGI), de la CNAS et de votre expert-comptable.

## Pourquoi la paie algérienne est spécifique

Trois caractéristiques distinguent la paie en Algérie de celle d'autres pays francophones :

- Un impôt sur le revenu (IRG) retenu à la source par l'employeur, avec un barème progressif propre à l'Algérie et des abattements spécifiques.
- Un régime de sécurité sociale unifié autour de la CNAS pour les salariés, avec une répartition employeur/salarié fixée nationalement.
- Un formalisme déclaratif exigeant : déclaration fiscale mensuelle (G50), déclaration annuelle des salaires (DAS), registres obligatoires.

Résultat : un bulletin de paie algérien ne se « bricole » pas à partir d'un modèle français ou générique. La structure du brut, les rubriques cotisables et imposables, l'ordre des calculs (cotisations puis IRG) doivent suivre la réglementation locale.

## Le cadre légal du travail en Algérie

### Le socle : la loi 90-11

Les relations de travail sont régies principalement par la loi n° 90-11 du 21 avril 1990 relative aux relations de travail, complétée par des textes sectoriels et les conventions collectives. Ce socle définit le contrat de travail, la durée légale, les congés, et les obligations documentaires de l'employeur.

### Durée du travail et heures supplémentaires

La durée légale hebdomadaire est de 40 heures, réparties en général sur cinq jours. Les heures supplémentaires sont encadrées (plafond et majorations) : elles doivent apparaître distinctement sur le bulletin, car elles entrent dans l'assiette des cotisations et de l'IRG.

### SNMG : le salaire minimum

Le Salaire National Minimum Garanti (SNMG) constitue le plancher de rémunération. Il est fixé par décret ; le montant de référence relevé en septembre 2026 est de 20 000 DZD par mois (valeur fixée en 2020 — vérifiez systématiquement le montant en vigueur avant application). Le SNMG sert aussi de base de calcul à certaines indemnités et exonérations.

### Congés payés et absences

Le salarié acquiert en règle générale 2,5 jours calendaires de congé par mois travaillé, soit 30 jours calendaires par an, avec des majorations pour les travailleurs du Sud. Les absences (maladie, maternité, accident du travail) obéissent à des règles d'indemnisation CNAS spécifiques qui impactent directement le calcul du net.

## Les cotisations sociales : la CNAS

### Répartition employeur / salarié

Le régime des assurances sociales des travailleurs salariés est géré par la CNAS. Le taux global de cotisation de référence (relevé en septembre 2026, à confirmer selon votre situation) est de 34,5 % du salaire de poste, réparti approximativement ainsi :

- Part employeur : 26 % (assurances sociales, accidents du travail et maladies professionnelles, retraite, assurance chômage, retraite anticipée).
- Part salariale : 9 % (retenue sur le salaire brut, affichée sur le bulletin).
- Œuvres sociales et autres contributions éventuelles selon les textes applicables à votre secteur.

L'assiette de cotisation est le « salaire de poste » : salaire de base plus primes et indemnités liées au travail, hors éléments expressément exclus (certaines indemnités à caractère de remboursement de frais, comme le panier ou le transport, sous conditions strictes).

### Affiliation et immatriculation

Tout employeur doit s'affilier à la CNAS et immatriculer chaque salarié dès l'embauche (dans les 10 jours en règle générale). L'absence de déclaration expose à des majorations, des pénalités, et prive le salarié de ses droits — c'est l'un des premiers points contrôlés lors d'une inspection.

### Indépendants : la CASNOS

Les gérants majoritaires et travailleurs non salariés relèvent de la CASNOS et non de la CNAS. Dans une PME, il est fréquent de devoir gérer les deux régimes en parallèle : le dirigeant à la CASNOS, les salariés à la CNAS.

## L'IRG : l'impôt sur le revenu global

### Un barème progressif retenu à la source

L'employeur calcule et retient l'IRG sur les salaires. Le barème mensuel est progressif ; les repères relevés en septembre 2026 (issus de la loi de finances 2022, à vérifier chaque année) :

- Jusqu'à 30 000 DZD mensuels imposables : exonération.
- Tranches suivantes imposées à des taux croissants, jusqu'à 35 % pour la tranche la plus haute.
- Un abattement proportionnel s'applique, avec un mécanisme spécifique pour les salaires proches du seuil d'exonération afin d'éviter les effets de seuil.

### L'ordre des calculs

Le point qui génère le plus d'erreurs dans les tableurs : l'assiette IRG est le salaire imposable après déduction de la part salariale de sécurité sociale (les 9 % CNAS). Calculer l'IRG sur le brut, ou oublier d'exclure une rubrique non imposable, fausse tout le bas du bulletin.

### Rubriques imposables et exonérées

Certaines primes et indemnités bénéficient d'exonérations totales ou partielles d'IRG (et parfois de cotisations) sous conditions. Chaque rubrique de paie doit donc être qualifiée deux fois : cotisable ou non, imposable ou non. Un paramétrage rigoureux de ce référentiel de rubriques est le cœur d'une paie algérienne fiable.

## Les obligations déclaratives et documentaires

### La déclaration G50

L'IRG retenu sur les salaires est reversé à l'administration fiscale via la déclaration G50, en principe dans les 20 premiers jours du mois suivant. Le G50 agrège d'autres impôts et taxes : la ligne « IRG salaires » doit être cohérente avec vos journaux de paie du mois.

### Les déclarations CNAS et la DAS

Les cotisations CNAS font l'objet de déclarations et de versements périodiques (mensuels ou trimestriels selon l'effectif). S'y ajoute la Déclaration Annuelle des Salaires (DAS), qui récapitule par salarié les rémunérations et cotisations de l'année — elle doit être strictement cohérente avec les 12 mois de paie déclarés.

### Le bulletin de paie et les registres

Chaque paiement de salaire donne lieu à un bulletin détaillant les rubriques, les cotisations et l'IRG. L'employeur tient en outre les registres obligatoires (registre des personnels, registre de paie...) et conserve les documents de paie pendant les durées légales. En cas de contrôle CNAS ou fiscal, la traçabilité est votre première protection.

## Les erreurs fréquentes des PME algériennes

- Payer une partie du salaire « hors bulletin » : risque social et fiscal majeur, redressements et pénalités à la clé.
- Mal qualifier les primes (cotisable/imposable) : écarts entre G50, DAS et comptabilité.
- Oublier la mise à jour annuelle du barème IRG après la loi de finances.
- Calculer l'IRG avant la retenue CNAS au lieu de l'inverse.
- Gérer les congés sur un fichier séparé de la paie : soldes faux, indemnités de congé erronées au départ du salarié.
- Ne pas archiver les justificatifs : la DAS devient impossible à réconcilier en fin d'année.

## Trois situations qui compliquent la paie algérienne

### L'embauche ou le départ en cours de mois

Le prorata du salaire de base est simple ; le prorata des primes, du barème IRG mensuel et des plafonds l'est beaucoup moins. Au départ d'un salarié, le solde de tout compte doit intégrer l'indemnité de congé non pris, calculée sur des soldes fiables — impossible si les congés vivent dans un fichier à part.

### Les primes de zone et le travail dans le Sud

Les entreprises opérant dans les wilayas du Sud appliquent des majorations de congés et des primes de zone spécifiques. Ces rubriques doivent être correctement qualifiées face à la CNAS et à l'IRG, et documentées : c'est un point d'attention récurrent lors des contrôles.

### Les rappels de salaire

Un rappel (augmentation rétroactive, régularisation d'heures) se rattache fiscalement et socialement à des périodes antérieures. Un outil qui ne sait pas ventiler un rappel sur les bons mois produit des DAS incohérentes avec les G50 déjà déposés.

## Excel ou logiciel de paie en Algérie ?

Beaucoup de PME algériennes démarrent la paie sous Excel. C'est viable à 3 salariés, risqué à 10, ingérable à 30. Les limites concrètes :

- Chaque changement de barème IRG impose de réécrire des formules à la main, sans historique.
- Les rubriques cotisables/imposables sont gérées « de tête », sans référentiel contrôlé.
- Aucune piste d'audit : impossible de prouver qui a modifié quoi avant un contrôle.
- La DAS annuelle se reconstruit péniblement à partir de 12 fichiers mensuels.

Un logiciel de paie adapté à l'Algérie doit au minimum : embarquer le barème IRG et les taux CNAS avec leurs dates de validité, qualifier chaque rubrique (cotisable/imposable), produire des bulletins conformes, préparer les états nécessaires au G50 et à la DAS, et tracer chaque calcul. Nous avons détaillé la démarche générale de migration dans notre article [Passer de Excel à un logiciel de paie](/blog/automatiser-paie-excel-vers-logiciel) et dans la [checklist paie](/guides/checklist-paie).

## Comment Leopardo aborde la paie algérienne

Leopardo est une suite métier pour les entreprises de terrain : RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. Côté paie, la suite couvre 21 pays (mesuré 2026-09-09), dont l'Algérie, avec des règles pays en **statut pilote**.

Ce statut pilote signifie, concrètement : les barèmes IRG et les taux CNAS sont fournis, versionnés et testés, mais la validation finale de chaque cycle de paie reste de la responsabilité de votre gestionnaire de paie ou de votre expert-comptable. Nous ne promettons pas une « conformité légale validée » — personne ne peut sérieusement le faire à votre place — nous fournissons un moteur de calcul transparent, des exports de paie exploitables par votre comptable, et une traçabilité complète de chaque bulletin.

Pour une PME algérienne, l'intérêt est ailleurs que dans la seule conformité : le pointage (biométrie, QR, mobile) alimente directement les heures travaillées et supplémentaires, les [absences et congés](/blog/gestion-absences-conges-efficace) sont décomptés à la source, et le [dossier salarié](/employes) centralise contrats et documents. Chaque tenant est isolé : vos données de paie ne se mélangent jamais avec celles d'une autre entreprise.

Si vous comparez les solutions du marché (Sage, PayFit, Odoo...), notre page [alternatives](/alternatives) détaille honnêtement, critère par critère, ce que Leopardo couvre et ne couvre pas — y compris la [comparaison avec Sage](/alternatives/sage), l'acteur historique de la paie en Afrique francophone.

## Checklist : structurer sa paie en Algérie

- Vérifier l'affiliation CNAS de l'entreprise et l'immatriculation de chaque salarié.
- Construire le référentiel de rubriques avec leur double qualification (cotisable / imposable).
- Documenter le barème IRG en vigueur et sa source (loi de finances applicable).
- Caler le calendrier : paie, G50 avant le 20, déclarations CNAS, DAS annuelle.
- Mettre en place un contrôle mensuel : brut total, cotisations, IRG, net — rapproché de la comptabilité.
- Archiver bulletins, journaux de paie et déclarations pendant les durées légales.
- Choisir un outil qui trace les calculs et facilite la réconciliation annuelle.

## Questions fréquentes

### Quel est le salaire minimum en Algérie ?

Le SNMG est le plancher légal ; la valeur de référence relevée en septembre 2026 est de 20 000 DZD par mois (fixée en 2020). Vérifiez le montant en vigueur avant toute application, car il est révisé par décret.

### Qui paie l'IRG, l'employeur ou le salarié ?

L'IRG est dû par le salarié mais retenu à la source et reversé par l'employeur via le G50. Une erreur de calcul engage donc la responsabilité de l'entreprise.

### Un logiciel de paie étranger peut-il gérer la paie algérienne ?

Seulement s'il embarque réellement le barème IRG, les règles CNAS et les états déclaratifs algériens — et s'il les met à jour à chaque loi de finances. Un paramétrage générique « adapté à la main » reproduit les risques d'Excel.

## Conclusion

La paie en Algérie est exigeante mais parfaitement structurable : un cadre légal stable (loi 90-11), deux piliers de prélèvement (CNAS puis IRG), un calendrier déclaratif connu d'avance. La clé est un référentiel de rubriques rigoureux, des barèmes versionnés et une traçabilité de bout en bout.

Envie de voir comment une suite métier gère ce cycle de bout en bout — du pointage au bulletin, avec des règles Algérie en statut pilote ? [Essayez Leopardo gratuitement pendant 14 jours, sans carte bancaire](/signup), ou réservez une [démo guidée](/demo) : l'onboarding prend moins de 30 minutes.`,
    image: '/blog/paie-algerie.svg',
    date: new Date('2026-09-18'),
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.svg',
    },
    category: 'Paie',
    readingTime: 12,
    tags: ['paie', 'algérie', 'IRG', 'CNAS', 'logiciel de paie'],
  },
  {
    slug: 'gerer-la-paie-au-senegal-guide-pme',
    title: 'Gérer la paie au Sénégal : guide pratique PME',
    excerpt: "IPRES, CSS, IR et TRIMF, CFCE : tout ce qu'une PME doit maîtriser pour gérer la paie au Sénégal, du bulletin aux déclarations, et bien choisir son outil.",
    content: `# Gérer la paie au Sénégal : guide pratique PME

Gérer la paie au Sénégal, c'est jongler entre deux caisses sociales (IPRES pour la retraite, CSS pour les prestations familiales et les accidents du travail), un impôt sur le revenu retenu à la source avec un système de parts familiales, la TRIMF, la CFCE à la charge de l'employeur, et un calendrier déclaratif mensuel et annuel. Ce guide pratique donne aux dirigeants et gestionnaires de PME sénégalaises les repères pour produire une paie propre — et les critères pour choisir un outil de gestion de la paie adapté.

Avertissement : les taux, plafonds et montants cités sont des repères indicatifs relevés en septembre 2026. Ils évoluent par loi de finances, décret ou décision des caisses : vérifiez systématiquement les valeurs en vigueur auprès de la DGID, de l'IPRES, de la CSS et de votre expert-comptable avant tout calcul réel.

## Le paysage de la paie sénégalaise en bref

Le droit du travail sénégalais repose sur le Code du travail (loi n° 97-17 du 1er décembre 1997), la Convention Collective Nationale Interprofessionnelle (CCNI, révisée en 2019) et les conventions sectorielles. Côté prélèvements, quatre acteurs structurent le bulletin :

- L'IPRES : retraite (régime général et régime complémentaire des cadres).
- La CSS : prestations familiales et couverture accidents du travail / maladies professionnelles.
- La DGID : impôt sur le revenu retenu à la source et TRIMF.
- L'employeur lui-même : CFCE (contribution forfaitaire à sa charge exclusive).

La monnaie de paie est le franc CFA (XOF), et la plupart des cotisations sociales sont plafonnées — une spécificité qui surprend souvent ceux qui viennent de systèmes à cotisations déplafonnées.

## Le cadre légal du travail

### Contrats, durée du travail, salaire minimum

La durée légale de travail est de 40 heures par semaine dans le régime général non agricole. Les heures supplémentaires sont majorées selon des taux fixés par la réglementation et la CCNI. Le SMIG (salaire minimum interprofessionnel garanti) est fixé par voie réglementaire ; il a été revalorisé en 2023 — vérifiez le taux horaire en vigueur avant application, ainsi que les minima par catégorie prévus par votre convention collective, souvent supérieurs au SMIG.

### Congés payés

Le salarié acquiert en règle générale 2 jours ouvrables de congé par mois de service effectif, avec des majorations liées à l'ancienneté et des congés spécifiques (maternité, événements familiaux). L'indemnité de congé se calcule sur la rémunération moyenne — d'où l'importance d'un historique de paie fiable.

### Catégories professionnelles et CCNI

La CCNI classe les salariés par catégories et échelons, avec des salaires minima conventionnels. Lors de l'embauche, le rattachement à la bonne catégorie conditionne le salaire minimum applicable, les préavis et certaines indemnités : c'est un paramètre de paie à part entière.

## Les cotisations sociales : CSS et IPRES

### La CSS : prestations familiales et accidents du travail

La Caisse de Sécurité Sociale couvre deux branches, financées par l'employeur seul (repères relevés en septembre 2026, à confirmer) :

- Prestations familiales : 7 % du salaire soumis, dans la limite d'un plafond mensuel par salarié (63 000 FCFA de référence).
- Accidents du travail / maladies professionnelles : 1 %, 3 % ou 5 % selon le niveau de risque de l'activité, sur la même assiette plafonnée.

### L'IPRES : la retraite

L'Institution de Prévoyance Retraite du Sénégal gère le régime général (tous salariés) et le régime complémentaire des cadres. Ordres de grandeur de référence (septembre 2026, à confirmer auprès de l'IPRES) :

- Régime général : 14 % du salaire plafonné, répartis entre part patronale (8,4 %) et part salariale (5,6 %).
- Régime complémentaire cadres : 6 % supplémentaires sur une assiette plafonnée plus élevée, répartis également entre employeur et salarié.

Les plafonds IPRES et CSS sont révisés périodiquement : un bulletin correct dépend directement de plafonds à jour.

### La couverture maladie

La couverture maladie des salariés passe par les Institutions de Prévoyance Maladie (IPM), avec une cotisation partagée employeur/salarié selon les statuts de l'IPM. C'est une ligne de bulletin souvent oubliée par les outils génériques.

## Impôts sur salaires : IR, TRIMF et CFCE

### L'impôt sur le revenu retenu à la source

L'employeur retient l'IR sur les salaires selon un barème progressif (taux marginaux allant jusqu'à 40 % pour les tranches supérieures, repère septembre 2026). Spécificité sénégalaise : le système des parts familiales. Le nombre de parts (situation matrimoniale, enfants à charge) ouvre droit à une réduction d'impôt encadrée par un minimum et un maximum par tranche de parts. Deux salariés au même brut peuvent donc avoir des nets sensiblement différents.

### La TRIMF

La Taxe Représentative de l'Impôt du Minimum Fiscal est une retenue mensuelle forfaitaire, d'un faible montant, fonction de la tranche de salaire. Elle figure sur le bulletin et suit le même circuit déclaratif que l'IR.

### La CFCE : la contribution de l'employeur

La Contribution Forfaitaire à la Charge de l'Employeur s'élève à 3 % (référence septembre 2026) de la masse salariale imposable. Elle ne se retient pas sur le salarié : c'est un coût employeur à intégrer dans votre coût total du travail — et souvent le grand oublié des budgets de recrutement.

## Le calendrier déclaratif

### Au mois le mois

- Versement des retenues à la source (IR, TRIMF) et de la CFCE à la DGID selon la périodicité applicable à votre entreprise.
- Cotisations CSS et IPRES : déclarations et paiements aux échéances fixées par les caisses (mensuelles ou trimestrielles selon l'effectif).

### À l'année

- État récapitulatif annuel des salaires versés et retenues opérées pour la DGID (déclaration annuelle des salaires).
- Régularisations annuelles auprès de l'IPRES et de la CSS.

La règle d'or : les 12 journaux de paie mensuels doivent se réconcilier exactement avec les déclarations annuelles. Toute rubrique mal qualifiée en cours d'année ressort à ce moment-là — au pire moment.

## Les erreurs fréquentes des PME sénégalaises

- Ignorer les plafonds CSS/IPRES et cotiser sur le brut total : bulletins faux et trop-versés difficiles à récupérer.
- Mal gérer les parts familiales de l'IR : nets erronés, réclamations des salariés, régularisations pénibles.
- Oublier la CFCE dans le coût employeur lors des embauches.
- Ne pas appliquer les minima de la convention collective applicable, en se contentant du SMIG.
- Gérer heures supplémentaires et absences hors du système de paie, sur papier ou WhatsApp.
- Reporter les déclarations : les pénalités et majorations des caisses s'accumulent vite.

## Cas particuliers à anticiper

### CDD, journaliers et saisonniers

Le recours aux contrats à durée déterminée est encadré par le Code du travail ; les travailleurs journaliers et saisonniers, fréquents dans le commerce, le BTP et l'agro-alimentaire, restent des salariés à part entière : cotisations CSS/IPRES, IR et bulletin obligatoires. C'est précisément sur ces populations « terrain » que le pointage fiable fait la différence entre une paie juste et un contentieux.

### Apprentis et stagiaires

Les conventions de stage et contrats d'apprentissage obéissent à des règles d'assujettissement particulières : vérifiez au cas par cas ce qui est soumis à cotisations et à l'IR avant de créer la rubrique correspondante.

### Expatriés et personnel détaché

Les salariés étrangers posent des questions d'affiliation (conventions bilatérales de sécurité sociale) et de résidence fiscale. Ne dupliquez pas simplement le paramétrage local : chaque situation d'expatriation mérite une validation par votre conseil.

## Excel, sous-traitance ou logiciel : comment outiller sa paie ?

À moins de 5 salariés, un tableur bien tenu ou un cabinet comptable suffit souvent. Au-delà, les limites apparaissent :

- Le barème IR avec parts familiales est complexe à fiabiliser en formules Excel.
- Les plafonds CSS/IPRES changent : qui met à jour les 12 fichiers de l'année ?
- La sous-traitance totale coupe le dirigeant de ses données : impossible de simuler un coût d'embauche ou de sortir un état à la demande.

Un bon outil de gestion de la paie au Sénégal doit : embarquer le barème IR et les parts familiales, gérer les plafonds CSS/IPRES avec leurs dates de validité, distinguer les rubriques soumises/non soumises par organisme, produire bulletins et journaux exploitables par votre comptable, et intégrer les heures réellement travaillées. Notre [checklist paie](/guides/checklist-paie) et notre guide [Passer de Excel à un logiciel de paie](/blog/automatiser-paie-excel-vers-logiciel) détaillent la démarche de migration pas à pas.

## Comment Leopardo aborde la paie sénégalaise

Leopardo est une suite métier pour les entreprises de terrain — RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes. La paie couvre 21 pays (mesuré 2026-09-09), dont le Sénégal, avec des règles pays en **statut pilote**.

Soyons transparents sur ce que « pilote » veut dire : les barèmes (IR, TRIMF, parts familiales) et les taux et plafonds CSS/IPRES sont fournis, versionnés et testés, mais la validation finale de chaque cycle de paie reste de la responsabilité de votre gestionnaire de paie ou de votre expert-comptable. Nous ne revendiquons aucune « conformité validée » : nous fournissons des calculs traçables, des exports de paie propres pour votre cabinet, et l'isolation complète de vos données (chaque entreprise vit dans son propre espace).

Là où la suite change concrètement le quotidien d'une PME sénégalaise : le pointage terrain (mobile, QR, biométrie) alimente les heures et les majorations sans ressaisie, la [gestion des absences et congés](/blog/gestion-absences-conges-efficace) suit les soldes CCNI, et la [base employés](/employes) garde contrats, catégories conventionnelles et documents au même endroit.

Vous évaluez aussi Sage, PayFit, Odoo ou un outil local ? Notre page [alternatives](/alternatives) compare point par point — honnêtement, statut pilote inclus — ce que chaque option couvre pour l'Afrique de l'Ouest, notamment face à [Sage](/alternatives/sage), très présent chez les cabinets comptables sénégalais.

## Checklist : mettre sa paie sénégalaise sous contrôle

- Vérifier l'immatriculation de l'entreprise et des salariés à l'IPRES et à la CSS.
- Rattacher chaque salarié à sa catégorie CCNI et vérifier les minima conventionnels.
- Documenter le barème IR, la table des parts familiales et la TRIMF en vigueur, avec leurs sources.
- Paramétrer les plafonds CSS et IPRES avec leur date de validité.
- Intégrer la CFCE (3 %, référence septembre 2026) dans chaque simulation de coût d'embauche.
- Mettre en place la réconciliation mensuelle paie / comptabilité / déclarations.
- Archiver bulletins, journaux et déclarations pendant les durées légales.

## Questions fréquentes

### Quelles cotisations le salarié voit-il sur son bulletin ?

Principalement sa part IPRES (régime général, et complémentaire s'il est cadre), sa cotisation IPM le cas échéant, l'IR retenu à la source et la TRIMF. Les cotisations CSS et la CFCE sont à la charge exclusive de l'employeur.

### Combien coûte réellement un salarié au Sénégal ?

Au brut s'ajoutent les parts patronales IPRES, les cotisations CSS (7 % + taux AT selon risque, sur assiette plafonnée), la cotisation IPM patronale et la CFCE (3 %). Selon le niveau de salaire et les plafonds, le surcoût employeur se situe typiquement entre 10 et 20 % du brut (ordre de grandeur indicatif, septembre 2026) — à simuler précisément cas par cas.

### Que risque une entreprise en retard sur ses déclarations ?

Les caisses appliquent des majorations et pénalités de retard qui s'accumulent mois après mois, et un salarié non déclaré peut se retourner contre l'employeur en cas d'accident du travail. La régularisation tardive coûte toujours plus cher que la déclaration à l'heure.

### Une PME peut-elle gérer sa paie elle-même ?

Oui, à condition d'avoir des barèmes à jour, des plafonds paramétrés et une réconciliation mensuelle. L'idéal pour beaucoup de PME : un outil qui calcule et trace, plus un expert-comptable qui valide — chacun son rôle.

## Conclusion

La gestion de la paie au Sénégal est un système cohérent une fois qu'on en connaît les pièces : CCNI pour le cadre, CSS et IPRES pour le social (avec leurs plafonds), IR à parts familiales, TRIMF et CFCE pour le fiscal. Une PME outillée, avec des barèmes versionnés et un pointage fiable en amont, transforme la paie d'une source de stress mensuel en processus prévisible.

Pour voir ce que donne ce cycle complet — pointage, absences, bulletin, export comptable — avec des règles Sénégal en statut pilote : [essayez Leopardo gratuitement pendant 14 jours, sans carte bancaire](/signup), ou demandez une [démo guidée](/demo). L'onboarding prend moins de 30 minutes.`,
    image: '/blog/paie-senegal.svg',
    date: new Date('2026-09-18'),
    author: {
      name: 'Fatima Dupont',
      avatar: '/avatars/fatima.svg',
    },
    category: 'Paie',
    readingTime: 12,
    tags: ['paie', 'sénégal', 'IPRES', 'CSS', 'gestion de la paie'],
  },
];

type LocalizedBlogPostFields = Pick<BlogPost, 'title' | 'excerpt' | 'content' | 'category' | 'tags'>;

const localizedBlogPosts: Partial<Record<AppLocale, Record<string, LocalizedBlogPostFields>>> = {
  en: {
    'guide-complet-gestion-rh-startup': {
      title: 'Complete guide: HR management for startups',
      excerpt: 'Build a reliable HR foundation from the first hires without slowing down growth.',
      category: 'HR',
      tags: ['startup', 'HR', 'hiring', 'operations'],
      content: `# Complete guide: HR management for startups

## Why HR matters early

Startup teams move fast, but people operations cannot stay informal forever. Clear roles, contracts, onboarding and performance rituals protect the company while helping employees understand how to succeed.

## Build the foundation

- Define the roles you need before hiring.
- Document compensation, leave and approval rules.
- Centralize employee records and access rights.
- Prepare onboarding checklists for every new joiner.

## Automate before volume arrives

Use software for attendance, leave, documents and payroll before spreadsheets become the bottleneck. Automation reduces errors and gives managers reliable data.

## Conclusion

A startup with disciplined HR foundations scales faster because every hire enters a clear, secure and measurable operating system.`,
    },
    'automatiser-paie-excel-vers-logiciel': {
      title: 'From Excel to payroll software: practical migration guide',
      excerpt: 'Move payroll from spreadsheets to automation with less risk and better traceability.',
      category: 'Payroll',
      tags: ['payroll', 'excel', 'automation', 'migration'],
      content: `# From Excel to payroll software: practical migration guide

## Why spreadsheets become risky

Payroll spreadsheets are flexible, but they are hard to audit, hard to secure and easy to break. As soon as the team grows, manual formulas create compliance and payment risks.

## Migration steps

- List every payroll input currently managed in Excel.
- Clean employee, contract and bank details.
- Run one parallel payroll cycle before switching.
- Keep a rollback export for the first production month.

## What to automate first

Start with gross-to-net calculation, payslip generation, bank exports and social declarations. These steps give the fastest reliability gain.

## Conclusion

A controlled migration turns payroll into a predictable process instead of a monthly emergency.`,
    },
    'tendances-rh-2024': {
      title: 'HR trends leaders should watch',
      excerpt: 'Hybrid work, employee experience, skills and automation are reshaping HR operations.',
      category: 'Trends',
      tags: ['trends', 'HR', 'future', 'automation'],
      content: `# HR trends leaders should watch

## The direction of modern HR

Companies need flexible work models, better employee experience and stronger operational data. HR is becoming a strategic operating system, not only an administrative function.

## Key trends

- Hybrid and distributed team management.
- Skills development and internal mobility.
- AI-assisted HR support with human validation.
- Real-time dashboards for attendance, leave and payroll.

## Conclusion

Teams that invest in structured HR data and automation will move faster with fewer compliance surprises.`,
    },
    'productivite-5-conseils-economiser-temps': {
      title: '5 ways to save time in HR administration',
      excerpt: 'Reduce repetitive HR work and give managers faster access to reliable data.',
      category: 'Productivity',
      tags: ['productivity', 'time', 'HR', 'automation'],
      content: `# 5 ways to save time in HR administration

## 1. Automate recurring tasks

Attendance imports, leave balances, payroll reports and document reminders should not depend on manual follow-up.

## 2. Centralize data

One source of truth avoids duplicate files and conflicting employee records.

## 3. Use approval workflows

Clear approvals prevent lost requests and reduce back-and-forth messages.

## 4. Give managers self-service views

Managers need team data without asking HR for every report.

## 5. Measure what slows the team

Track delays, errors and manual workload, then automate the highest-volume workflows first.`,
    },
    'conformite-rgpd-donnees-employes': {
      title: 'GDPR compliance: protecting employee data',
      excerpt: 'A practical guide to secure sensitive HR data with clear governance.',
      category: 'Security',
      tags: ['GDPR', 'privacy', 'security', 'employee data'],
      content: `# GDPR compliance: protecting employee data

## Employee data is sensitive

HR platforms process identity, payroll, contracts, performance and attendance data. This requires clear access control and traceability.

## Practical controls

- Limit access by role and tenant.
- Encrypt sensitive fields and backups.
- Keep audit logs for critical actions.
- Provide export and deletion request workflows.

## Conclusion

Privacy compliance is stronger when it is built into daily workflows instead of handled manually after incidents.`,
    },
    'culture-entreprise-engagement-employes': {
      title: 'Company culture: improving employee engagement',
      excerpt: 'Create rituals and feedback loops that help employees stay aligned and motivated.',
      category: 'Engagement',
      tags: ['culture', 'engagement', 'feedback', 'retention'],
      content: `# Company culture: improving employee engagement

## Culture is operational

Engagement grows when employees understand goals, receive feedback and see consistent management rituals.

## Practical levers

- Share priorities clearly.
- Recognize contributions often.
- Measure engagement with short surveys.
- Train managers on regular one-to-one conversations.

## Conclusion

Strong culture is built through repeatable habits, not occasional speeches.`,
    },
    'pointage-biometrique-avantages': {
      title: 'Biometric attendance: benefits and safeguards',
      excerpt: 'Understand where biometric attendance helps and which privacy controls must be in place.',
      category: 'Attendance',
      tags: ['attendance', 'biometrics', 'security', 'kiosk'],
      content: `# Biometric attendance: benefits and safeguards

## Why companies adopt it

Biometric terminals reduce buddy punching, speed up shift tracking and improve payroll accuracy.

## Required safeguards

- Obtain explicit employee consent where required.
- Limit biometric data access.
- Keep device sync logs.
- Provide fallback attendance methods.

## Conclusion

Biometric attendance is powerful when security, consent and auditability are designed from day one.`,
    },
    'gestion-absences-conges-efficace': {
      title: 'Efficient leave and absence management',
      excerpt: 'Build a clear approval workflow and avoid leave balance conflicts.',
      category: 'Leave',
      tags: ['leave', 'absence', 'approval', 'balance'],
      content: `# Efficient leave and absence management

## The common problem

Leave requests often get lost in messages, while balances become difficult to trust.

## A better workflow

- Define request, approval and cancellation rules.
- Give managers team calendars.
- Recalculate balances automatically.
- Notify employees at every status change.

## Conclusion

Clear absence workflows reduce disputes and give managers better planning visibility.`,
    },
    'recrutement-digital-sourcing-talents': {
      title: 'Digital recruiting: sourcing better talent',
      excerpt: 'Organize candidates, interviews and decisions in a measurable hiring pipeline.',
      category: 'Recruiting',
      tags: ['recruiting', 'sourcing', 'candidates', 'pipeline'],
      content: `# Digital recruiting: sourcing better talent

## Recruiting needs structure

Without a shared pipeline, candidate data spreads across emails and spreadsheets.

## What to track

- Job posts and channels.
- Candidate stages and notes.
- Interview feedback.
- Time-to-hire and offer conversion.

## Conclusion

A digital hiring pipeline improves speed, fairness and reporting.`,
    },
    'formation-developpement-competences': {
      title: 'Training and skills development',
      excerpt: 'Turn training plans into measurable skill growth for employees and managers.',
      category: 'Training',
      tags: ['training', 'skills', 'development', 'learning'],
      content: `# Training and skills development

## Why skills planning matters

Growth creates new roles and new expectations. Training plans help teams adapt before gaps become blockers.

## Build a useful program

- Map required skills by role.
- Track training attendance.
- Connect evaluations to development plans.
- Measure completion and impact.

## Conclusion

Training becomes strategic when it is linked to roles, performance and future workforce needs.`,
    },
    'guide-paie-algerie-irg-cnas': {
      title: 'Payroll in Algeria (IRG, CNAS): obligations and tools',
      excerpt: 'IRG brackets, CNAS contributions, G50 and DAS filings: a practical payroll guide for SMEs operating in Algeria.',
      category: 'Payroll',
      tags: ['payroll', 'algeria', 'IRG', 'CNAS'],
      content: `# Payroll in Algeria (IRG, CNAS): obligations and tools

Algerian payroll follows its own logic: a progressive income tax (IRG) withheld by the employer, unified social contributions through CNAS, a national minimum wage (SNMG) and a strict filing calendar (monthly G50, annual DAS). All figures below are indicative references collected in September 2026 — always verify current values with the tax administration (DGI), CNAS and your accountant.

## The legal frame

- Labour relations are governed mainly by law 90-11 (1990), sector agreements and collective bargaining.
- Legal working time: 40 hours per week; overtime must appear separately on the payslip.
- SNMG minimum wage: 20,000 DZD per month as the reference value observed in September 2026 (set in 2020 — check the value in force).
- Paid leave: generally 2.5 calendar days per month worked (30 days per year).

## Social contributions: CNAS

The reference global rate observed in September 2026 is 34.5% of the position salary: roughly 26% employer share and 9% employee share, on a base that includes salary plus most work-related allowances. Employers must register each employee with CNAS at hiring; company managers usually contribute to CASNOS instead.

## Income tax: IRG

IRG is withheld at source on a progressive monthly scale (exemption up to 30,000 DZD of taxable salary, top marginal rate 35% — references from the 2022 finance law, to be re-checked every year). Key detail: the IRG base is computed after deducting the 9% employee CNAS share. Each pay item must be qualified twice: subject to contributions or not, taxable or not.

## Filings and records

- G50 monthly tax return (IRG on salaries), in principle within the first 20 days of the following month.
- Periodic CNAS declarations and the annual DAS salary statement, which must reconcile exactly with the 12 monthly payrolls.
- Mandatory payslips, payroll journals and registers, archived for the legal retention periods.

## Spreadsheet or payroll software?

Excel works at 3 employees and breaks at 30: manual IRG scale updates, no audit trail, painful annual DAS reconciliation. A tool suited to Algeria must embed the IRG scale and CNAS rates with validity dates, qualify every pay item, and prepare G50/DAS-ready reports. See our [migration guide](/blog/automatiser-paie-excel-vers-logiciel).

## Where Leopardo fits

Leopardo is the business suite for field-based companies — HR & payroll, attendance, leave, CRM, accounting and operations. Payroll rules cover 21 countries (measured 2026-09-09), including Algeria, in **pilot status**: scales and rates are provided, versioned and tested, but final validation of each payroll run remains the responsibility of your payroll manager or accountant. We do not claim "validated legal compliance" — we provide transparent calculations, payroll exports and full tenant isolation. Compare options on our [alternatives page](/alternatives), including [Sage](/alternatives/sage).

## Conclusion

Algerian payroll is demanding but fully manageable with a rigorous pay-item referential, versioned scales and end-to-end traceability. [Try Leopardo free for 14 days, no credit card](/signup), or book a [guided demo](/demo) — onboarding takes less than 30 minutes.`,
    },
    'gerer-la-paie-au-senegal-guide-pme': {
      title: 'Managing payroll in Senegal: a practical SME guide',
      excerpt: 'IPRES, CSS, income tax with family shares, TRIMF and CFCE: what SMEs need to run clean payroll in Senegal.',
      category: 'Payroll',
      tags: ['payroll', 'senegal', 'IPRES', 'CSS'],
      content: `# Managing payroll in Senegal: a practical SME guide

Senegalese payroll combines two social funds (IPRES for pensions, CSS for family benefits and work injuries), income tax withheld at source with a family-shares system, the TRIMF flat tax and the employer-only CFCE. All rates and ceilings below are indicative references collected in September 2026 — verify current values with the DGID, IPRES, CSS and your accountant.

## The legal frame

- Labour Code (law 97-17 of 1997) plus the National Interprofessional Collective Agreement (CCNI, revised 2019) with category-based minimum wages.
- Legal working time: 40 hours per week in the general non-agricultural regime.
- Paid leave: generally 2 working days per month of service.
- The SMIG minimum wage was revalued in 2023 — check the rate in force, and the CCNI category minima which are often higher.

## Social contributions: CSS and IPRES

Reference values observed in September 2026, to confirm:

- CSS (employer only): family benefits 7% and work-injury 1%, 3% or 5% depending on risk, on a capped base (63,000 FCFA monthly ceiling as reference).
- IPRES general scheme: 14% of capped salary (8.4% employer / 5.6% employee); executives add a 6% complementary scheme on a higher ceiling.
- Health coverage runs through IPM institutions with shared contributions.

## Salary taxes: income tax, TRIMF, CFCE

Income tax is withheld at source on a progressive scale (top marginal rate around 40%, September 2026 reference) reduced through family shares — two employees with the same gross can have different nets. The TRIMF is a small monthly flat withholding. The CFCE, 3% of the taxable payroll (September 2026 reference), is borne by the employer alone and often forgotten in hiring budgets.

## Filing calendar

Monthly or quarterly payments to the DGID (income tax, TRIMF, CFCE) and to CSS/IPRES, plus annual salary statements and regularisations. The golden rule: the 12 monthly payroll journals must reconcile exactly with the annual filings.

## Tooling the payroll

Spreadsheets struggle with family shares and moving ceilings; full outsourcing cuts managers off from their own data. A good tool must embed the tax scale and family shares, manage CSS/IPRES ceilings with validity dates, and integrate actual worked hours. See our [payroll migration guide](/blog/automatiser-paie-excel-vers-logiciel).

## Where Leopardo fits

Leopardo is the business suite for field-based companies — HR & payroll, attendance, leave, CRM, accounting and operations. Payroll covers 21 countries (measured 2026-09-09), including Senegal, in **pilot status**: scales, rates and ceilings are provided, versioned and tested, while final validation of each payroll run remains the responsibility of your payroll manager or accountant. No "validated compliance" claims — traceable calculations, clean payroll exports and strict tenant isolation. Compare options on our [alternatives page](/alternatives), including [Sage](/alternatives/sage).

## Conclusion

Senegalese payroll becomes predictable with versioned scales, up-to-date ceilings and reliable attendance data upstream. [Try Leopardo free for 14 days, no credit card](/signup) or book a [guided demo](/demo) — onboarding takes less than 30 minutes.`,
    },
  },
  tr: {
    'guide-complet-gestion-rh-startup': {
      title: 'Startup lar icin IK yonetimi rehberi',
      excerpt: 'Ilk ise alimlardan itibaren saglam ve olceklenebilir bir IK temeli kurun.',
      category: 'IK',
      tags: ['startup', 'IK', 'ise alim', 'operasyon'],
      content: `# Startup lar icin IK yonetimi rehberi

## Erken donemde IK neden onemlidir

Hizli buyuyen ekiplerde roller, sozlesmeler, izin kurallari ve onboarding net olmazsa operasyon cabuk dagilir.

## Temel adimlar

- Ihtiyac duyulan rolleri ise alimdan once netlestirin.
- Maas, izin ve onay kurallarini yazili hale getirin.
- Calisan kayitlarini ve erisimleri merkezi tutun.
- Her yeni calisan icin onboarding listesi hazirlayin.

## Sonuc

Saglam IK temeli kuran startup lar daha hizli buyur ve daha az operasyon riski tasir.`,
    },
    'automatiser-paie-excel-vers-logiciel': {
      title: 'Excel den bordro yazilimina gecis rehberi',
      excerpt: 'Bordroyu elektronik tablolardan otomasyona daha az riskle tasiyin.',
      category: 'Bordro',
      tags: ['bordro', 'excel', 'otomasyon', 'gecis'],
      content: `# Excel den bordro yazilimina gecis rehberi

## Excel neden riskli hale gelir

Excel esnektir ama denetimi, guvenligi ve olceklenmesi zordur. Ekip buyudukce manuel formul hatalari artar.

## Gecis adimlari

- Mevcut bordro girdilerini listeleyin.
- Calisan, sozlesme ve banka bilgilerini temizleyin.
- Ilk ay paralel hesaplama yapin.
- Canli gecis icin geri donus dosyasi saklayin.

## Sonuc

Kontrollu gecis bordroyu aylik kriz olmaktan cikarip olculebilir surece donusturur.`,
    },
    'tendances-rh-2024': {
      title: 'IK liderlerinin izlemesi gereken trendler',
      excerpt: 'Hibrit calisma, yetenek gelisimi ve otomasyon IK operasyonlarini degistiriyor.',
      category: 'Trendler',
      tags: ['trendler', 'IK', 'gelecek', 'otomasyon'],
      content: `# IK liderlerinin izlemesi gereken trendler

## Modern IK yonu

Sirketler esnek calisma, daha iyi calisan deneyimi ve daha guclu operasyon verisi bekliyor.

## One cikan basliklar

- Hibrit ekip yonetimi.
- Yetkinlik gelisimi ve ic mobilite.
- Insan onayli yapay zeka destekli IK.
- Gercek zamanli IK panelleri.

## Sonuc

Yapisal IK verisine yatirim yapan ekipler daha hizli ve daha guvenli olceklenir.`,
    },
    'productivite-5-conseils-economiser-temps': {
      title: 'IK operasyonunda zaman kazandiran 5 yol',
      excerpt: 'Tekrarlayan isleri azaltin ve yoneticilere guvenilir veriye hizli erisim verin.',
      category: 'Verimlilik',
      tags: ['verimlilik', 'zaman', 'IK', 'otomasyon'],
      content: `# IK operasyonunda zaman kazandiran 5 yol

## Otomasyonla baslayin

Devam verisi, izin bakiyesi, bordro raporu ve belge hatirlatmalari manuel takip edilmemelidir.

## Veriyi merkezilestirin

Tek kaynak, tekrar dosyalari ve celisen kayitlari azaltir.

## Onay akislarini netlestirin

Net onaylar kaybolan talepleri ve mesaj trafigini azaltir.

## Sonuc

En cok tekrar eden akislar otomatiklestiginde IK stratejik ise daha fazla zaman ayirir.`,
    },
    'conformite-rgpd-donnees-employes': {
      title: 'KVKK/GDPR: calisan verilerini koruma',
      excerpt: 'Hassas IK verilerini net yonetisim ve guvenlik kontrolleriyle koruyun.',
      category: 'Guvenlik',
      tags: ['gizlilik', 'guvenlik', 'calisan verisi', 'uyum'],
      content: `# KVKK/GDPR: calisan verilerini koruma

## IK verisi hassastir

Kimlik, bordro, sozlesme, performans ve devam verileri guclu erisim kontrolu gerektirir.

## Pratik kontroller

- Erisimi role ve tenant a gore sinirlayin.
- Hassas alanlari sifreleyin.
- Kritik islemleri denetim kaydina alin.
- Veri dis aktarim ve silme taleplerini yonetin.

## Sonuc

Gizlilik gunluk is akislarina gomuldugunde daha guvenilir hale gelir.`,
    },
    'culture-entreprise-engagement-employes': {
      title: 'Sirket kulturu ve calisan bagliligi',
      excerpt: 'Calisanlari hizali ve motive tutan rituel ve geri bildirim donguleri kurun.',
      category: 'Baglilik',
      tags: ['kultur', 'baglilik', 'geri bildirim', 'elde tutma'],
      content: `# Sirket kulturu ve calisan bagliligi

## Kultur operasyoneldir

Baglilik, hedefler net oldugunda, geri bildirim duzenli verildiginde ve yonetim ritulleri tutarli oldugunda artar.

## Pratik kaldiraclar

- Oncelikleri acik paylasin.
- Katkilari duzenli takdir edin.
- Kisa anketlerle bagliligi olcun.
- Yoneticileri bire bir gorusmelere hazirlayin.

## Sonuc

Guclu kultur tek seferlik mesajlarla degil, tekrarlanabilir aliskanliklarla kurulur.`,
    },
    'pointage-biometrique-avantages': {
      title: 'Biyometrik devam takibi: faydalar ve korumalar',
      excerpt: 'Biyometrik takibin nerede deger kattigini ve hangi gizlilik kontrollerinin gerektigini anlayin.',
      category: 'Devam',
      tags: ['devam', 'biyometri', 'guvenlik', 'kiosk'],
      content: `# Biyometrik devam takibi: faydalar ve korumalar

## Neden kullanilir

Biyometrik cihazlar baskasi yerine giris yapmayi azaltir, vardiya takibini hizlandirir ve bordro dogrulugunu artirir.

## Gerekli korumalar

- Gerekli durumlarda acik riza alin.
- Biyometrik veriye erisimi sinirlayin.
- Cihaz senkronizasyon loglarini tutun.
- Alternatif devam yontemleri saglayin.

## Sonuc

Biyometrik takip, guvenlik ve denetlenebilirlik bastan tasarlandiginda guclu bir arac olur.`,
    },
    'gestion-absences-conges-efficace': {
      title: 'Etkili izin ve devamsizlik yonetimi',
      excerpt: 'Net onay akisi kurun ve izin bakiyesi uyusmazliklarini azaltin.',
      category: 'Izin',
      tags: ['izin', 'devamsizlik', 'onay', 'bakiye'],
      content: `# Etkili izin ve devamsizlik yonetimi

## Yaygin sorun

Izin talepleri mesajlarda kaybolur, bakiyeler ise zamanla guvenilmez hale gelir.

## Daha iyi akis

- Talep, onay ve iptal kurallarini belirleyin.
- Yoneticilere ekip takvimi verin.
- Bakiyeleri otomatik guncelleyin.
- Her durum degisikliginde calisani bilgilendirin.

## Sonuc

Net izin akislarindan hem calisan hem yonetici kazanir.`,
    },
    'recrutement-digital-sourcing-talents': {
      title: 'Dijital ise alim: daha iyi yetenek bulma',
      excerpt: 'Adaylari, mulakatlari ve karar sureclerini olculebilir bir pipeline icinde yonetin.',
      category: 'Ise alim',
      tags: ['ise alim', 'aday', 'pipeline', 'sourcing'],
      content: `# Dijital ise alim: daha iyi yetenek bulma

## Ise alim yapisal veri ister

Ortak pipeline olmazsa aday verisi e-posta ve dosyalara dagilir.

## Ne izlenmeli

- Ilanlar ve kaynak kanallari.
- Aday asamalari ve notlar.
- Mulakat geri bildirimleri.
- Ise alim suresi ve teklif donusumu.

## Sonuc

Dijital pipeline hiz, adalet ve raporlama kalitesini artirir.`,
    },
    'formation-developpement-competences': {
      title: 'Egitim ve yetkinlik gelisimi',
      excerpt: 'Egitim planlarini calisan ve yoneticiler icin olculebilir yetkinlik gelisimine donusturun.',
      category: 'Egitim',
      tags: ['egitim', 'yetkinlik', 'gelisim', 'ogrenme'],
      content: `# Egitim ve yetkinlik gelisimi

## Neden onemli

Buyume yeni roller ve yeni beklentiler getirir. Egitim planlari eksikler sorun olmadan once ekipleri hazirlar.

## Program kurmak

- Role gore gerekli yetkinlikleri haritalayin.
- Egitim katilimini izleyin.
- Degerlendirmeleri gelisim planlarina baglayin.
- Tamamlama ve etkiyi olcun.

## Sonuc

Egitim, roller ve performansla baglandiginda stratejik hale gelir.`,
    },
    'guide-paie-algerie-irg-cnas': {
      title: 'Cezayir de bordro (IRG, CNAS): yukumlulukler ve araclar',
      excerpt: 'IRG dilimleri, CNAS primleri, G50 ve DAS bildirimleri: Cezayir de faaliyet gosteren KOBI ler icin pratik bordro rehberi.',
      category: 'Bordro',
      tags: ['bordro', 'cezayir', 'IRG', 'CNAS'],
      content: `# Cezayir de bordro (IRG, CNAS): yukumlulukler ve araclar

Cezayir bordrosunun kendine ozgu kurallari vardir: isveren tarafindan kaynagta kesilen artan oranli gelir vergisi (IRG), CNAS uzerinden toplanan sosyal primler, ulusal asgari ucret (SNMG) ve siki bir bildirim takvimi (aylik G50, yillik DAS). Asagidaki tum degerler Eylul 2026 da derlenen gosterge niteliginde referanslardir — guncel degerleri vergi idaresi (DGI), CNAS ve muhasebecinizle dogrulayin.

## Yasal cerceve

- Is iliskileri temel olarak 90-11 sayili kanunla (1990) duzenlenir.
- Yasal calisma suresi haftada 40 saattir; fazla mesai bordroda ayri gosterilmelidir.
- SNMG asgari ucreti: Eylul 2026 itibariyla referans deger ayda 20.000 DZD (2020 de belirlendi — yururlukteki degeri kontrol edin).
- Yillik ucretli izin: genel kural olarak calisilan ay basina 2,5 takvim gunu.

## Sosyal primler: CNAS

Eylul 2026 referans toplam orani pozisyon maasinin %34,5 idir: yaklasik %26 isveren payi ve %9 calisan payi. Her calisan ise alimda CNAS a kaydedilmelidir; sirket yoneticileri genellikle CASNOS a baglidir.

## Gelir vergisi: IRG

IRG artan oranli aylik tarifeyle kaynagta kesilir (vergiye tabi maasin 30.000 DZD ye kadari muaf, en ust dilim %35 — 2022 butce kanunu referanslari, her yil yeniden kontrol edilmelidir). Kritik nokta: IRG matrahi, %9 luk calisan CNAS payi dusuldukten sonra hesaplanir. Her bordro kalemi iki kez nitelendirilmelidir: prime tabi mi, vergiye tabi mi.

## Bildirimler ve kayitlar

- Aylik G50 vergi bildirimi (maas IRG si), kural olarak izleyen ayin ilk 20 gunu icinde.
- Donemsel CNAS bildirimleri ve 12 aylik bordroyla birebir uyusan yillik DAS beyani.
- Zorunlu bordrolar, ucret defterleri ve yasal saklama sureleri.

## Excel mi bordro yazilimi mi?

Excel 3 calisanda calisir, 30 da kirilir: elle tarife guncellemeleri, denetim izi yok, yillik DAS mutabakati cok zor. Cezayir e uygun bir arac IRG tarifesini ve CNAS oranlarini gecerlilik tarihleriyle icermeli, her kalemi nitelendirmeli ve G50/DAS raporlarini hazirlamalidir. [Excel den bordro yazilimina gecis rehberimize](/blog/automatiser-paie-excel-vers-logiciel) bakin.

## Leopardo nun yaklasimi

Leopardo, saha ekipleri icin isletme yonetimi paketidir — IK ve bordro, yoklama, izin, CRM, muhasebe ve saha operasyonlari. Bordro kurallari Cezayir dahil 21 ulkeyi kapsar (olcum 2026-09-09) ve **pilot statusundedir**: tarifeler ve oranlar saglanir, surumlenir ve test edilir; ancak her bordro dongusunun nihai dogrulamasi bordro yoneticinizin veya muhasebecinizin sorumlulugundadir. "Dogrulanmis yasal uyumluluk" iddiasinda bulunmuyoruz — seffaf hesaplamalar, bordro disa aktarimlari ve tam tenant izolasyonu sagliyoruz. Secenekleri [alternatifler sayfamizda](/alternatives) karsilastirin, [Sage](/alternatives/sage) dahil.

## Sonuc

Cezayir bordrosu titiz bir kalem referansi, surumlenmis tarifeler ve uctan uca izlenebilirlikle tamamen yonetilebilir. [Leopardo yu 14 gun ucretsiz deneyin, kredi karti gerekmez](/signup) veya [rehberli demo](/demo) ayirtin — kurulum 30 dakikadan kisa surer.`,
    },
    'gerer-la-paie-au-senegal-guide-pme': {
      title: 'Senegal de bordro yonetimi: KOBI ler icin pratik rehber',
      excerpt: 'IPRES, CSS, aile paylari ile gelir vergisi, TRIMF ve CFCE: Senegal de temiz bordro icin KOBI lerin bilmesi gerekenler.',
      category: 'Bordro',
      tags: ['bordro', 'senegal', 'IPRES', 'CSS'],
      content: `# Senegal de bordro yonetimi: KOBI ler icin pratik rehber

Senegal bordrosu iki sosyal fonu (emeklilik icin IPRES, aile yardimlari ve is kazalari icin CSS), aile paylari sistemiyle kaynagta kesilen gelir vergisini, TRIMF sabit vergisini ve yalnizca isverene ait CFCE yi birlestirir. Asagidaki oranlar ve tavanlar Eylul 2026 da derlenen gosterge referanslardir — guncel degerleri DGID, IPRES, CSS ve muhasebecinizle dogrulayin.

## Yasal cerceve

- Is Kanunu (1997 tarihli 97-17 sayili kanun) ve kategori bazli asgari ucretler iceren ulusal toplu sozlesme (CCNI, 2019 revizyonu).
- Yasal calisma suresi: genel rejimde haftada 40 saat.
- Ucretli izin: genel olarak her hizmet ayi icin 2 is gunu.
- SMIG asgari ucreti 2023 te yeniden degerlendi — yururlukteki orani ve genellikle daha yuksek olan CCNI kategori minimumlarini kontrol edin.

## Sosyal primler: CSS ve IPRES

Eylul 2026 referans degerleri, teyit edilmelidir:

- CSS (yalnizca isveren): aile yardimlari %7 ve riske gore %1, %3 veya %5 is kazasi primi, tavanli matrah uzerinden (referans aylik tavan 63.000 FCFA).
- IPRES genel rejim: tavanli maasin %14 u (%8,4 isveren / %5,6 calisan); kadrolar icin daha yuksek tavanli %6 lik tamamlayici rejim eklenir.
- Saglik kapsami, paylasilan primlerle IPM kurumlari uzerinden yurur.

## Maas vergileri: gelir vergisi, TRIMF, CFCE

Gelir vergisi artan oranli tarifeyle kaynagta kesilir (en ust dilim yaklasik %40, Eylul 2026 referansi) ve aile paylariyla azaltilir — ayni brute sahip iki calisanin neti farkli olabilir. TRIMF kucuk bir aylik sabit kesintidir. CFCE, vergiye tabi ucret kutlesinin %3 u (Eylul 2026 referansi), yalnizca isverene aittir ve ise alim butcelerinde siklikla unutulur.

## Bildirim takvimi

DGID ye (gelir vergisi, TRIMF, CFCE) ve CSS/IPRES e aylik veya uc aylik odemeler, arti yillik maas beyanlari ve duzeltmeler. Altin kural: 12 aylik bordro defteri yillik beyanlarla birebir uyusmalidir.

## Bordroyu araclarla yonetmek

Elektronik tablolar aile paylari ve degisen tavanlarla zorlanir; tam dis kaynak kullanimi yoneticiyi kendi verisinden koparir. Iyi bir arac vergi tarifesini ve aile paylarini icermeli, CSS/IPRES tavanlarini gecerlilik tarihleriyle yonetmeli ve gercek calisma saatlerini entegre etmelidir. [Bordro gecis rehberimize](/blog/automatiser-paie-excel-vers-logiciel) bakin.

## Leopardo nun yaklasimi

Leopardo, saha ekipleri icin isletme yonetimi paketidir — IK ve bordro, yoklama, izin, CRM, muhasebe ve saha operasyonlari. Bordro Senegal dahil 21 ulkeyi kapsar (olcum 2026-09-09) ve **pilot statusundedir**: tarifeler, oranlar ve tavanlar saglanir, surumlenir ve test edilir; her bordro dongusunun nihai dogrulamasi bordro yoneticinizin veya muhasebecinizin sorumlulugundadir. "Dogrulanmis uyumluluk" iddiasi yok — izlenebilir hesaplamalar, temiz bordro disa aktarimlari ve siki tenant izolasyonu var. Secenekleri [alternatifler sayfamizda](/alternatives) karsilastirin, [Sage](/alternatives/sage) dahil.

## Sonuc

Surumlenmis tarifeler, guncel tavanlar ve guvenilir yoklama verisiyle Senegal bordrosu ongorulebilir hale gelir. [Leopardo yu 14 gun ucretsiz deneyin, kredi karti gerekmez](/signup) veya [rehberli demo](/demo) talep edin — kurulum 30 dakikadan kisa surer.`,
    },
  },
  ar: {
    'guide-complet-gestion-rh-startup': {
      title: 'دليل إدارة الموارد البشرية للشركات الناشئة',
      excerpt: 'ابن أساسا منظما للموارد البشرية منذ أول توظيف وبدون إبطاء النمو.',
      category: 'الموارد البشرية',
      tags: ['شركة ناشئة', 'موارد بشرية', 'توظيف', 'عمليات'],
      content: `# دليل إدارة الموارد البشرية للشركات الناشئة

## لماذا تبدأ مبكرا

عندما ينمو الفريق بسرعة تصبح العقود والأدوار والإجازات والتأهيل عناصر ضرورية لحماية الشركة والموظفين.

## الأساس العملي

- حدد الأدوار قبل التوظيف.
- وثق قواعد الرواتب والإجازات والموافقات.
- اجعل ملفات الموظفين والصلاحيات مركزية.
- جهز قائمة تأهيل لكل موظف جديد.

## الخلاصة

الشركة الناشئة التي تبني أساسا واضحا للموارد البشرية تتوسع بسرعة وبمخاطر أقل.`,
    },
    'automatiser-paie-excel-vers-logiciel': {
      title: 'من Excel إلى برنامج رواتب: دليل الانتقال',
      excerpt: 'انقل الرواتب من الجداول اليدوية إلى الأتمتة مع تتبع أفضل ومخاطر أقل.',
      category: 'الرواتب',
      tags: ['رواتب', 'Excel', 'أتمتة', 'انتقال'],
      content: `# من Excel إلى برنامج رواتب: دليل الانتقال

## لماذا تصبح الجداول خطرة

الجداول مرنة لكنها صعبة التدقيق والحماية. ومع نمو الفريق تزيد أخطاء الصيغ والحسابات.

## خطوات الانتقال

- احصر كل بيانات الرواتب الحالية.
- نظف بيانات الموظفين والعقود والحسابات البنكية.
- شغل دورة رواتب موازية قبل الاعتماد الكامل.
- احتفظ بملف رجوع للشهر الأول.

## الخلاصة

الانتقال المنظم يجعل الرواتب عملية قابلة للتوقع بدل أزمة شهرية.`,
    },
    'tendances-rh-2024': {
      title: 'اتجاهات الموارد البشرية التي يجب مراقبتها',
      excerpt: 'العمل الهجين، تجربة الموظف، المهارات والأتمتة تعيد تشكيل عمليات الموارد البشرية.',
      category: 'اتجاهات',
      tags: ['اتجاهات', 'موارد بشرية', 'مستقبل', 'أتمتة'],
      content: `# اتجاهات الموارد البشرية التي يجب مراقبتها

## اتجاه الموارد البشرية الحديثة

تحتاج الشركات إلى نماذج عمل مرنة وتجربة موظف أفضل وبيانات تشغيلية أدق.

## أهم الاتجاهات

- إدارة الفرق الهجينة والموزعة.
- تطوير المهارات والتنقل الداخلي.
- دعم موارد بشرية بالذكاء الاصطناعي مع تحقق بشري.
- لوحات بيانات فورية للحضور والإجازات والرواتب.

## الخلاصة

الفرق التي تبني بيانات منظمة وأتمتة قوية ستنمو بسرعة وبمفاجآت أقل.`,
    },
    'productivite-5-conseils-economiser-temps': {
      title: '5 طرق لتوفير الوقت في إدارة الموارد البشرية',
      excerpt: 'قلل الأعمال المتكررة وامنح المديرين وصولا أسرع إلى بيانات موثوقة.',
      category: 'الإنتاجية',
      tags: ['إنتاجية', 'وقت', 'موارد بشرية', 'أتمتة'],
      content: `# 5 طرق لتوفير الوقت في إدارة الموارد البشرية

## أتمتة المهام المتكررة

الحضور، أرصدة الإجازات، تقارير الرواتب وتذكيرات المستندات لا يجب أن تعتمد على المتابعة اليدوية.

## مركزية البيانات

مصدر واحد للحقيقة يقلل الملفات المكررة والسجلات المتضاربة.

## مسارات موافقة واضحة

الموافقات الواضحة تقلل الطلبات الضائعة والرسائل المتكررة.

## الخلاصة

عندما تتم أتمتة أكثر الأعمال تكرارا يستطيع فريق الموارد البشرية التركيز على العمل الاستراتيجي.`,
    },
    'conformite-rgpd-donnees-employes': {
      title: 'الامتثال للخصوصية وحماية بيانات الموظفين',
      excerpt: 'دليل عملي لحماية بيانات الموارد البشرية الحساسة بحوكمة واضحة.',
      category: 'الأمان',
      tags: ['خصوصية', 'أمان', 'بيانات الموظفين', 'امتثال'],
      content: `# الامتثال للخصوصية وحماية بيانات الموظفين

## بيانات الموظفين حساسة

تعالج أنظمة الموارد البشرية الهوية والرواتب والعقود والأداء والحضور، ولذلك تحتاج إلى صلاحيات دقيقة.

## ضوابط عملية

- حدد الوصول حسب الدور والشركة.
- شفر الحقول الحساسة والنسخ الاحتياطية.
- احتفظ بسجلات تدقيق للأفعال المهمة.
- وفر مسارات تصدير وحذف البيانات.

## الخلاصة

تصبح الخصوصية أقوى عندما تكون جزءا من العمل اليومي وليس إجراء بعد الحوادث.`,
    },
    'culture-entreprise-engagement-employes': {
      title: 'ثقافة الشركة ورفع تفاعل الموظفين',
      excerpt: 'ابن عادات واضحة وتغذية راجعة منتظمة تساعد الموظفين على البقاء منسجمين ومتحمسين.',
      category: 'التفاعل',
      tags: ['ثقافة', 'تفاعل', 'تغذية راجعة', 'احتفاظ'],
      content: `# ثقافة الشركة ورفع تفاعل الموظفين

## الثقافة ممارسة يومية

يزداد التفاعل عندما يفهم الموظفون الأهداف ويتلقون ملاحظات منتظمة ويرون طقوس إدارة ثابتة.

## أدوات عملية

- شارك الأولويات بوضوح.
- قدر المساهمات بانتظام.
- قس التفاعل باستبيانات قصيرة.
- درب المديرين على لقاءات فردية منتظمة.

## الخلاصة

الثقافة القوية تبنى بالعادات المتكررة وليس بالخطابات فقط.`,
    },
    'pointage-biometrique-avantages': {
      title: 'الحضور البيومتري: الفوائد والضوابط',
      excerpt: 'افهم أين يفيد الحضور البيومتري وما ضوابط الخصوصية المطلوبة.',
      category: 'الحضور',
      tags: ['حضور', 'بيومتري', 'أمان', 'كشك'],
      content: `# الحضور البيومتري: الفوائد والضوابط

## لماذا تعتمد عليه الشركات

تقلل الأجهزة البيومترية تسجيل الحضور بالنيابة، وتسرع تتبع المناوبات، وتحسن دقة الرواتب.

## ضوابط ضرورية

- احصل على موافقة صريحة عند الحاجة.
- قلل الوصول إلى البيانات البيومترية.
- احتفظ بسجلات مزامنة الأجهزة.
- وفر طرق حضور بديلة.

## الخلاصة

يكون الحضور البيومتري فعالا عندما تصمم الخصوصية والتدقيق منذ البداية.`,
    },
    'gestion-absences-conges-efficace': {
      title: 'إدارة فعالة للإجازات والغيابات',
      excerpt: 'ابن مسار موافقة واضحا وتجنب تضارب أرصدة الإجازات.',
      category: 'الإجازات',
      tags: ['إجازات', 'غياب', 'موافقة', 'رصيد'],
      content: `# إدارة فعالة للإجازات والغيابات

## المشكلة الشائعة

تضيع طلبات الإجازة في الرسائل وتصبح الأرصدة غير موثوقة مع الوقت.

## مسار أفضل

- حدد قواعد الطلب والموافقة والإلغاء.
- وفر للمديرين تقويم فريق واضحا.
- حدث الأرصدة آليا.
- أبلغ الموظف بكل تغيير في الحالة.

## الخلاصة

مسارات الإجازة الواضحة تقلل النزاعات وتحسن التخطيط.`,
    },
    'recrutement-digital-sourcing-talents': {
      title: 'التوظيف الرقمي وجذب المواهب',
      excerpt: 'نظم المرشحين والمقابلات والقرارات داخل مسار توظيف قابل للقياس.',
      category: 'التوظيف',
      tags: ['توظيف', 'مرشحون', 'مصادر', 'مسار'],
      content: `# التوظيف الرقمي وجذب المواهب

## التوظيف يحتاج تنظيما

بدون مسار مشترك تتوزع بيانات المرشحين بين البريد والجداول.

## ما يجب تتبعه

- الإعلانات وقنوات المصدر.
- مراحل المرشحين والملاحظات.
- ملاحظات المقابلات.
- مدة التوظيف ونسبة قبول العروض.

## الخلاصة

مسار التوظيف الرقمي يحسن السرعة والعدالة وجودة التقارير.`,
    },
    'formation-developpement-competences': {
      title: 'التكوين وتطوير المهارات',
      excerpt: 'حوّل خطط التكوين إلى نمو مهارات قابل للقياس للموظفين والمديرين.',
      category: 'التكوين',
      tags: ['تكوين', 'مهارات', 'تطوير', 'تعلم'],
      content: `# التكوين وتطوير المهارات

## لماذا التخطيط للمهارات مهم

النمو يخلق أدوارا وتوقعات جديدة. تساعد خطط التكوين الفرق على الاستعداد قبل ظهور الفجوات.

## بناء برنامج مفيد

- اربط المهارات المطلوبة بكل دور.
- تتبع حضور التكوين.
- صل التقييمات بخطط التطوير.
- قس الإنجاز والأثر.

## الخلاصة

يصبح التكوين استراتيجيا عندما يرتبط بالأدوار والأداء واحتياجات المستقبل.`,
    },
    'guide-paie-algerie-irg-cnas': {
      title: 'دليل الرواتب في الجزائر (IRG وCNAS): الالتزامات والأدوات',
      excerpt: 'شرائح IRG واشتراكات CNAS وتصريحات G50 وDAS: دليل عملي لإدارة الرواتب للشركات الصغيرة والمتوسطة في الجزائر.',
      category: 'الرواتب',
      tags: ['رواتب', 'الجزائر', 'IRG', 'CNAS'],
      content: `# دليل الرواتب في الجزائر (IRG وCNAS): الالتزامات والأدوات

للرواتب في الجزائر منطقها الخاص: ضريبة دخل تصاعدية (IRG) يقتطعها صاحب العمل من المصدر، واشتراكات اجتماعية موحدة عبر CNAS، وأجر وطني أدنى مضمون (SNMG)، وجدول تصريحات صارم (G50 شهريا وDAS سنويا). جميع الأرقام أدناه مراجع إرشادية جُمعت في سبتمبر 2026 — تحقق دائما من القيم السارية لدى إدارة الضرائب وCNAS ومحاسبك.

## الإطار القانوني

- تخضع علاقات العمل أساسا للقانون 90-11 لسنة 1990 والاتفاقيات الجماعية.
- مدة العمل القانونية: 40 ساعة في الأسبوع؛ ويجب إظهار الساعات الإضافية بشكل منفصل في كشف الراتب.
- الأجر الأدنى SNMG: القيمة المرجعية المسجلة في سبتمبر 2026 هي 20000 دينار جزائري شهريا (حُددت سنة 2020 — تحقق من القيمة السارية).
- العطلة المدفوعة: كقاعدة عامة 2.5 يوم تقويمي عن كل شهر عمل.

## الاشتراكات الاجتماعية: CNAS

النسبة الإجمالية المرجعية المسجلة في سبتمبر 2026 هي 34.5% من أجر المنصب: نحو 26% حصة صاحب العمل و9% حصة العامل. يجب تسجيل كل عامل لدى CNAS عند التوظيف؛ أما المسيرون فيخضعون عادة لنظام CASNOS.

## ضريبة الدخل: IRG

تُقتطع IRG من المصدر وفق جدول شهري تصاعدي (إعفاء حتى 30000 دينار من الأجر الخاضع، وأعلى شريحة 35% — مراجع قانون المالية 2022، تُراجع كل سنة). النقطة الحاسمة: وعاء IRG يُحسب بعد خصم حصة العامل في CNAS البالغة 9%. ويجب توصيف كل بند من بنود الراتب مرتين: خاضع للاشتراكات أم لا، خاضع للضريبة أم لا.

## التصريحات والسجلات

- تصريح G50 الشهري (ضريبة الرواتب) خلال العشرين يوما الأولى من الشهر الموالي كقاعدة عامة.
- تصريحات CNAS الدورية والتصريح السنوي بالأجور DAS الذي يجب أن يطابق تماما رواتب الأشهر الاثني عشر.
- كشوف رواتب ودفاتر وسجلات إلزامية تُحفظ طوال المدد القانونية.

## جدول بيانات أم برنامج رواتب؟

يصلح Excel مع 3 موظفين وينهار مع 30: تحديثات يدوية للجداول، لا أثر تدقيق، ومطابقة سنوية شاقة لتصريح DAS. الأداة المناسبة للجزائر يجب أن تتضمن جدول IRG ونسب CNAS بتواريخ سريانها، وتوصّف كل بند، وتُحضّر تقارير G50 وDAS. راجع [دليل الانتقال من Excel](/blog/automatiser-paie-excel-vers-logiciel).

## مقاربة ليوباردو

ليوباردو حزمة الأعمال للشركات الميدانية — الموارد البشرية والرواتب، الحضور، الإجازات، إدارة العملاء، المحاسبة والعمليات الميدانية. تغطي قواعد الرواتب 21 بلدا (قياس 2026-09-09) من بينها الجزائر، وهي في **وضع تجريبي (pilot)**: الجداول والنسب متوفرة ومُدارة بالإصدارات ومُختبرة، لكن المصادقة النهائية على كل دورة رواتب تبقى من مسؤولية مسيّر الرواتب أو المحاسب لديك. لا ندّعي «امتثالا قانونيا مصادقا عليه» — نوفر حسابات شفافة وتصدير رواتب وعزلا كاملا لبيانات كل شركة. قارن الخيارات عبر [صفحة البدائل](/alternatives) بما فيها [Sage](/alternatives/sage).

## الخلاصة

رواتب الجزائر قابلة للإدارة تماما بمرجع بنود دقيق وجداول مُدارة بالإصدارات وتتبع من طرف إلى طرف. [جرّب ليوباردو مجانا لمدة 14 يوما دون بطاقة بنكية](/signup) أو احجز [عرضا موجها](/demo) — الانطلاق يستغرق أقل من 30 دقيقة.`,
    },
    'gerer-la-paie-au-senegal-guide-pme': {
      title: 'إدارة الرواتب في السنغال: دليل عملي للشركات الصغيرة والمتوسطة',
      excerpt: 'IPRES وCSS وضريبة الدخل بالأنصبة العائلية وTRIMF وCFCE: ما تحتاجه الشركات الصغيرة والمتوسطة لرواتب سليمة في السنغال.',
      category: 'الرواتب',
      tags: ['رواتب', 'السنغال', 'IPRES', 'CSS'],
      content: `# إدارة الرواتب في السنغال: دليل عملي للشركات الصغيرة والمتوسطة

تجمع رواتب السنغال بين صندوقين اجتماعيين (IPRES للتقاعد وCSS للمنح العائلية وحوادث العمل)، وضريبة دخل تُقتطع من المصدر بنظام الأنصبة العائلية، وضريبة TRIMF الجزافية، ومساهمة CFCE التي يتحملها صاحب العمل وحده. النسب والسقوف أدناه مراجع إرشادية جُمعت في سبتمبر 2026 — تحقق من القيم السارية لدى DGID وIPRES وCSS ومحاسبك.

## الإطار القانوني

- مدونة الشغل (القانون 97-17 لسنة 1997) والاتفاقية الجماعية الوطنية المهنية (CCNI، مراجعة 2019) بحدود دنيا للأجور حسب الفئات.
- مدة العمل القانونية: 40 ساعة أسبوعيا في النظام العام.
- العطلة المدفوعة: يومان من أيام العمل عن كل شهر خدمة كقاعدة عامة.
- أُعيد تقييم الأجر الأدنى SMIG سنة 2023 — تحقق من المعدل الساري ومن الحدود الدنيا الاتفاقية التي كثيرا ما تكون أعلى.

## الاشتراكات الاجتماعية: CSS وIPRES

قيم مرجعية مسجلة في سبتمبر 2026، تحتاج إلى تأكيد:

- CSS (على صاحب العمل وحده): المنح العائلية 7% وحوادث العمل 1% أو 3% أو 5% حسب الخطر، على وعاء مسقوف (سقف شهري مرجعي 63000 فرنك).
- النظام العام لـIPRES: 14% من الأجر المسقوف (8.4% صاحب العمل / 5.6% العامل)؛ ويضاف للإطارات نظام تكميلي بنسبة 6% بسقف أعلى.
- التغطية الصحية عبر مؤسسات IPM باشتراكات مشتركة.

## الضرائب على الأجور: ضريبة الدخل وTRIMF وCFCE

تُقتطع ضريبة الدخل من المصدر وفق جدول تصاعدي (أعلى شريحة نحو 40%، مرجع سبتمبر 2026) وتُخفَّض عبر الأنصبة العائلية — عاملان بنفس الأجر الإجمالي قد يختلف صافيهما. TRIMF اقتطاع شهري جزافي صغير. أما CFCE فتبلغ 3% من كتلة الأجور الخاضعة (مرجع سبتمبر 2026) ويتحملها صاحب العمل وحده وكثيرا ما تُنسى في ميزانيات التوظيف.

## جدول التصريحات

مدفوعات شهرية أو فصلية لفائدة DGID (ضريبة الدخل وTRIMF وCFCE) ولفائدة CSS وIPRES، إضافة إلى البيانات السنوية للأجور والتسويات. القاعدة الذهبية: يجب أن تتطابق دفاتر الرواتب الاثني عشر تماما مع التصريحات السنوية.

## أدوات إدارة الرواتب

تتعثر جداول البيانات مع الأنصبة العائلية والسقوف المتغيرة؛ والاستعانة الكاملة بمصادر خارجية تقطع المسيّر عن بياناته. الأداة الجيدة يجب أن تتضمن جدول الضريبة والأنصبة العائلية، وتدير سقوف CSS وIPRES بتواريخ سريانها، وتدمج ساعات العمل الفعلية. راجع [دليل الانتقال إلى برنامج رواتب](/blog/automatiser-paie-excel-vers-logiciel).

## مقاربة ليوباردو

ليوباردو حزمة الأعمال للشركات الميدانية — الموارد البشرية والرواتب، الحضور، الإجازات، إدارة العملاء، المحاسبة والعمليات الميدانية. تغطي الرواتب 21 بلدا (قياس 2026-09-09) من بينها السنغال، وهي في **وضع تجريبي (pilot)**: الجداول والنسب والسقوف متوفرة ومُدارة بالإصدارات ومُختبرة، والمصادقة النهائية على كل دورة رواتب من مسؤولية مسيّر الرواتب أو المحاسب لديك. لا ادعاء بـ«امتثال مصادق عليه» — بل حسابات قابلة للتتبع وتصدير رواتب نظيف وعزل صارم لبيانات كل شركة. قارن الخيارات عبر [صفحة البدائل](/alternatives) بما فيها [Sage](/alternatives/sage).

## الخلاصة

بجداول مُدارة بالإصدارات وسقوف محدّثة وبيانات حضور موثوقة، تصبح رواتب السنغال عملية قابلة للتنبؤ. [جرّب ليوباردو مجانا لمدة 14 يوما دون بطاقة بنكية](/signup) أو اطلب [عرضا موجها](/demo) — الانطلاق يستغرق أقل من 30 دقيقة.`,
    },
  },
};

export function getBlogPosts(locale: AppLocale): BlogPost[] {
  const overrides = localizedBlogPosts[locale];

  if (!overrides) {
    return blogPosts;
  }

  return blogPosts.map((post) => ({
    ...post,
    ...(overrides[post.slug] ?? {}),
  }));
}

export function getBlogPost(slug: string, locale: AppLocale): BlogPost | undefined {
  return getBlogPosts(locale).find((post) => post.slug === slug);
}
