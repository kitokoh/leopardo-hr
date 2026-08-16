'use client';

// Contenu localisé des pages /guides/* (issue #3248). Les pages (Server
// Components avec `metadata`) délèguent leur rendu à ce composant client —
// `useVitrineLocale` ne peut pas s'exécuter dans un Server Component.
import { HeroSection } from '@/modules/vitrine/components/sections/HeroSection';
import { CTASection } from '@/modules/vitrine/components/sections/CTASection';
import { MainLayout } from '@/modules/vitrine/components/layout/MainLayout';
import { Container } from '@/modules/vitrine/components/common/Container';
import { Section } from '@/modules/vitrine/components/common/Section';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { guidesPageCopy, type GuidesContent } from '@/modules/vitrine/data/guides';

export type GuideKey = keyof GuidesContent;

export function GuidePageContent({
  guide,
  downloads,
}: {
  guide: GuideKey;
  downloads: { pdf: string; signup: string; footer: string };
}) {
  const { locale } = useVitrineLocale();
  const copy = guidesPageCopy[locale]?.[guide] ?? guidesPageCopy.fr[guide];

  return (
    <MainLayout>
      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        badge={copy.hero.badge}
        ctaPrimary={{ text: copy.hero.ctaPrimary, href: downloads.pdf }}
        ctaSecondary={{ text: copy.hero.ctaSecondary, href: downloads.signup }}
      />

      <Section>
        <Container>
          <div className="grid md:grid-cols-3 gap-8">
            {copy.stats.map((stat) => (
              <div
                key={stat.title}
                className="bg-white dark:bg-slate-900 p-6 rounded-lg border border-slate-200 dark:border-slate-800"
              >
                <h3 className="text-lg font-bold mb-2">{stat.title}</h3>
                <p className="text-slate-600 dark:text-slate-400">
                  {stat.description}
                </p>
              </div>
            ))}
          </div>
        </Container>
      </Section>

      <Section>
        <Container>
          <h2 className="text-3xl font-bold mb-8">{copy.sectionTitle}</h2>
          <div className="space-y-4">
            {copy.sections.map((section, index) => (
              <div key={section.title} className="flex items-start gap-4">
                <div className="flex-shrink-0 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
                  {index + 1}
                </div>
                <div>
                  <h3 className="font-bold">{section.title}</h3>
                  <p className="text-slate-600 dark:text-slate-400">
                    {section.description}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </Container>
      </Section>

      <CTASection
        headline={copy.cta.headline}
        subheadline={copy.cta.subheadline}
        ctaPrimary={{ text: copy.cta.ctaPrimary, href: downloads.pdf }}
        ctaSecondary={{ text: copy.cta.ctaSecondary, href: downloads.footer }}
      />
    </MainLayout>
  );
}
