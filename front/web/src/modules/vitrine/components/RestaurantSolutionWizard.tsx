'use client';

/**
 * Wizard « Je suis restaurateur » — pré-qualification publique.
 *
 * Flow : profil → questions (depuis le backend) → pack suggéré (cocher/
 * décocher avec raisons) → téléchargement (QR + liens + Edge + guide).
 *
 * 100 % open source côté front : framer-motion (déjà en deps), lib `qrcode`
 * (déjà en deps), pas d'API payante.
 *
 * Squelette pédagogique : chaque étape est volontairement simple à lire et
 * à étendre (voir docs/architecture/RESTAURANT_SOLUTION_SURVEY.md).
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import {
  ArrowLeft,
  ArrowRight,
  Check,
  CheckCircle2,
  Download,
  Fingerprint,
  HardDrive,
  Loader2,
  QrCode,
  Smartphone,
  Store,
  WifiOff,
} from 'lucide-react';
import QRCode from 'qrcode';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { mobileDownloadTarget, type MobileAppSlug } from '@/modules/vitrine/lib/mobile-download';
import {
  buildDefaultAnswers,
  fetchSurvey,
  localeFromAppLocale,
  solutionLabel,
  suggestPack,
  type SolutionSurveyQuestion,
  type SuggestedPackage,
  type SurveyAnswerValue,
  type VitrineLocale,
} from '@/modules/vitrine/lib/solution-survey';

type Step = 'intro' | 'questions' | 'suggestions' | 'download';

type WizardCopy = {
  title: string;
  subtitle: string;
  start: string;
  next: string;
  back: string;
  loading: string;
  questionsTitle: string;
  suggestionsTitle: string;
  suggestionsSubtitle: string;
  keep: string;
  uncheckNote: string;
  downloadTitle: string;
  downloadSubtitle: string;
  qrHint: string;
  edgeTitle: string;
  edgeCmdHint: string;
  guideLabel: string;
  includedLabel: string;
  restart: string;
};

const COPY: Record<VitrineLocale, WizardCopy> = {
  fr: {
    title: 'Je suis restaurateur',
    subtitle: 'Répondez à 3 questions : on vous propose automatiquement le pack Leopardo adapté à votre restaurant. Vous cochez, vous téléchargez.',
    start: 'Commencer',
    next: 'Voir mon pack',
    back: 'Retour',
    loading: 'Calcul de votre pack…',
    questionsTitle: 'Parlez-nous de votre restaurant',
    suggestionsTitle: 'Voici le pack recommandé pour vous',
    suggestionsSubtitle: 'Pré-coché selon vos réponses. Décochez ce dont vous n\'avez pas besoin.',
    keep: 'Continuer',
    uncheckNote: 'Ces éléments s\'activeront dans votre espace Leopardo à la création du compte.',
    downloadTitle: 'Téléchargez votre pack',
    downloadSubtitle: 'Scannez le QR pour installer les apps, ou suivez les liens ci-dessous.',
    qrHint: 'Scannez pour installer',
    edgeTitle: 'Nœud Edge local (offline)',
    edgeCmdHint: 'Installez le nœud local sur un mini-PC du restaurant :',
    guideLabel: 'Guide de démarrage',
    includedLabel: 'Inclus dans votre espace',
    restart: 'Recommencer',
  },
  en: {
    title: 'I am a restaurant owner',
    subtitle: 'Answer 3 questions: we automatically build the Leopardo pack for your restaurant. Tick, download.',
    start: 'Start',
    next: 'See my pack',
    back: 'Back',
    loading: 'Building your pack…',
    questionsTitle: 'Tell us about your restaurant',
    suggestionsTitle: 'Here is your recommended pack',
    suggestionsSubtitle: 'Pre-ticked from your answers. Untick what you don\'t need.',
    keep: 'Continue',
    uncheckNote: 'Selected items will be enabled in your Leopardo workspace on signup.',
    downloadTitle: 'Download your pack',
    downloadSubtitle: 'Scan the QR code to install the apps, or use the links below.',
    qrHint: 'Scan to install',
    edgeTitle: 'Local Edge node (offline)',
    edgeCmdHint: 'Install the local node on a mini-PC at the restaurant:',
    guideLabel: 'Getting started guide',
    includedLabel: 'Included in your workspace',
    restart: 'Start over',
  },
  tr: { title: 'Ben bir restoran sahibiyim', subtitle: '3 soruya yanıt verin: restoranınıza uygun Leopardo paketini otomatik önerelim. İşaretleyin, indirin.', start: 'Başla', next: 'Paketimi gör', back: 'Geri', loading: 'Paketiniz hesaplanıyor…', questionsTitle: 'Restoranınızdan bahsedin', suggestionsTitle: 'Size önerilen paket', suggestionsSubtitle: 'Yanıtlarınıza göre önceden işaretlendi.', keep: 'Devam', uncheckNote: 'Seçilen öğeler hesap oluşturulduğunda etkinleşir.', downloadTitle: 'Paketinizi indirin', downloadSubtitle: 'Uygulamaları kurmak için QR kodu okutun.', qrHint: 'Kurulum için okutun', edgeTitle: 'Yerel Edge düğümü (çevrimdışı)', edgeCmdHint: 'Yerel düğümü restorandaki bir mini-PC\'ye kurun:', guideLabel: 'Başlangıç rehberi', includedLabel: 'Çalışma alanınıza dahil', restart: 'Baştan başla' },
  ar: { title: 'أنا صاحب مطعم', subtitle: 'أجب عن 3 أسئلة وسنقترح عليك تلقائيًا حزمة Leopardo المناسبة لمطعمك. حدد ما تحتاجه وحمّله.', start: 'ابدأ', next: 'عرض حزمتي', back: 'رجوع', loading: 'جارٍ حساب الحزمة…', questionsTitle: 'حدثنا عن مطعمك', suggestionsTitle: 'هذه هي الحزمة المقترحة لك', suggestionsSubtitle: 'محددة مسبقًا حسب إجاباتك.', keep: 'متابعة', uncheckNote: 'سيتم تفعيل العناصر المحددة عند إنشاء الحساب.', downloadTitle: 'حمّل حزمتك', downloadSubtitle: 'امسح رمز QR لتثبيت التطبيقات.', qrHint: 'امسح للتثبيت', edgeTitle: 'عقدة Edge المحلية (بدون اتصال)', edgeCmdHint: 'ثبّت العقدة المحلية على جهاز صغير في المطعم:', guideLabel: 'دليل البدء', includedLabel: 'مضمن في مساحتك', restart: 'إعادة البدء' },
};

const EDGE_INSTALL_CMD = 'curl -fsSL https://gestionemployerbackend.onrender.com/api/v1/edge/install.sh | sudo bash -s -- --node-id <NODE_ID> --token <EDGE_TOKEN>';

export function RestaurantSolutionWizard() {
  const { locale, direction } = useVitrineLocale();
  const vLocale = localeFromAppLocale(locale);
  const c = COPY[vLocale] ?? COPY.en;

  const [step, setStep] = useState<Step>('intro');
  const [questions, setQuestions] = useState<SolutionSurveyQuestion[]>([]);
  const [answers, setAnswers] = useState<Record<string, SurveyAnswerValue>>({});
  const [result, setResult] = useState<{ packages: SuggestedPackage[]; total: number } | null>(null);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [qrUrls, setQrUrls] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Chargement du questionnaire (source de vérité = backend).
  useEffect(() => {
    let cancelled = false;
    fetchSurvey('restaurant').then((data) => {
      if (cancelled || !data) {
        return;
      }
      setQuestions(data.questions);
      setAnswers((prev) => ({ ...buildDefaultAnswers(data.questions), ...prev }));
    });
    return () => {
      cancelled = true;
    };
  }, []);

  const submitAnswers = useCallback(async () => {
    setLoading(true);
    setError(null);
    const data = await suggestPack('restaurant', answers);
    setLoading(false);
    if (!data) {
      setError('suggest_error');
      return;
    }
    setResult(data);
    setSelected(new Set(data.packages.map((p) => p.key)));
    setStep('suggestions');
  }, [answers]);

  const goDownload = useCallback(() => {
    setStep('download');
  }, []);

  const togglePackage = useCallback((key: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  }, []);

  const setAnswer = useCallback((key: string, value: SurveyAnswerValue) => {
    setAnswers((prev) => ({ ...prev, [key]: value }));
  }, []);

  // Génération des QR codes pour les apps mobiles (lib `qrcode`, gratuite).
  const mobilePackages = useMemo(
    () => (result?.packages ?? []).filter((p) => p.type === 'mobile' && p.app && selected.has(p.key)),
    [result, selected],
  );

  useEffect(() => {
    if (step !== 'download' || mobilePackages.length === 0) {
      return;
    }
    let cancelled = false;
    const generate = async () => {
      const entries = await Promise.all(
        mobilePackages.map(async (p) => {
          const target = mobileDownloadTarget(p.app as MobileAppSlug, 'android');
          const url = await QRCode.toDataURL(target.href, { width: 180, margin: 1 });
          return [p.key, url] as const;
        }),
      );
      if (!cancelled) {
        setQrUrls(Object.fromEntries(entries));
      }
    };
    void generate();
    return () => {
      cancelled = true;
    };
  }, [step, mobilePackages]);

  const selectedPackages = useMemo(
    () => (result?.packages ?? []).filter((p) => selected.has(p.key)),
    [result, selected],
  );

  const progress = step === 'intro' ? 0 : step === 'questions' ? 1 : step === 'suggestions' ? 2 : 3;

  return (
    <div dir={direction} className="w-full max-w-3xl mx-auto">
      {/* Barre de progression */}
      <div className="flex items-center gap-2 mb-8">
        {[0, 1, 2, 3].map((i) => (
          <div
            key={i}
            className={`h-1.5 flex-1 rounded-full transition-colors ${i <= progress ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-700'}`}
          />
        ))}
      </div>

      <AnimatePresence mode="wait">
        {step === 'intro' && (
          <motion.section
            key="intro"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            className="text-center py-10"
          >
            <div className="mx-auto mb-6 w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
              <Store className="w-8 h-8 text-emerald-600 dark:text-emerald-400" />
            </div>
            <h2 className="text-3xl font-bold text-slate-900 dark:text-white mb-3">{c.title}</h2>
            <p className="text-slate-600 dark:text-slate-300 max-w-xl mx-auto mb-8">{c.subtitle}</p>
            <button
              type="button"
              onClick={() => setStep('questions')}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-8 py-3 transition-colors"
            >
              {c.start}
              <ArrowRight className="w-5 h-5" />
            </button>
          </motion.section>
        )}

        {step === 'questions' && (
          <motion.section
            key="questions"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            className="py-6"
          >
            <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-6">{c.questionsTitle}</h2>
            <div className="space-y-6">
              {questions.map((q) => (
                <div
                  key={q.key}
                  className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"
                >
                  <p className="font-medium text-slate-900 dark:text-white mb-3">
                    {solutionLabel(q.label_key, vLocale, q.label_key)}
                  </p>
                  {q.type === 'bool' ? (
                    <div className="flex gap-3">
                      <button
                        type="button"
                        onClick={() => setAnswer(q.key, true)}
                        className={`flex-1 rounded-xl border px-4 py-2.5 text-sm font-medium transition-colors ${
                          answers[q.key] === true
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400'
                        }`}
                      >
                        {vLocale === 'fr' ? 'Oui' : 'Yes'}
                      </button>
                      <button
                        type="button"
                        onClick={() => setAnswer(q.key, false)}
                        className={`flex-1 rounded-xl border px-4 py-2.5 text-sm font-medium transition-colors ${
                          answers[q.key] === false
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400'
                        }`}
                      >
                        {vLocale === 'fr' ? 'Non' : 'No'}
                      </button>
                    </div>
                  ) : (
                    <div className="grid sm:grid-cols-2 gap-2">
                      {q.options?.map((opt) => (
                        <button
                          key={opt.value}
                          type="button"
                          onClick={() => setAnswer(q.key, opt.value)}
                          className={`rounded-xl border px-4 py-2.5 text-left text-sm font-medium transition-colors ${
                            answers[q.key] === opt.value
                              ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                              : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400'
                          }`}
                        >
                          {solutionLabel(opt.label_key, vLocale, opt.value)}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>

            {error && (
              <p className="mt-4 text-sm text-rose-600 dark:text-rose-400">
                {vLocale === 'fr' ? 'Impossible de calculer le pack. Réessayez.' : 'Could not build your pack. Try again.'}
              </p>
            )}

            <div className="mt-8 flex justify-between">
              <button
                type="button"
                onClick={() => setStep('intro')}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-6 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 hover:border-slate-400 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                {c.back}
              </button>
              <button
                type="button"
                onClick={() => void submitAnswers()}
                disabled={loading}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold px-6 py-3 transition-colors"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-5 h-5 animate-spin" />
                    {c.loading}
                  </>
                ) : (
                  <>
                    {c.next}
                    <ArrowRight className="w-5 h-5" />
                  </>
                )}
              </button>
            </div>
          </motion.section>
        )}

        {step === 'suggestions' && result && (
          <motion.section
            key="suggestions"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            className="py-6"
          >
            <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">{c.suggestionsTitle}</h2>
            <p className="text-slate-600 dark:text-slate-300 mb-6">{c.suggestionsSubtitle}</p>

            <div className="space-y-3">
              {result.packages.map((pkg) => {
                const isSelected = selected.has(pkg.key);
                return (
                  <button
                    key={pkg.key}
                    type="button"
                    onClick={() => togglePackage(pkg.key)}
                    className={`w-full text-left rounded-2xl border p-4 transition-colors ${
                      isSelected
                        ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-900/20'
                        : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 opacity-60'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <span
                        className={`mt-0.5 w-5 h-5 rounded-md border flex items-center justify-center shrink-0 ${
                          isSelected
                            ? 'bg-emerald-500 border-emerald-500 text-white'
                            : 'border-slate-300 dark:border-slate-600'
                        }`}
                      >
                        {isSelected && <Check className="w-3.5 h-3.5" />}
                      </span>
                      <span>
                        <span className="font-semibold text-slate-900 dark:text-white">
                          {solutionLabel(pkg.label_key, vLocale, pkg.key)}
                        </span>
                        <span className="block text-sm text-slate-500 dark:text-slate-400 mt-1">
                          {solutionLabel(pkg.reason_key, vLocale, pkg.reason_key)}
                        </span>
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>

            <p className="mt-4 text-xs text-slate-500 dark:text-slate-400">{c.uncheckNote}</p>

            <div className="mt-8 flex justify-between">
              <button
                type="button"
                onClick={() => setStep('questions')}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-6 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 hover:border-slate-400 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                {c.back}
              </button>
              <button
                type="button"
                onClick={goDownload}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 transition-colors"
              >
                {c.keep}
                <ArrowRight className="w-5 h-5" />
              </button>
            </div>
          </motion.section>
        )}

        {step === 'download' && selectedPackages.length > 0 && (
          <motion.section
            key="download"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            className="py-6"
          >
            <div className="flex items-center gap-3 mb-2">
              <Download className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
              <h2 className="text-2xl font-bold text-slate-900 dark:text-white">{c.downloadTitle}</h2>
            </div>
            <p className="text-slate-600 dark:text-slate-300 mb-8">{c.downloadSubtitle}</p>

            <div className="grid sm:grid-cols-2 gap-4">
              {selectedPackages.map((pkg) => {
                if (pkg.type === 'mobile' && pkg.app) {
                  const target = mobileDownloadTarget(pkg.app as MobileAppSlug, 'android');
                  return (
                    <div
                      key={pkg.key}
                      className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"
                    >
                      <div className="flex items-center gap-3 mb-3">
                        <Smartphone className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <p className="font-semibold text-slate-900 dark:text-white">
                          {solutionLabel(pkg.label_key, vLocale, pkg.key)}
                        </p>
                      </div>
                      <div className="flex items-center gap-4">
                        {qrUrls[pkg.key] && (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img
                            src={qrUrls[pkg.key]}
                            alt={c.qrHint}
                            className="w-24 h-24 rounded-lg border border-slate-200 dark:border-slate-700"
                          />
                        )}
                        <div className="space-y-2">
                          <a
                            href={target.href}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-2 text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                          >
                            <Download className="w-4 h-4" />
                            Android APK
                          </a>
                        </div>
                      </div>
                      <p className="mt-3 text-xs text-slate-400">{c.qrHint}</p>
                    </div>
                  );
                }

                if (pkg.type === 'edge' || pkg.download === 'edge_install') {
                  return (
                    <div
                      key={pkg.key}
                      className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"
                    >
                      <div className="flex items-center gap-3 mb-3">
                        <WifiOff className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <p className="font-semibold text-slate-900 dark:text-white">{c.edgeTitle}</p>
                      </div>
                      <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">{c.edgeCmdHint}</p>
                      <pre className="rounded-lg bg-slate-950 text-slate-100 text-xs p-3 overflow-x-auto whitespace-pre-wrap">
                        {EDGE_INSTALL_CMD}
                      </pre>
                    </div>
                  );
                }

                if (pkg.download === 'guide') {
                  return (
                    <div
                      key={pkg.key}
                      className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"
                    >
                      <div className="flex items-center gap-3 mb-2">
                        <Fingerprint className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <p className="font-semibold text-slate-900 dark:text-white">
                          {solutionLabel(pkg.label_key, vLocale, pkg.key)}
                        </p>
                      </div>
                      <a
                        href={`/api/v1/solutions/restaurant/pack?packages=${selectedPackages.map((p) => p.key).join(',')}`}
                        className="inline-flex items-center gap-2 text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                      >
                        <HardDrive className="w-4 h-4" />
                        {c.guideLabel}
                      </a>
                    </div>
                  );
                }

                return (
                  <div
                    key={pkg.key}
                    className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"
                  >
                    <div className="flex items-center gap-3">
                      <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                      <div>
                        <p className="font-semibold text-slate-900 dark:text-white">
                          {solutionLabel(pkg.label_key, vLocale, pkg.key)}
                        </p>
                        <p className="text-xs text-slate-500 dark:text-slate-400">{c.includedLabel}</p>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-8 flex justify-between">
              <button
                type="button"
                onClick={() => setStep('suggestions')}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-6 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 hover:border-slate-400 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                {c.back}
              </button>
              <button
                type="button"
                onClick={() => setStep('intro')}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-6 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 hover:border-slate-400 transition-colors"
              >
                <QrCode className="w-4 h-4" />
                {c.restart}
              </button>
            </div>
          </motion.section>
        )}
      </AnimatePresence>
    </div>
  );
}
