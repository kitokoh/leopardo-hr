'use client';

import { HeroSection } from '@/modules/vitrine/components/sections/HeroSection';
import { CTASection } from '@/modules/vitrine/components/sections/CTASection';
import { MainLayout } from '@/modules/vitrine/components/layout/MainLayout';
import { Container } from '@/modules/vitrine/components/common/Container';
import { Section } from '@/modules/vitrine/components/common/Section';

export default function GuidesPlanningEmployesPage() {
  return (
    <MainLayout>
      <HeroSection
        headline="Modèle Planning Employés"
        subheadline="Template Excel gratuit pour gérer le planning de votre équipe"
        badge="Template Gratuit"
        ctaPrimary={{
          text: 'Télécharger le Template (Excel)',
          href: '/downloads/modele-planning-employes.xlsx',
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
              <h3 className="text-lg font-bold mb-2">Flexible</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Adaptez le template à vos besoins
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">Facile à Utiliser</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Pas de configuration complexe
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">100% Gratuit</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Téléchargez directement en Excel
              </p>
            </div>
          </div>
        </Container>
      </Section>

      <Section>
        <Container>
          <h2 className="text-3xl font-bold mb-8">Contenu du Template</h2>
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                📋
              </div>
              <div>
                <h3 className="font-bold">Feuille Employés</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Liste de vos employés avec informations de base
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                📅
              </div>
              <div>
                <h3 className="font-bold">Planning Mensuel</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Vue mensuelle du planning avec jours de travail
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ⏰
              </div>
              <div>
                <h3 className="font-bold">Heures de Travail</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Suivi des heures et des pauses
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                📊
              </div>
              <div>
                <h3 className="font-bold">Rapports</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Rapports automatiques sur le planning
                </p>
              </div>
            </div>
          </div>
        </Container>
      </Section>

      <CTASection
        headline="Gérez votre planning facilement"
        subheadline="Téléchargez le template et commencez dès aujourd'hui"
        ctaPrimary={{
          text: 'Télécharger Maintenant',
          href: '/downloads/modele-planning-employes.xlsx',
        }}
        ctaSecondary={{
          text: 'Passer à Leopardo',
          href: '/auth/signup',
        }}
      />
    </MainLayout>
  );
}
