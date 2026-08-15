'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { CheckCircle2, ChevronRight, X, Users, Building, ShieldCheck } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { type StoredAuthUser, storeAuthSession } from '@/lib/i18n';

// Issue #2642 (QA 2026-08-15) : l'onboarding était 100 % en français pour
// tous les dashboards — localisé FR/EN/TR/AR (fallback FR).
const onboardingCopy: Record<string, { steps: Array<{ title: string; desc: string }>; validating: string; finish: string; next: string }> = {
  fr: {
    steps: [
      { title: 'Bienvenue sur Leopardo', desc: 'Découvrez votre nouvel espace RH en quelques étapes.' },
      { title: 'Ajoutez vos équipes', desc: 'Invitez vos employés pour commencer à pointer.' },
      { title: 'Finalisez la configuration', desc: 'Vos plannings et règles d\'entreprise sont prêts.' },
    ],
    validating: 'Validation...',
    finish: 'Terminer',
    next: 'Suivant',
  },
  en: {
    steps: [
      { title: 'Welcome to Leopardo', desc: 'Discover your new HR workspace in a few steps.' },
      { title: 'Add your teams', desc: 'Invite your employees to start clocking in.' },
      { title: 'Finish the setup', desc: 'Your schedules and company rules are ready.' },
    ],
    validating: 'Validating...',
    finish: 'Finish',
    next: 'Next',
  },
  tr: {
    steps: [
      { title: 'Leopardo\'ya hoş geldiniz', desc: 'Yeni İK alanınızı birkaç adımda keşfedin.' },
      { title: 'Ekiplerinizi ekleyin', desc: 'Çalışanlarınızı davet ederek puantaja başlayın.' },
      { title: 'Kurulumu tamamlayın', desc: 'Planlarınız ve şirket kurallarınız hazır.' },
    ],
    validating: 'Doğrulanıyor...',
    finish: 'Bitir',
    next: 'İleri',
  },
  ar: {
    steps: [
      { title: 'مرحباً بك في Leopardo', desc: 'اكتشف مساحة الموارد البشرية الجديدة في خطوات قليلة.' },
      { title: 'أضف فرقك', desc: 'ادعُ موظفيك لبدء تسجيل الحضور.' },
      { title: 'أكمل الإعداد', desc: 'جداولك وقواعد شركتك جاهزة.' },
    ],
    validating: 'جارٍ التحقق...',
    finish: 'إنهاء',
    next: 'التالي',
  },
};

export function OnboardingWizard({ user, onComplete }: { user: StoredAuthUser; onComplete: () => void }) {
  const [isOpen, setIsOpen] = useState(true);
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(false);

  const locale = useVitrineLocale().locale ?? 'fr';
  const copy = onboardingCopy[locale] ?? onboardingCopy.fr;

  const steps = copy.steps.map((stepCopy, index) => ({
    id: index + 1,
    title: stepCopy.title,
    desc: stepCopy.desc,
    icon: [Building, Users, ShieldCheck][index] ?? Building,
  }));

  const handleNext = async () => {
    if (step < steps.length) {
      setStep((s) => s + 1);
      return;
    }

    setLoading(true);
    try {
      // Marquer le onboarding comme terminé via l'endpoint dédié
      // (PATCH /api/v1/onboarding-setup/{stepKey}/complete), jamais via
      // /company/branding qui sert à la configuration visuelle du tenant.
      await apiFetch('/onboarding-setup/configure_schedules/complete', {
        method: 'PATCH',
      });

      // Update local user
      const updatedUser = { ...user, company: { ...user.company, metadata: { ...user.company?.metadata, onboarding_completed: true } } };
      storeAuthSession(null, updatedUser);

      setIsOpen(false);
      onComplete();
    } catch (e) {
      // Le wizard se ferme même si l'API échoue : ne pas bloquer l'utilisateur.
      console.error(e);
      setIsOpen(false);
      onComplete();
    } finally {
      setLoading(false);
    }
  };

  const handleDismiss = () => {
    setIsOpen(false);
    onComplete();
  };

  if (!isOpen) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.95 }}
          className="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl"
        >
          <button onClick={handleDismiss} className="absolute right-4 top-4 rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
            <X className="h-5 w-5" />
          </button>
          
          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-8 text-white">
            <div className="mb-4 flex items-center justify-between">
              <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                Étape {step} sur {steps.length}
              </span>
            </div>
            <h2 className="text-2xl font-black">{steps[step - 1].title}</h2>
            <p className="mt-2 text-emerald-50">{steps[step - 1].desc}</p>
          </div>

          <div className="p-8">
            <div className="space-y-6">
              {steps.map((s, idx) => {
                const isActive = step === s.id;
                const isPast = step > s.id;
                return (
                  <div key={s.id} className={`flex items-center gap-4 transition-opacity ${!isActive && !isPast ? 'opacity-40' : 'opacity-100'}`}>
                    <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${isPast ? 'bg-emerald-100 text-emerald-600' : isActive ? 'bg-teal-600 text-white shadow-lg' : 'bg-slate-100 text-slate-400'}`}>
                      {isPast ? <CheckCircle2 className="h-5 w-5" /> : <s.icon className="h-5 w-5" />}
                    </div>
                    <div>
                      <p className={`font-bold ${isActive ? 'text-slate-900' : 'text-slate-500'}`}>{s.title}</p>
                      {isActive && <p className="text-xs text-slate-500">{s.desc}</p>}
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-8 flex justify-end">
              <button
                onClick={handleNext}
                disabled={loading}
                className="flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3 font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
              >
                {loading ? copy.validating : step === steps.length ? copy.finish : copy.next}
                {!loading && step < steps.length && <ChevronRight className="h-4 w-4" />}
              </button>
            </div>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
}

