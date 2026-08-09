'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import {
  Brush,
  Building2,
  Check,
  ImageIcon,
  Layers,
  Monitor,
  Palette,
  Shield,
  Smartphone,
  Sparkles,
  Type,
} from 'lucide-react';

type Lang = 'fr' | 'en' | 'tr' | 'ar';
const langs: Lang[] = ['fr', 'en', 'tr', 'ar'];

type FeatureCopy = { title: string; desc: string; color: string };

const featuresByLocale: Record<Lang, FeatureCopy[]> = {
  fr: [
    { title: 'Logo entreprise', desc: "Uploadez votre logo (PNG, SVG). Il s'affiche dans l'app mobile, les emails et les bulletins PDF.", color: 'emerald' },
    { title: "Nom d'affichage", desc: 'Personnalisez le nom affiché dans l\'interface : "Acme Corp" plutôt que votre identifiant technique.', color: 'blue' },
    { title: 'Couleurs primaires', desc: "Définissez votre couleur principale et votre couleur d'accent. Aperçu en temps réel dans le dashboard.", color: 'violet' },
    { title: 'Mobile branding', desc: "Le thème se propage dans l'app manager et l'app employee. Headers, boutons et badges adoptent vos couleurs.", color: 'pink' },
    { title: 'Dashboard admin', desc: "L'espace web du manager reflète votre identité visuelle dès la connexion.", color: 'amber' },
    { title: 'Isolation tenant', desc: 'Chaque entreprise a son propre branding. Aucun risque de mélange entre tenants.', color: 'red' },
  ],
  en: [
    { title: 'Company logo', desc: 'Upload your logo (PNG, SVG). It appears in the mobile app, emails and PDF payslips.', color: 'emerald' },
    { title: 'Display name', desc: 'Customise the name shown in the interface: "Acme Corp" instead of your technical identifier.', color: 'blue' },
    { title: 'Primary colours', desc: 'Set your primary and accent colours. Live preview in the dashboard.', color: 'violet' },
    { title: 'Mobile branding', desc: 'The theme propagates to the manager and employee apps. Headers, buttons and badges pick up your colours.', color: 'pink' },
    { title: 'Admin dashboard', desc: "The manager's web space reflects your visual identity as soon as they log in.", color: 'amber' },
    { title: 'Tenant isolation', desc: 'Each company has its own branding. No risk of mixing tenants.', color: 'red' },
  ],
  tr: [
    { title: 'Åžirket logosu', desc: "Logonuzu yükleyin (PNG, SVG). Mobil uygulamada, e-postalarda ve PDF bordrolarda görünür.", color: 'emerald' },
    { title: 'Görünen ad', desc: 'Arayüzde görünen adÄ± özelleÅŸtirin: teknik kimliÄŸiniz yerine "Acme Corp".', color: 'blue' },
    { title: 'Birincil renkler', desc: 'Ana rengi ve vurgu rengini belirleyin. Panelde gerçek zamanlÄ± önizleme.', color: 'violet' },
    { title: 'Mobil marka', desc: 'Tema yönetici ve çalÄ±ÅŸan uygulamalarÄ±na yayÄ±lÄ±r. BaÅŸlÄ±klar, düÄŸmeler ve rozetler renklerinizi alÄ±r.', color: 'pink' },
    { title: 'Yönetici paneli', desc: "Yöneticinin web alanÄ± giriÅŸ yaptÄ±ÄŸÄ± anda görsel kimliÄŸinizi yansÄ±tÄ±r.", color: 'amber' },
    { title: 'KiracÄ± yalÄ±tÄ±mÄ±', desc: 'Her ÅŸirketin kendi markasÄ± vardÄ±r. KiracÄ±lar arasÄ±nda karÄ±ÅŸma riski yoktur.', color: 'red' },
  ],
  ar: [
    { title: 'Ø´Ø¹Ø§Ø± Ø§Ù„Ø´Ø±ÙƒØ©', desc: 'Ø§Ø±ÙØ¹ Ø´Ø¹Ø§Ø±Ùƒ (PNGØŒ SVG). ÙŠØ¸Ù‡Ø± ÙÙŠ Ø§Ù„ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø¬ÙˆØ§Ù„ ÙˆØ§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ ÙˆÙƒØ´ÙˆÙ Ø§Ù„Ø±ÙˆØ§ØªØ¨ PDF.', color: 'emerald' },
    { title: 'Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø±ÙˆØ¶', desc: 'Ø®ØµÙ‘Øµ Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø±ÙˆØ¶ ÙÙŠ Ø§Ù„ÙˆØ§Ø¬Ù‡Ø©: "Acme Corp" Ø¨Ø¯Ù„ Ù…Ø¹Ø±ÙÙƒ Ø§Ù„ØªÙ‚Ù†ÙŠ.', color: 'blue' },
    { title: 'Ø§Ù„Ø£Ù„ÙˆØ§Ù† Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©', desc: 'Ø­Ø¯Ù‘Ø¯ Ù„ÙˆÙ†Ùƒ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ ÙˆÙ„ÙˆÙ† Ø§Ù„ØªÙ…ÙŠÙŠØ². Ù…Ø¹Ø§ÙŠÙ†Ø© ÙÙˆØ±ÙŠØ© ÙÙŠ Ù„ÙˆØ­Ø© Ø§Ù„ØªØ­ÙƒÙ….', color: 'violet' },
    { title: 'Ø§Ù„Ù‡ÙˆÙŠØ© Ø¹Ù„Ù‰ Ø§Ù„Ù…ÙˆØ¨Ø§ÙŠÙ„', desc: 'ÙŠÙ†ØªÙ‚Ù„ Ø§Ù„Ø³Ù…Ø© Ø¥Ù„Ù‰ ØªØ·Ø¨ÙŠÙ‚ÙŠ Ø§Ù„Ù…Ø¯ÙŠØ± ÙˆØ§Ù„Ù…ÙˆØ¸Ù. ØªØªØ¨Ù†Ù‰ Ø§Ù„Ø±Ø¤ÙˆØ³ ÙˆØ§Ù„Ø£Ø²Ø±Ø§Ø± ÙˆØ§Ù„Ø´Ø¹Ø§Ø±Ø§Øª Ø£Ù„ÙˆØ§Ù†Ùƒ.', color: 'pink' },
    { title: 'Ù„ÙˆØ­Ø© ØªØ­ÙƒÙ… Ø§Ù„Ø¥Ø¯Ø§Ø±Ø©', desc: 'Ù…Ø³Ø§Ø­Ø© Ø§Ù„Ù…Ø¯ÙŠØ± Ø¹Ù„Ù‰ Ø§Ù„ÙˆÙŠØ¨ ØªØ¹ÙƒØ³ Ù‡ÙˆÙŠØªÙƒ Ø§Ù„Ø¨ØµØ±ÙŠØ© Ù…Ù† Ø£ÙˆÙ„ ØªØ³Ø¬ÙŠÙ„ Ø¯Ø®ÙˆÙ„.', color: 'amber' },
    { title: 'Ø¹Ø²Ù„ Ø§Ù„Ù…Ø³ØªØ£Ø¬Ø±ÙŠÙ†', desc: 'ÙƒÙ„ Ø´Ø±ÙƒØ© Ù„Ù‡Ø§ Ù‡ÙˆÙŠØªÙ‡Ø§ Ø§Ù„Ø®Ø§ØµØ©. Ù„Ø§ Ø®Ø·Ø± Ù„Ø§Ø®ØªÙ„Ø§Ø· Ø§Ù„Ù‡ÙˆÙŠØ© Ø¨ÙŠÙ† Ø§Ù„Ù…Ø³ØªØ£Ø¬Ø±ÙŠÙ†.', color: 'red' },
  ],
};

const featureIcons = [ImageIcon, Type, Palette, Smartphone, Monitor, Shield] as const;

const colorMap: Record<string, { bg: string; icon: string; border: string; light: string }> = {
  emerald: {
    bg: 'bg-emerald-50 dark:bg-emerald-900/20',
    icon: 'text-emerald-600 dark:text-emerald-400',
    border: 'border-emerald-200 dark:border-emerald-800',
    light: 'bg-emerald-400',
  },
  blue: {
    bg: 'bg-blue-50 dark:bg-blue-900/20',
    icon: 'text-blue-600 dark:text-blue-400',
    border: 'border-blue-200 dark:border-blue-800',
    light: 'bg-blue-400',
  },
  violet: {
    bg: 'bg-violet-50 dark:bg-violet-900/20',
    icon: 'text-violet-600 dark:text-violet-400',
    border: 'border-violet-200 dark:border-violet-800',
    light: 'bg-violet-400',
  },
  pink: {
    bg: 'bg-pink-50 dark:bg-pink-900/20',
    icon: 'text-pink-600 dark:text-pink-400',
    border: 'border-pink-200 dark:border-pink-800',
    light: 'bg-pink-400',
  },
  amber: {
    bg: 'bg-amber-50 dark:bg-amber-900/20',
    icon: 'text-amber-600 dark:text-amber-400',
    border: 'border-amber-200 dark:border-amber-800',
    light: 'bg-amber-400',
  },
  red: {
    bg: 'bg-red-50 dark:bg-red-900/20',
    icon: 'text-red-600 dark:text-red-400',
    border: 'border-red-200 dark:border-red-800',
    light: 'bg-red-400',
  },
};

type PlanCopy = { name: string; price: string; features: string[]; highlight: boolean };

const plansByLocale: Record<Lang, PlanCopy[]> = {
  fr: [
    { name: 'Starter', price: 'Inclus', features: ['Logo entreprise', "Nom d'affichage"], highlight: false },
    { name: 'Pro', price: 'Premium', features: ['Logo entreprise', "Nom d'affichage", 'Couleurs personnalisées', 'Branding mobile'], highlight: true },
    { name: 'Enterprise', price: 'Sur devis', features: ['Tout Pro', 'Whitelabel complet', 'Domaine personnalisé', 'Splash screen mobile custom'], highlight: false },
  ],
  en: [
    { name: 'Starter', price: 'Included', features: ['Company logo', 'Display name'], highlight: false },
    { name: 'Pro', price: 'Premium', features: ['Company logo', 'Display name', 'Custom colours', 'Mobile branding'], highlight: true },
    { name: 'Enterprise', price: 'Custom quote', features: ['Everything in Pro', 'Full white-label', 'Custom domain', 'Custom mobile splash screen'], highlight: false },
  ],
  tr: [
    { name: 'Starter', price: 'Dahil', features: ['Åžirket logosu', 'Görünen ad'], highlight: false },
    { name: 'Pro', price: 'Premium', features: ['Åžirket logosu', 'Görünen ad', 'Ã–zel renkler', 'Mobil marka'], highlight: true },
    { name: 'Enterprise', price: 'Teklif alÄ±n', features: ["Pro'daki her ÅŸey", 'Tam whitelabel', 'Ã–zel alan adÄ±', 'Ã–zel mobil açÄ±lÄ±ÅŸ ekranÄ±'], highlight: false },
  ],
  ar: [
    { name: 'Starter', price: 'Ù…Ø´Ù…ÙˆÙ„', features: ['Ø´Ø¹Ø§Ø± Ø§Ù„Ø´Ø±ÙƒØ©', 'Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø±ÙˆØ¶'], highlight: false },
    { name: 'Pro', price: 'Ø¨Ø±ÙŠÙ…ÙŠÙˆÙ…', features: ['Ø´Ø¹Ø§Ø± Ø§Ù„Ø´Ø±ÙƒØ©', 'Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…Ø¹Ø±ÙˆØ¶', 'Ø£Ù„ÙˆØ§Ù† Ù…Ø®ØµØµØ©', 'Ù‡ÙˆÙŠØ© Ø§Ù„Ù…ÙˆØ¨Ø§ÙŠÙ„'], highlight: true },
    { name: 'Enterprise', price: 'Ø­Ø³Ø¨ Ø§Ù„Ø¹Ø±Ø¶', features: ['ÙƒÙ„ Ù…Ø§ ÙÙŠ Pro', 'Ø¹Ù„Ø§Ù…Ø© Ø¨ÙŠØ¶Ø§Ø¡ ÙƒØ§Ù…Ù„Ø©', 'Ù†Ø·Ø§Ù‚ Ù…Ø®ØµØµ', 'Ø´Ø§Ø´Ø© Ø¨Ø¯Ø§ÙŠØ© Ù…Ø®ØµØµØ© Ù„Ù„ØªØ·Ø¨ÙŠÙ‚'], highlight: false },
  ],
};

type BrandingPageCopy = {
  hero: string;
  heroSub: string;
  badge: string;
  featuresTitle: string;
  apiSectionTitle: string;
  plansTitle: string;
  recommendedBadge: string;
  ctaTitle: string;
  ctaSub: string;
  ctaBtn: string;
};

const copy: Record<Lang, BrandingPageCopy> = {
  fr: {
    hero: 'Personnalisation entreprise',
    heroSub: 'Faites de Leopardo RH votre propre outil. Logo, couleurs, nom â€” tout reflète votre identité.',
    badge: 'Tenant Branding Premium',
    featuresTitle: 'Ce que vous pouvez personnaliser',
    apiSectionTitle: 'API Branding',
    plansTitle: 'Branding selon votre plan',
    recommendedBadge: 'Recommandé',
    ctaTitle: 'Prêt à personnaliser votre espace ?',
    ctaSub: 'Demandez une démo pour voir le branding premium en action.',
    ctaBtn: 'Démarrer la personnalisation',
  },
  en: {
    hero: 'Enterprise Branding',
    heroSub: 'Make Leopardo RH yours. Logo, colours, name â€” everything reflects your identity.',
    badge: 'Tenant Branding Premium',
    featuresTitle: 'What you can customise',
    apiSectionTitle: 'Branding API',
    plansTitle: 'Branding by plan',
    recommendedBadge: 'Recommended',
    ctaTitle: 'Ready to customise your workspace?',
    ctaSub: 'Request a demo to see premium branding in action.',
    ctaBtn: 'Start customising',
  },
  tr: {
    hero: 'Kurumsal Marka',
    heroSub: 'Leopardo RH\'yi kendinize ait yapÄ±n. Logo, renkler, isim â€” her ÅŸey kimliÄŸinizi yansÄ±tÄ±r.',
    badge: 'KiracÄ± Marka Premium',
    featuresTitle: 'Neler özelleÅŸtirilebilir',
    apiSectionTitle: 'Marka API',
    plansTitle: 'Plana göre marka özelleÅŸtirme',
    recommendedBadge: 'Ã–nerilen',
    ctaTitle: 'Ã‡alÄ±ÅŸma alanÄ±nÄ±zÄ± özelleÅŸtirmeye hazÄ±r mÄ±sÄ±nÄ±z?',
    ctaSub: 'Premium markayÄ± aksiyonda görmek için demo talep edin.',
    ctaBtn: 'Ã–zelleÅŸtirmeye baÅŸla',
  },
  ar: {
    hero: 'Ù‡ÙˆÙŠØ© Ø¨ØµØ±ÙŠØ© Ù„Ù„Ù…Ø¤Ø³Ø³Ø©',
    heroSub: 'Ø§Ø¬Ø¹Ù„ Leopardo RH Ù…Ù†ØµØªÙƒ Ø§Ù„Ø®Ø§ØµØ©. Ø´Ø¹Ø§Ø±ØŒ Ø£Ù„ÙˆØ§Ù†ØŒ Ø§Ø³Ù… â€” ÙƒÙ„ Ø´ÙŠØ¡ ÙŠØ¹ÙƒØ³ Ù‡ÙˆÙŠØªÙƒ.',
    badge: 'Ù‡ÙˆÙŠØ© Ø§Ù„Ù…Ø³ØªØ£Ø¬Ø± Ø§Ù„Ù…Ù…ÙŠØ²Ø©',
    featuresTitle: 'Ù…Ø§ ÙŠÙ…ÙƒÙ†Ùƒ ØªØ®ØµÙŠØµÙ‡',
    apiSectionTitle: 'ÙˆØ§Ø¬Ù‡Ø© Ø¨Ø±Ù…Ø¬Ø© ØªØ·Ø¨ÙŠÙ‚Ø§Øª Ø§Ù„Ù‡ÙˆÙŠØ©',
    plansTitle: 'Ø§Ù„Ø¹Ù„Ø§Ù…Ø© Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø­Ø³Ø¨ Ø§Ù„Ø®Ø·Ø©',
    recommendedBadge: 'Ù…ÙˆØµÙ‰ Ø¨Ù‡',
    ctaTitle: 'Ù‡Ù„ Ø£Ù†Øª Ù…Ø³ØªØ¹Ø¯ Ù„ØªØ®ØµÙŠØµ Ù…Ø³Ø§Ø­ØªÙƒØŸ',
    ctaSub: 'Ø§Ø·Ù„Ø¨ Ø¹Ø±Ø¶Ù‹Ø§ ØªÙˆØ¶ÙŠØ­ÙŠÙ‹Ø§ Ù„Ù…Ø´Ø§Ù‡Ø¯Ø© Ø§Ù„Ù‡ÙˆÙŠØ© Ø§Ù„Ù…Ù…ÙŠØ²Ø© Ù‚ÙŠØ¯ Ø§Ù„Ø¹Ù…Ù„.',
    ctaBtn: 'Ø§Ø¨Ø¯Ø£ Ø§Ù„ØªØ®ØµÙŠØµ',
  },
};

export default function BrandingPage() {
  const [isDark, setIsDark] = useState(false);
  const [lang, setLang] = useState<Lang>('fr');
  useScrollReveal();

  const t = copy[lang];
  const features = featuresByLocale[lang];
  const plans = plansByLocale[lang];
  const isRtl = lang === 'ar';

  return (
    <div
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
      dir={isRtl ? 'rtl' : 'ltr'}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero */}
      <section className="pt-32 pb-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          {/* Lang switcher */}
          <div className="flex justify-center gap-2 mb-8">
            {langs.map((l) => (
              <button
                key={l}
                onClick={() => setLang(l)}
                className={`px-3 py-1 rounded-full text-xs font-semibold transition-colors ${
                  lang === l
                    ? 'bg-violet-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-violet-100 dark:hover:bg-violet-900/30'
                }`}
              >
                {l.toUpperCase()}
              </button>
            ))}
          </div>

          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
            <Brush className="w-3.5 h-3.5" />
            {t.badge}
          </div>

          <motion.h1
            key={lang + '-hero'}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white tracking-tight mb-6"
          >
            <span className="bg-gradient-to-r from-violet-500 to-pink-500 bg-clip-text text-transparent">
              {t.hero}
            </span>
          </motion.h1>

          <motion.p
            key={lang + '-sub'}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-8"
          >
            {t.heroSub}
          </motion.p>

          {/* Mock brand preview */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.2 }}
            className="max-w-md mx-auto rounded-2xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-slate-900 shadow-xl overflow-hidden"
          >
            {/* Fake app header with brand colors */}
            <div className="h-14 flex items-center gap-3 px-4" style={{ background: 'linear-gradient(135deg, #7c3aed, #db2777)' }}>
              <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                <Building2 className="w-4 h-4 text-white" />
              </div>
              <span className="text-white font-bold text-sm">Acme Corp</span>
            </div>
            <div className="p-4 space-y-2">
              <div className="h-3 bg-violet-100 dark:bg-violet-900/30 rounded w-3/4" />
              <div className="h-3 bg-slate-100 dark:bg-slate-800 rounded w-1/2" />
              <div className="mt-3 h-8 rounded-lg" style={{ background: 'linear-gradient(135deg, #7c3aed, #db2777)' }} />
            </div>
          </motion.div>
        </div>
      </section>

      {/* Features grid */}
      <section className="py-16 px-4 bg-transparent dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-8 text-center">{t.featuresTitle}</h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((feat, i) => {
              const c = colorMap[feat.color];
              const Icon = featureIcons[i];
              return (
                <motion.div
                  key={feat.title}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.07 }}
                  viewport={{ once: true }}
                  className={`p-6 rounded-2xl border ${c.border} ${c.bg}`}
                >
                  <div className={`w-10 h-10 rounded-xl bg-white dark:bg-slate-900 flex items-center justify-center mb-4 shadow-sm`}>
                    <Icon className={`w-5 h-5 ${c.icon}`} />
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2">{feat.title}</h3>
                  <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{feat.desc}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Branding API info */}
      <section className="py-16 px-4">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3 mb-6">
            <Layers className="w-6 h-6 text-violet-600 dark:text-violet-400" />
            <h2 className="text-xl font-bold text-slate-900 dark:text-white">{t.apiSectionTitle}</h2>
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-900 dark:bg-slate-950 overflow-hidden">
              <div className="px-4 py-2 bg-slate-800 border-b border-slate-700">
                <span className="text-xs font-mono text-slate-300">GET /api/v1/company/branding</span>
              </div>
              <pre className="p-4 text-xs text-emerald-300 overflow-x-auto">{`{
  "display_name": "Acme Corp",
  "logo_url": "https://cdn.../logo.png",
  "primary_color": "#7c3aed",
  "accent_color": "#db2777",
  "brand_mode": "custom"
}`}</pre>
            </div>
            <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-900 dark:bg-slate-950 overflow-hidden">
              <div className="px-4 py-2 bg-slate-800 border-b border-slate-700">
                <span className="text-xs font-mono text-slate-300">PATCH /api/v1/company/branding</span>
              </div>
              <pre className="p-4 text-xs text-emerald-300 overflow-x-auto">{`{
  "display_name": "Acme Corp",
  "primary_color": "#7c3aed",
  "accent_color": "#db2777"
}

# Upload logo : multipart/form-data
# field: logo (image/png|svg, max 2 MB)`}</pre>
            </div>
          </div>
        </div>
      </section>

      {/* Plans */}
      <section className="py-16 px-4 bg-transparent dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-8">{t.plansTitle}</h2>
          <div className="grid sm:grid-cols-3 gap-6">
            {plans.map((plan) => (
              <div
                key={plan.name}
                className={`rounded-2xl p-6 border ${
                  plan.highlight
                    ? 'border-violet-500 bg-violet-600 text-white shadow-lg shadow-violet-200 dark:shadow-violet-900/30'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900'
                }`}
              >
                {plan.highlight && (
                  <div className="flex items-center gap-1 text-violet-200 text-xs font-semibold mb-2">
                    <Sparkles className="w-3.5 h-3.5" /> {t.recommendedBadge}
                  </div>
                )}
                <p className={`font-black text-2xl mb-1 ${plan.highlight ? 'text-white' : 'text-slate-900 dark:text-white'}`}>
                  {plan.name}
                </p>
                <p className={`text-sm mb-4 ${plan.highlight ? 'text-violet-200' : 'text-slate-500 dark:text-slate-400'}`}>
                  {plan.price}
                </p>
                <ul className="space-y-2 text-sm text-start">
                  {plan.features.map((f) => (
                    <li key={f} className={`flex items-center gap-2 ${plan.highlight ? 'text-violet-100' : 'text-slate-600 dark:text-slate-400'}`}>
                      <Check className={`w-4 h-4 flex-shrink-0 ${plan.highlight ? 'text-white' : 'text-violet-500'}`} />
                      {f}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-20 px-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="max-w-2xl mx-auto text-center"
        >
          <h2 className="text-3xl font-black text-slate-900 dark:text-white mb-4">{t.ctaTitle}</h2>
          <p className="text-slate-500 dark:text-slate-400 mb-8">
            {t.ctaSub}
          </p>
          <Link
            href="/demo"
            className="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl font-semibold text-white"
            style={{ background: 'linear-gradient(135deg, #7c3aed, #db2777)' }}
          >
            {t.ctaBtn}
          </Link>
        </motion.div>
      </section>

      <Footer />
    </div>
  );
}

