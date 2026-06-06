'use client';

import { HeroSection } from '@/modules/vitrine/components/sections/HeroSection';
import { CTASection } from '@/modules/vitrine/components/sections/CTASection';
import { MainLayout } from '@/modules/vitrine/components/layout/MainLayout';
import { Container } from '@/modules/vitrine/components/common/Container';
import { Section } from '@/modules/vitrine/components/common/Section';

export default function GuidesChecklistPaiePage() {
  return (
    <MainLayout>
      <HeroSection
        headline="Checklist Paie 2024"
        subheadline="Assurez la conformité de votre paie avec cette checklist complète"
        badge="Guide Gratuit"
        ctaPrimary={{
          text: 'Télécharger la Checklist (PDF)',
          href: '/downloads/checklist-paie-2024.pdf',
        }}
        ctaSecondary={{
          text: 'Essai Gratuit',
          href: '/signup?source=guide-checklist-paie',
        }}
      />

      <Section>
        <Container>
          <div className="grid md:grid-cols-3 gap-8">
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">50+ Points</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Vérifications complètes pour votre paie 2024
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">Conformité Garantie</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Respectez toutes les réglementations 2024
              </p>
            </div>
            <div className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800">
              <h3 className="text-lg font-bold mb-2">100% Gratuit</h3>
              <p className="text-slate-600 dark:text-slate-400">
                Téléchargez directement en PDF
              </p>
            </div>
          </div>
        </Container>
      </Section>

      <Section>
        <Container>
          <h2 className="text-3xl font-bold mb-8">Sections de la Checklist</h2>
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ✓
              </div>
              <div>
                <h3 className="font-bold">Avant la Paie</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Préparation et vérifications préalables
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ✓
              </div>
              <div>
                <h3 className="font-bold">Pendant la Paie</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Calculs et vérifications en cours
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ✓
              </div>
              <div>
                <h3 className="font-bold">Après la Paie</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Validation et archivage
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ✓
              </div>
              <div>
                <h3 className="font-bold">Conformité 2024</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Mises à jour et changements 2024
                </p>
              </div>
            </div>
            <div className="flex items-start gap-4">
              <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                ✓
              </div>
              <div>
                <h3 className="font-bold">Sécurité</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  Protection des données et conformité
                </p>
              </div>
            </div>
          </div>
        </Container>
      </Section>

      <CTASection
        headline="Assurez votre conformité paie 2024"
        subheadline="Téléchargez la checklist et vérifiez chaque point"
        ctaPrimary={{
          text: 'Télécharger Maintenant',
          href: '/downloads/checklist-paie-2024.pdf',
        }}
        ctaSecondary={{
          text: 'Automatiser avec Leopardo',
          href: '/signup?source=guide-checklist-paie-footer',
        }}
      />
    </MainLayout>
  );
}
