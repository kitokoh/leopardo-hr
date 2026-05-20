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
    image: '/blog/startup-rh.jpg',
    date: new Date('2024-01-15'),
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.jpg',
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
    image: '/blog/paie-excel.jpg',
    date: new Date('2024-01-10'),
    author: {
      name: 'Jean Martin',
      avatar: '/avatars/jean.jpg',
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
    image: '/blog/tendances-rh.jpg',
    date: new Date('2024-01-05'),
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.jpg',
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
    image: '/blog/productivite.jpg',
    date: new Date('2023-12-28'),
    author: {
      name: 'Fatima Dupont',
      avatar: '/avatars/fatima.jpg',
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
    image: '/blog/rgpd.jpg',
    date: new Date('2023-12-20'),
    author: {
      name: 'Jean Martin',
      avatar: '/avatars/jean.jpg',
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
    image: '/blog/culture.jpg',
    date: new Date('2023-12-15'),
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.jpg',
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
    image: '/blog/biometrique.jpg',
    date: new Date('2023-12-10'),
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.jpg',
    },
    category: 'Technologie',
    readingTime: 7,
    tags: ['biométrie', 'pointage', 'technologie', 'sécurité'],
  },
  {
    slug: 'gestion-absences-congés-efficace',
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
    image: '/blog/absences.jpg',
    date: new Date('2023-12-05'),
    author: {
      name: 'Fatima Dupont',
      avatar: '/avatars/fatima.jpg',
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
    image: '/blog/recrutement.jpg',
    date: new Date('2023-11-30'),
    author: {
      name: 'Sophie Bernard',
      avatar: '/avatars/sophie.jpg',
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
    image: '/blog/formation.jpg',
    date: new Date('2023-11-25'),
    author: {
      name: 'Ahmed Benali',
      avatar: '/avatars/ahmed.jpg',
    },
    category: 'Développement',
    readingTime: 7,
    tags: ['formation', 'développement', 'compétences', 'apprentissage'],
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
    'gestion-absences-congés-efficace': {
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
    'gestion-absences-congés-efficace': {
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
    'gestion-absences-congés-efficace': {
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
