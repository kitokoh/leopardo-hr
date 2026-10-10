'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight, Database, Package, Unlock, Users } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

// Bloc « Pourquoi Leopardo » qui parle au client final — 3 arguments business :
// vos données chez vous, pas de prix par utilisateur, zéro lock-in. Le
// positionnement est « Business OS » : on vend la maîtrise de l'outil, pas
// une licence ni un modèle de développement.

type WhyLeopardoCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  cards: Array<{ title: string; text: string }>;
  ctaPrimary: { label: string; href: string };
  ctaSecondary: { label: string; href: string };
};

const copyByLocale: Record<AppLocale, WhyLeopardoCopy> = {
  fr: {
    badge: 'Pourquoi Leopardo',
    title: 'Un Business OS qui vous appartient,',
    highlight: 'vraiment.',
    subtitle:
      'Avec Leopardo, vous choisissez le cloud ou vos propres serveurs — sans surprise sur la facture ni dépendance à un éditeur.',
    cards: [
      {
        title: 'Vos données chez vous',
        text: 'Hébergez la paie et les dossiers du personnel sur vos serveurs ou dans le cloud de votre choix. Vous gardez le contrôle, y compris hors ligne.',
      },
      {
        title: 'Pas de prix par utilisateur',
        text: 'Ajoutez des employés, des managers et des sites sans voir la facture grimper. Le coût ne punit pas votre croissance.',
      },
      {
        title: 'Zéro lock-in',
        text: 'Données exportables, API ouverte, hébergement au choix. Si vous partez un jour, vous partez avec tout — mais rien ne vous y oblige.',
      },
    ],
    ctaPrimary: { label: 'Comparer cloud et auto-hébergement', href: '/pricing' },
    ctaSecondary: { label: 'Découvrir les packs métiers', href: '/restaurateur' },
  },
  en: {
    badge: 'Why Leopardo',
    title: 'A Business OS you actually',
    highlight: 'own.',
    subtitle:
      'With Leopardo, you choose the cloud or your own servers — no billing surprises and no vendor dependency.',
    cards: [
      {
        title: 'Your data stays yours',
        text: 'Host payroll and employee records on your servers or in the cloud you choose. You keep control, even offline.',
      },
      {
        title: 'No per-user pricing',
        text: 'Add employees, managers, and sites without watching the bill climb. Cost never punishes your growth.',
      },
      {
        title: 'Zero lock-in',
        text: 'Exportable data, open API, hosting of your choice. If you ever leave, you leave with everything — but nothing forces you to.',
      },
    ],
    ctaPrimary: { label: 'Compare cloud vs self-hosting', href: '/pricing' },
    ctaSecondary: { label: 'Explore the business packs', href: '/restaurateur' },
  },
  tr: {
    badge: 'Neden Leopardo',
    title: 'Gerçekten size ait bir',
    highlight: 'Business OS.',
    subtitle:
      'Leopardo ile bulutu veya kendi sunucularınızı seçersiniz — fatura sürprizi yok, tedarikçi bağımlılığı yok.',
    cards: [
      {
        title: 'Verileriniz sizde kalır',
        text: 'Bordro ve personel dosyalarını kendi sunucularınızda veya seçtiğiniz bulutta barındırın. Kontrol sizde, çevrimdışı bile.',
      },
      {
        title: 'Kullanıcı başına ücret yok',
        text: 'Çalışan, yönetici ve şube ekledikçe fatura büyümez. Maliyet, büyümenizi cezalandırmaz.',
      },
      {
        title: 'Sıfır bağımlılık',
        text: 'Dışa aktarılabilir veri, açık API, dilediğiniz barındırma. Bir gün ayrılırsanız her şeyinizle ayrılırsınız — ama hiçbir şey sizi zorlamaz.',
      },
    ],
    ctaPrimary: { label: 'Bulut ve kendi sunucunuzu karşılaştırın', href: '/pricing' },
    ctaSecondary: { label: 'İş paketlerini keşfedin', href: '/restaurateur' },
  },
  ar: {
    badge: 'لماذا ليوباردو',
    title: 'نظام تشغيل أعمال',
    highlight: 'تملكه فعلاً.',
    subtitle:
      'مع ليوباردو تختار السحابة أو خوادمك الخاصة — دون مفاجآت في الفاتورة ودون الارتباط بمورّد واحد.',
    cards: [
      {
        title: 'بياناتك تبقى عندك',
        text: 'استضف الرواتب وملفات الموظفين على خوادمك أو في السحابة التي تختارها. تحتفظ بالتحكم حتى دون اتصال.',
      },
      {
        title: 'لا تسعير لكل مستخدم',
        text: 'أضف موظفين ومديرين وفروعا دون أن ترتفع الفاتورة. التكلفة لا تعاقب نموك.',
      },
      {
        title: 'صفر احتكار',
        text: 'بيانات قابلة للتصدير، واجهة API مفتوحة، واستضافة من اختيارك. إن غادرت يوما تغادر بكل شيء — ولا شيء يجبرك على ذلك.',
      },
    ],
    ctaPrimary: { label: 'قارن بين السحابة والاستضافة الذاتية', href: '/pricing' },
    ctaSecondary: { label: 'اكتشف حزم الأعمال', href: '/restaurateur' },
  },
};

const icons = [Database, Users, Unlock];

export function WhyLeopardoSection({ locale = 'fr' }: { locale?: AppLocale }) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;

  return (
    <section className="relative overflow-hidden bg-slate-950 py-24 text-white">
      <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.14),transparent_36%,rgba(34,211,238,0.10)),linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[length:100%_100%,48px_48px,48px_48px]" />
      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ y: 24 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="mx-auto max-w-3xl text-center"
        >
          <div className="mb-6 inline-flex items-center gap-2 rounded-lg border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-sm font-semibold text-emerald-100">
            <Package className="h-4 w-4" aria-hidden="true" />
            {copy.badge}
          </div>
          <h2 className="text-4xl font-black tracking-tight sm:text-5xl">
            {copy.title}{' '}
            <span className="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">
              {copy.highlight}
            </span>
          </h2>
          <p className="mt-6 text-lg leading-8 text-slate-300">{copy.subtitle}</p>
        </motion.div>

        <div className="mt-14 grid gap-4 sm:grid-cols-3">
          {copy.cards.map((card, index) => {
            const Icon = icons[index] ?? Database;

            return (
              <motion.article
                key={card.title}
                initial={{ y: 24 }}
                whileInView={{ y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.55, delay: index * 0.1 }}
                className="rounded-lg border border-white/10 bg-white/[0.06] p-6 shadow-2xl shadow-black/10 backdrop-blur"
              >
                <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-300/15 text-emerald-200">
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </div>
                <h3 className="mt-5 text-lg font-bold">{card.title}</h3>
                <p className="mt-3 text-sm leading-6 text-slate-300">{card.text}</p>
              </motion.article>
            );
          })}
        </div>

        <motion.div
          initial={{ y: 24 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.55, delay: 0.2 }}
          className="mt-12 flex flex-wrap items-center justify-center gap-4"
        >
          <Link
            href={copy.ctaPrimary.href}
            className="inline-flex items-center gap-2 rounded-lg bg-emerald-400 px-6 py-3 text-sm font-bold text-slate-950 transition hover:bg-emerald-300"
          >
            {copy.ctaPrimary.label}
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
          <Link
            href={copy.ctaSecondary.href}
            className="inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10"
          >
            <Package className="h-4 w-4" aria-hidden="true" />
            {copy.ctaSecondary.label}
          </Link>
        </motion.div>
      </div>
    </section>
  );
}
