'use client';

import { Metadata } from 'next';
import { HeroSection } from '@/modules/vitrine/components/sections/HeroSection';
import { FeaturesSection } from '@/modules/vitrine/components/sections/FeaturesSection';
import { CTASection } from '@/modules/vitrine/components/sections/CTASection';
import { MainLayout } from '@/modules/vitrine/components/layout/MainLayout';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Container } from '@/modules/vitrine/components/common/Container';
import { Section } from '@/modules/vitrine/components/common/Section';

export default function GuidesRHStartupPage() {
  return (
    <MainLayout>
      <HeroSection
        headline="Guide Complet RH pour Startup"
        subheadline="Tout ce que vous devez savoir pour gérer vos employés en startup"
        badge="Guide Gratuit"
        ctaPrimary={{
          text: 'Télécharger le Guide (PDF)',
          href: '/downloads/guide-rh-startup.pdf',
        }}
        ctaSecondary={{
          text: 'Essai Gratuit',
          href: '/auth/signup',
        }}
      />

      <Section>
        <Container>
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">10 Chapitres</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Couvrant tous les aspects de la gestion RH en startup
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">50+ Pages</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Contenu détaillé avec exemples et templates
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">100% Gratuit</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Aucune inscription requise, téléchargez directement
              </p>
            </div>
          </div>
        </Container>
      </Section>

      <Section>
        <Container>
          <h2 className="text-3xl font-bold mb-8">Contenu du Guide</h2>
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                1
              </div>
              <div>
                <h3 className="font-bold">Fondamentaux de la RH en Startup</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Pourquoi la RH est importante et les 3 piliers essentiels
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                2
              </div>
              <div>
                <h3 className="font-bold">Recrutement et Onboarding</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Comment trouver et intégrer les bons talents
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                3
              </div>
              <div>
                <h3 className="font-bold">Gestion des Contrats et Conformité</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Respecter la loi et protéger votre entreprise
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                4
              </div>
              <div>
                <h3 className="font-bold">Gestion de la Paie</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Automatiser et sécuriser votre paie
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                5
              </div>
              <div>
                <h3 className="font-bold">Gestion des Absences et Congés</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Gérer efficacement les congés et absences
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                6
              </div>
              <div>
                <h3 className="font-bold">Culture et Engagement</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Créer une culture forte et engager vos employés
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                7
              </div>
              <div>
                <h3 className="font-bold">Outils et Systèmes</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Choisir les bons outils pour votre startup
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                8
              </div>
              <div>
                <h3 className="font-bold">Gestion des Performances</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Évaluer et développer vos employés
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                9
              </div>
              <div>
                <h3 className="font-bold">Santé et Sécurité</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Responsabilités légales et bien-être
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                10
              </div>
              <div>
                <h3 className="font-bold">Croissance et Scalabilité</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Préparer votre RH pour la croissance
                </p>
              </div>
            </div>
          </div>
        </Container>
      </Section>

      <CTASection
        headline="Prêt à transformer votre RH?"
        subheadline="Téléchargez le guide et commencez dès aujourd'hui"
        ctaPrimary={{
          text: 'Télécharger Maintenant',
          href: '/downloads/guide-rh-startup.pdf',
        }}
        ctaSecondary={{
          text: 'Essayer Leopardo',
          href: '/auth/signup',
        }}
      />
    </MainLayout>
  );
}
