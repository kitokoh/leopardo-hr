'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight, BookOpen, CalendarCheck, FileText, ShieldCheck, Sparkles, UserPlus, Workflow } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

type ReadinessCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  cards: Array<{
    title: string;
    text: string;
    href: string;
    cta: string;
  }>;
};

const copyByLocale: Record<AppLocale, ReadinessCopy> = {
  fr: {
    badge: 'Pret pour votre lancement RH',
    title: 'Du premier clic au premier bulletin,',
    highlight: 'tout est relie.',
    subtitle: 'Leopardo RH transforme votre trafic marketing en espace client actif : demo, guide, inscription, dashboard, mobile et kiosque.',
    cards: [
      { title: 'Voir le produit en action', text: 'Un parcours demo clair pour comprendre la valeur avant achat.', href: '/demo', cta: 'Planifier une demo' },
      { title: 'Lire les guides RH', text: 'Des contenus concrets pour attirer managers, RH et dirigeants.', href: '/blog', cta: 'Explorer le blog' },
      { title: 'Comparer les plans', text: 'Une offre lisible pour demarrer petit puis activer les modules avances.', href: '/pricing', cta: 'Voir les tarifs' },
      { title: 'Ouvrir votre espace client', text: 'Un chemin direct pour creer le compte entreprise et rejoindre le dashboard.', href: '/signup', cta: 'Commencer maintenant' },
    ],
  },
  en: {
    badge: 'Ready for your HR launch',
    title: 'From first click to first pay slip,',
    highlight: 'everything is connected.',
    subtitle: 'Leopardo RH turns marketing traffic into an active client workspace: demo, guide, signup, dashboard, mobile, and kiosk.',
    cards: [
      { title: 'See the product live', text: 'A clear demo path to understand value before purchase.', href: '/demo', cta: 'Book a demo' },
      { title: 'Read HR guides', text: 'Practical content for managers, HR leaders, and founders.', href: '/blog', cta: 'Explore the blog' },
      { title: 'Compare plans', text: 'Start lean, then activate advanced modules when the team grows.', href: '/pricing', cta: 'View pricing' },
      { title: 'Open your client workspace', text: 'A direct path to create the company account and reach the dashboard.', href: '/signup', cta: 'Start now' },
    ],
  },
  tr: {
    badge: 'IK lansmani icin hazir',
    title: 'Ilk tiklamadan ilk bordroya,',
    highlight: 'her sey bagli.',
    subtitle: 'Leopardo RH pazarlama trafigini aktif musteri deneyimine donusturur: demo, rehber, kayit, panel, mobil ve kiosk.',
    cards: [
      { title: 'Urunu canli gorun', text: 'Satinalmadan once degeri anlamak icin net demo akisi.', href: '/demo', cta: 'Demo planla' },
      { title: 'IK rehberlerini okuyun', text: 'Yoneticiler, IK ekipleri ve kurucular icin pratik icerik.', href: '/blog', cta: 'Blogu kesfet' },
      { title: 'Planlari karsilastirin', text: 'Kucuk baslayin, ekip buyudukce gelismis modulleri acin.', href: '/pricing', cta: 'Fiyatlari gor' },
      { title: 'Musteri alaninizi acin', text: 'Sirket hesabini olusturup panele ulasmak icin dogrudan yol.', href: '/signup', cta: 'Hemen baslayin' },
    ],
  },
  ar: {
    badge: 'جاهز لإطلاق تجربة الموارد البشرية',
    title: 'من أول زيارة إلى أول كشف راتب،',
    highlight: 'كل شيء مترابط.',
    subtitle: 'يربط Leopardo RH العرض التجريبي، الأدلة، التسجيل، لوحة التحكم، الجوال والكشك في رحلة عميل واحدة.',
    cards: [
      { title: 'شاهد المنتج', text: 'مسار عرض واضح قبل قرار الشراء.', href: '/demo', cta: 'احجز عرضا' },
      { title: 'اقرأ أدلة الموارد البشرية', text: 'موارد عملية للموارد البشرية والمديرين.', href: '/blog', cta: 'استكشف المدونة' },
      { title: 'قارن الباقات', text: 'ابدأ ببساطة ثم فعّل الوحدات المتقدمة لاحقا.', href: '/pricing', cta: 'عرض الأسعار' },
      { title: 'افتح مساحة العميل', text: 'مسار مباشر لإنشاء الحساب والوصول إلى لوحة التحكم.', href: '/signup', cta: 'ابدأ الآن' },
    ],
  },
};

const icons = [CalendarCheck, BookOpen, FileText, UserPlus];

export function MarketingReadinessSection({ locale = 'fr' }: { locale?: AppLocale }) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;

  return (
    <section className="relative overflow-hidden bg-slate-950 py-24 text-white">
      <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.14),transparent_36%,rgba(34,211,238,0.10)),linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[length:100%_100%,48px_48px,48px_48px]" />
      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
          >
            <div className="mb-6 inline-flex items-center gap-2 rounded-lg border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-sm font-semibold text-emerald-100">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              {copy.badge}
            </div>
            <h2 className="max-w-3xl text-4xl font-black tracking-tight sm:text-5xl">
              {copy.title}{' '}
              <span className="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">
                {copy.highlight}
              </span>
            </h2>
            <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">{copy.subtitle}</p>
            <div className="mt-8 flex flex-wrap gap-3 text-sm text-slate-300">
              {['Web client', 'Mobile', 'Kiosque', 'API', 'IA-ready'].map((item) => (
                <span key={item} className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5">
                  <ShieldCheck className="h-4 w-4 text-emerald-300" aria-hidden="true" />
                  {item}
                </span>
              ))}
            </div>
          </motion.div>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {copy.cards.map((card, index) => {
              const Icon = icons[index] ?? Workflow;

              return (
                <motion.article
                  key={card.href}
                  initial={{ opacity: 0, y: 24 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.55, delay: index * 0.1 }}
                  className="rounded-lg border border-white/10 bg-white/[0.06] p-5 shadow-2xl shadow-black/10 backdrop-blur"
                >
                  <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-300/15 text-emerald-200">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <h3 className="mt-5 text-lg font-bold">{card.title}</h3>
                  <p className="mt-3 min-h-[72px] text-sm leading-6 text-slate-300">{card.text}</p>
                  <Link href={card.href} className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-emerald-200 transition hover:text-white">
                    {card.cta}
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Link>
                </motion.article>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}
