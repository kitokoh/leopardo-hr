'use client';

import { useState } from 'react';
import { CheckCircle, Clock3, ShieldCheck, Sparkles } from 'lucide-react';
import { Footer, HeroSection, Navbar, useScrollReveal } from '@/modules/vitrine';
import { SignupForm } from '@/modules/vitrine/components/forms';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';

type SignupCopy = {
  hero: {
    badge: string;
    headline: string;
    subheadline: string;
    cta: string;
  };
  sideBadge: string;
  title: string;
  proof: Array<{ title: string; desc: string }>;
  stepsTitle: string;
  steps: string[];
};

const signupCopy: Record<AppLocale, SignupCopy> = {
  fr: {
    hero: {
      badge: 'Essai guide',
      headline: 'Testez Leopardo RH sans tunnel complique',
      subheadline:
        "Un email professionnel suffit pour lancer une demande d'essai claire. Notre equipe qualifie votre contexte et prepare l'acces adapte.",
      cta: "Recevoir mon acces d'essai",
    },
    sideBadge: 'Funnel marketing operationnel',
    title: "Ce qui se passe apres votre demande",
    proof: [
      { title: 'Qualification utile', desc: 'Pays, taille, role et source marketing sont conserves.' },
      { title: 'Pas de faux compte', desc: 'Aucun mot de passe n est collecte tant que l espace n est pas cree.' },
      { title: 'Lead exploitable', desc: 'Chaque demande produit un identifiant utilisable par CRM ou platform admin.' },
    ],
    stepsTitle: 'Parcours clair en 3 temps',
    steps: [
      'Vous laissez votre email et le nom de votre entreprise.',
      'Leopardo qualifie le besoin et choisit le bon espace d essai.',
      'Vous recevez la suite sous 24h ouvrables, sans intervention invisible.',
    ],
  },
  en: {
    hero: {
      badge: 'Guided trial',
      headline: 'Try Leopardo RH without a heavy signup flow',
      subheadline:
        'A professional email is enough to start a clear trial request. Our team qualifies your context and prepares the right access.',
      cta: 'Get my trial access',
    },
    sideBadge: 'Operational marketing funnel',
    title: 'What happens after your request',
    proof: [
      { title: 'Useful qualification', desc: 'Country, size, role and marketing source are preserved.' },
      { title: 'No fake account', desc: 'No password is collected before the workspace is actually created.' },
      { title: 'Actionable lead', desc: 'Each request gets a lead identifier usable by CRM or platform admin.' },
    ],
    stepsTitle: 'A clear 3-step path',
    steps: [
      'You share your email and company name.',
      'Leopardo qualifies the need and selects the right trial workspace.',
      'You receive the next step within 24 business hours.',
    ],
  },
  tr: {
    hero: {
      badge: 'Rehberli deneme',
      headline: 'Leopardo RH yi agir bir kayit akisi olmadan deneyin',
      subheadline:
        'Profesyonel e-posta yeterlidir. Ekibimiz ihtiyacinizi nitelendirir ve uygun deneme erisimini hazirlar.',
      cta: 'Deneme erisimimi al',
    },
    sideBadge: 'Operasyonel pazarlama hunisi',
    title: 'Talebinizden sonra ne olur',
    proof: [
      { title: 'Kullanilabilir nitelendirme', desc: 'Ulke, ekip buyuklugu, rol ve kampanya kaynagi saklanir.' },
      { title: 'Sahte hesap yok', desc: 'Calisma alani gercekten olusturulmadan parola toplanmaz.' },
      { title: 'Aksiyon alinabilir lead', desc: 'Her talep CRM veya platform admin icin kullanilabilir bir kimlik uretir.' },
    ],
    stepsTitle: 'Net 3 adimli yol',
    steps: [
      'E-posta ve sirket adinizi birakirsiniz.',
      'Leopardo ihtiyaci nitelendirir ve uygun deneme alanini secer.',
      'Sonraki adimi 24 is saati icinde alirsiniz.',
    ],
  },
  ar: {
    hero: {
      badge: 'تجربة موجهة',
      headline: 'جرّب Leopardo RH بدون مسار تسجيل معقد',
      subheadline:
        'يكفي بريد مهني لبدء طلب تجربة واضح. يقوم فريقنا بفهم احتياجك ثم تجهيز الوصول المناسب.',
      cta: 'الحصول على وصول تجريبي',
    },
    sideBadge: 'مسار تسويقي عملي',
    title: 'ماذا يحدث بعد إرسال الطلب',
    proof: [
      { title: 'تأهيل مفيد', desc: 'نحتفظ بالبلد وحجم الفريق والدور ومصدر الحملة.' },
      { title: 'لا حساب وهمي', desc: 'لا يتم طلب كلمة مرور قبل إنشاء مساحة العمل فعليا.' },
      { title: 'طلب قابل للمتابعة', desc: 'كل طلب يحصل على معرف يمكن استخدامه في CRM أو إدارة المنصة.' },
    ],
    stepsTitle: 'مسار واضح من ثلاث خطوات',
    steps: [
      'تترك بريدك المهني واسم شركتك.',
      'يقوم Leopardo بفهم الاحتياج واختيار مساحة التجربة المناسبة.',
      'تصلك الخطوة التالية خلال 24 ساعة عمل.',
    ],
  },
};

export default function SignupPage() {
  const [isDark, setIsDark] = useState(false);
  const { locale, direction } = useVitrineLocale();
  const copy = signupCopy[locale] ?? signupCopy.fr;
  useScrollReveal();

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#signup-form' }}
        badge={{
          text: copy.hero.badge,
          icon: <Sparkles className="w-3 h-3" />,
        }}
      />

      <main id="signup-form" className="relative overflow-hidden py-24">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/60 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative mx-auto grid max-w-6xl grid-cols-1 items-start gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
          <section>
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
              <ShieldCheck className="h-4 w-4" />
              {copy.sideBadge}
            </div>
            <h2 className="mb-8 text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">
              {copy.title}
            </h2>
            <div className="space-y-5">
              {copy.proof.map((item) => (
                <div key={item.title} className="flex gap-4">
                  <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                    <CheckCircle className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 dark:text-white">{item.title}</h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400">{item.desc}</p>
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-10 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
              <div className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                <Clock3 className="h-4 w-4 text-emerald-500" />
                {copy.stepsTitle}
              </div>
              <ol className="space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {copy.steps.map((step, index) => (
                  <li key={step} className="flex gap-3">
                    <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                      {index + 1}
                    </span>
                    <span>{step}</span>
                  </li>
                ))}
              </ol>
            </div>
          </section>

          <SignupForm page="/signup" />
        </div>
      </main>

      <Footer />
    </div>
  );
}
