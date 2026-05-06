import { Clock, Users, Wallet, Shield, Brain, Smartphone } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type Feature = {
  icon: LucideIcon;
  title: string;
  description: string;
  gradient: string;
  stats: string;
  statsLabel: string;
  details: string[];
};

export const features: Feature[] = [
  {
    icon: Clock,
    title: 'Pointage Intelligent',
    description: 'Gestion ultra-precise des presences avec compatibilite ZKTeco, NFC et biometrie avancee.',
    gradient: 'from-emerald-400 to-teal-500',
    stats: '99.9%',
    statsLabel: 'Precision',
    details: ['Reconnaissance faciale', 'NFC / QR Code', 'Geolocalisation', 'Mode hors-ligne'],
  },
  {
    icon: Users,
    title: 'Gestion des Absences',
    description: 'Workflow complet de demande, validation et suivi des conges avec calendrier partage.',
    gradient: 'from-blue-400 to-indigo-500',
    stats: '50K+',
    statsLabel: 'Utilisateurs',
    details: ['Soldes en temps reel', 'Validation multi-niveaux', 'Calendrier equipe', 'Alertes automatiques'],
  },
  {
    icon: Wallet,
    title: 'Paie Automatisee',
    description: 'Calcul automatique adapte aux reglementations locales avec generation de bulletins.',
    gradient: 'from-amber-400 to-orange-500',
    stats: '3x',
    statsLabel: 'Plus rapide',
    details: ['Multi-devises', 'Exports comptables', 'Avances sur salaire', 'Conformite fiscale'],
  },
  {
    icon: Shield,
    title: 'Securite Renforcee',
    description: 'Authentification biometrique, chiffrement bout-en-bout et audit trail complet.',
    gradient: 'from-violet-400 to-purple-500',
    stats: 'SOC2',
    statsLabel: 'Certifie',
    details: ['2FA obligatoire', 'Chiffrement AES-256', 'Audit trail', 'RGPD compliant'],
  },
  {
    icon: Brain,
    title: 'Leo IA',
    description: 'Assistant IA integre pour analyser vos donnees RH, predire les tendances et automatiser les taches.',
    gradient: 'from-fuchsia-400 to-pink-500',
    stats: 'GPT-4',
    statsLabel: 'Propulse',
    details: ['Analyse predictive', 'Rapports automatiques', 'Commande vocale', 'Suggestions intelligentes'],
  },
  {
    icon: Smartphone,
    title: 'Mobile First',
    description: 'Application native iOS et Android avec synchronisation temps reel et mode offline.',
    gradient: 'from-cyan-400 to-sky-500',
    stats: '4.9',
    statsLabel: 'App Store',
    details: ['iOS & Android', 'Mode offline', 'Push notifications', 'Widgets natifs'],
  },
];
