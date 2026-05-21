'use client';

import { useState } from 'react';
import { CheckCircle, ShieldCheck, Sparkles } from 'lucide-react';
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
  title: string;
  proof: Array<{ title: string; desc: string }>;
};

const signupCopy: Record<AppLocale, SignupCopy> = {
  fr: {
    hero: {
      badge: 'Essai gratuit',
      headline: 'Demarrez Leopardo RH avec un parcours guide',
      subheadline:
        'Capturez votre demande, qualifiez le besoin et basculez vers un espace adapte a votre entreprise.',
      cta: "Creer ma demande d'essai",
    },
    title: 'Un tunnel simple, mais pret pour la vente',
    proof: [
      { title: 'Qualification commerciale', desc: 'Plan, module et source marketing sont conserves.' },
      { title: 'Securite', desc: 'Le mot de passe ne part jamais vers les webhooks CRM ou email.' },
      { title: 'Suivi', desc: 'Chaque demande produit un identifiant lead exploitable.' },
    ],
  },
  en: {
    hero: {
      badge: 'Free trial',
      headline: 'Start Leopardo RH with a guided path',
      subheadline:
        'Capture the request, qualify the need and route the buyer to the right workspace.',
      cta: 'Create my trial request',
    },
    title: 'A simple funnel, ready for sales operations',
    proof: [
      { title: 'Sales qualification', desc: 'Plan, module and marketing source are preserved.' },
      { title: 'Security', desc: 'The password is never forwarded to CRM or email webhooks.' },
      { title: 'Tracking', desc: 'Every request returns a usable lead identifier.' },
    ],
  },
  tr: {
    hero: {
      badge: 'Ucretsiz deneme',
      headline: 'Leopardo RH denemesini rehberli baslatin',
      subheadline:
        'Talebi yakalayin, ihtiyaci nitelendirin ve aliciyi dogru calisma alanina yonlendirin.',
      cta: 'Deneme talebimi olustur',
    },
    title: 'Satis operasyonuna hazir sade bir funnel',
    proof: [
      { title: 'Satis nitelendirme', desc: 'Plan, modul ve pazarlama kaynagi korunur.' },
      { title: 'Guvenlik', desc: 'Sifre CRM veya e-posta webhooklarina gonderilmez.' },
      { title: 'Takip', desc: 'Her talep kullanilabilir bir lead kimligi uretir.' },
    ],
  },
  ar: {
    hero: {
      badge: 'تجربة مجانية',
      headline: 'ابدأ Leopardo RH عبر مسار موجه',
      subheadline:
        'نجمع الطلب ونؤهل الحاجة ثم نوجه العميل إلى المساحة المناسبة لشركته.',
      cta: 'إنشاء طلب التجربة',
    },
    title: 'مسار بسيط وجاهز للمبيعات',
    proof: [
      { title: 'تأهيل تجاري', desc: 'يتم حفظ الخطة والوحدة ومصدر الحملة.' },
      { title: 'أمان', desc: 'لا يتم إرسال كلمة المرور إلى CRM أو البريد.' },
      { title: 'تتبع', desc: 'كل طلب يحصل على معرف lead قابل للاستعمال.' },
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

      <main id="signup-form" className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/60 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-[0.9fr_1.1fr] gap-12 items-start">
          <section>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm font-semibold mb-5">
              <ShieldCheck className="w-4 h-4" />
              Funnel marketing operationnel
            </div>
            <h2 className="text-3xl sm:text-4xl font-black tracking-tight text-slate-950 dark:text-white mb-8">
              {copy.title}
            </h2>
            <div className="space-y-5">
              {copy.proof.map((item) => (
                <div key={item.title} className="flex gap-4">
                  <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 flex items-center justify-center">
                    <CheckCircle className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 dark:text-white">{item.title}</h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400">{item.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <SignupForm page="/signup" />
        </div>
      </main>

      <Footer />
    </div>
  );
}
