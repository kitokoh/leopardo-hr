'use client';

import React, { useReducer, useState, useRef, useCallback, useEffect } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion, AnimatePresence } from 'framer-motion';
import {
  AlertCircle,
  ArrowLeft,
  Building2,
  CheckCircle,
  Clock3,
  ClipboardCopy,
  Download,
  LogIn,
  Mail,
  Phone,
  Rocket,
  ShieldCheck,
  Sparkles,
  Users,
} from 'lucide-react';
import { Input } from '@/modules/vitrine/components/common/Input';
import { Button } from '@/modules/vitrine/components/common/Button';
import { Card } from '@/modules/vitrine/components/common/Card';
import { signupFormSchema, SignupFormData } from '@/modules/vitrine/lib/validation';
import { submitSignupForm, submitVerifyForm, fetchTrialStatus, createFormReducer, initialFormState, getLeadSource } from '@/modules/vitrine/lib/forms';
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';

interface SignupFormProps {
  page?: string;
  onSuccess?: (data: SignupFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}

type Step = 'form' | 'otp' | 'pending' | 'tracking' | 'success';

// #2469 : clé sessionStorage du token de provisioning (jamais dans l'URL).
const TRIAL_TOKEN_STORAGE_KEY = 'lp_trial_provisioning_token';
// Repli après ~60 s de polling (12 × 5 s).
const TRIAL_POLL_INTERVAL_MS = 5000;
const TRIAL_POLL_MAX_ATTEMPTS = 12;

const selectClassName =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white';

type SignupFormCopy = {
  badge: string; title: string; subtitle: string;
  labelEmail: string; placeholderEmail: string;
  labelCompany: string; placeholderCompany: string;
  labelRole: string; rolePlaceholder: string;
  roleFounder: string; roleManager: string; roleHr: string; roleOperations: string; roleOther: string;
  labelTeamSize: string; teamPlaceholder: string;
  labelPhone: string; placeholderPhone: string;
  operationsNote: string;
  agreePrefix: string; termsLink: string; privacyLink: string; agreeSuffix: string;
  submitLabel: string; submittingLabel: string;
  codeHint: string; haveAccount: string; loginCta: string;
  back: string; otpTitle: string; otpSentTo: string;
  otpInvalidLength: string; otpInvalidCode: string; otpVerifyError: string;
  verifyLabel: string; verifyingLabel: string; codeValidity: string; trackStatus: string;
  pendingTitle: string; pendingFallback: string; pendingNote: string;
  readyTitle: string; readySubtitle: string; accessCta: string;
  copyLink: string; linkCopied: string; linkEmailed: string;
  failedTitle: string; failedBody: string;
  timeoutTitle: string; timeoutBody: string; refreshStatus: string;
  preparingTitle: string; preparingBody: string; statusFor: string; statusEvery5s: string;
  successTitle: string; emailVerified: string; credsLabel: string;
  fieldEmail: string; fieldPassword: string; copyPasswordTitle: string; copied: string;
  credsSentByEmail: string; credsEmailed: string; trialNote: string; trialNoteSuffix: string;
  downloadApp: string; changePasswordNote: string; defaultError: string;
};

const signupFormCopy: Record<AppLocale, SignupFormCopy> = {
  fr: {
    badge: 'Essai gratuit 30 jours',
    title: 'Tester Leopardo avec votre entreprise',
    subtitle: "Créez votre espace d'essai en 2 minutes. Aucune carte bancaire requise.",
    labelEmail: 'Email professionnel',
    placeholderEmail: 'vous@entreprise.com',
    labelCompany: 'Entreprise',
    placeholderCompany: 'Nom de votre entreprise',
    labelRole: 'Votre rôle',
    rolePlaceholder: 'Choisir',
    roleFounder: 'Fondateur / dirigeant',
    roleManager: 'Manager',
    roleHr: 'RH',
    roleOperations: 'Opérations terrain',
    roleOther: 'Autre',
    labelTeamSize: 'Taille équipe',
    teamPlaceholder: 'Choisir',
    labelPhone: 'Téléphone (optionnel)',
    placeholderPhone: '+213 555 000 000',
    operationsNote: "Nous préparerons un parcours axé terrain : pointage, tâches, kiosk et suivi d'équipe.",
    agreePrefix: "J'accepte les",
    termsLink: "conditions d'utilisation",
    privacyLink: 'politique de confidentialité',
    agreeSuffix: 'et la',
    submitLabel: 'Recevoir mon code de vérification',
    submittingLabel: 'Envoi du code...',
    codeHint: 'Un code à 6 chiffres sera envoyé à votre email pour confirmer votre identité.',
    haveAccount: 'Vous avez déjà un compte ?',
    loginCta: 'Se connecter',
    back: 'Retour',
    otpTitle: 'Vérifiez votre email',
    otpSentTo: 'Nous avons envoyé un code de vérification à 6 chiffres à :',
    otpInvalidLength: 'Veuillez entrer les 6 chiffres du code.',
    otpInvalidCode: 'Code invalide ou expiré.',
    otpVerifyError: 'Erreur lors de la vérification. Veuillez réessayer.',
    verifyLabel: 'Vérifier et créer mon espace',
    verifyingLabel: 'Vérification en cours...',
    codeValidity: 'Le code est valide pendant 30 minutes. Vérifiez vos spams si vous ne le trouvez pas.',
    trackStatus: "Suivre l'état de mon espace",
    pendingTitle: "Demande d'essai reçue",
    pendingFallback: "Demande d'essai reçue. Notre équipe vous contacte sous 24h ouvrables.",
    pendingNote: "Notre système de création d'espace instantané est momentanément indisponible (redémarrage serveur). Votre demande est bien enregistrée : une personne de l'équipe Leopardo vous contactera par email sous 24h ouvrables avec un accès adapté à votre contexte.",
    readyTitle: 'Votre espace est prêt !',
    readySubtitle: 'Le sandbox de démonstration est provisionné. Accédez-y directement :',
    accessCta: 'Accéder à mon espace',
    copyLink: 'Copier le lien',
    linkCopied: 'Lien copié !',
    linkEmailed: "Votre lien d'accès a également été envoyé par email.",
    failedTitle: 'Création interrompue',
    failedBody: "Une erreur est survenue lors de la création de votre espace. Notre équipe vous contactera par email sous 24h ouvrables avec un accès adapté.",
    timeoutTitle: 'Création toujours en cours',
    timeoutBody: "Votre espace est en cours de préparation. Nous vous enverrons le lien d'accès par email dès qu'il sera prêt.",
    refreshStatus: 'Rafraîchir le statut',
    preparingTitle: 'Préparation de votre espace',
    preparingBody: 'Nous provisionnons votre sandbox de démonstration. Cela prend généralement moins de 30 secondes.',
    statusFor: 'Pour :',
    statusEvery5s: 'Statut vérifié toutes les 5 secondes.',
    successTitle: 'Votre espace est prêt !',
    emailVerified: 'Votre adresse email a bien été vérifiée.',
    credsLabel: 'Identifiants de connexion',
    fieldEmail: 'Email',
    fieldPassword: 'Mot de passe',
    copyPasswordTitle: 'Copier le mot de passe',
    copied: 'Copié !',
    credsSentByEmail: 'Ces identifiants ont aussi été envoyés par email à',
    credsEmailed: 'Vos identifiants de connexion viennent de vous être envoyés par email.',
    trialNote: 'Essai gratuit de',
    trialNoteSuffix: 'aucune carte bancaire requise',
    downloadApp: "Télécharger l'app",
    changePasswordNote: 'Changez votre mot de passe dès la première connexion.',
    defaultError: 'Une erreur est survenue',
  },
  en: {
    badge: 'Free 30-day trial',
    title: 'Try Leopardo with your company',
    subtitle: 'Create your trial workspace in 2 minutes. No credit card required.',
    labelEmail: 'Work email',
    placeholderEmail: 'you@company.com',
    labelCompany: 'Company',
    placeholderCompany: 'Your company name',
    labelRole: 'Your role',
    rolePlaceholder: 'Select',
    roleFounder: 'Founder / CEO',
    roleManager: 'Manager',
    roleHr: 'HR',
    roleOperations: 'Field operations',
    roleOther: 'Other',
    labelTeamSize: 'Team size',
    teamPlaceholder: 'Select',
    labelPhone: 'Phone (optional)',
    placeholderPhone: '+1 555 000 0000',
    operationsNote: 'We will prepare a field-focused setup: attendance, tasks, kiosk and team monitoring.',
    agreePrefix: 'I accept the',
    termsLink: 'terms of use',
    privacyLink: 'privacy policy',
    agreeSuffix: 'and the',
    submitLabel: 'Get my verification code',
    submittingLabel: 'Sending code...',
    codeHint: 'A 6-digit code will be sent to your email to confirm your identity.',
    haveAccount: 'Already have an account?',
    loginCta: 'Sign in',
    back: 'Back',
    otpTitle: 'Verify your email',
    otpSentTo: 'We sent a 6-digit verification code to:',
    otpInvalidLength: 'Please enter the 6 digits of the code.',
    otpInvalidCode: 'Invalid or expired code.',
    otpVerifyError: 'An error occurred during verification. Please try again.',
    verifyLabel: 'Verify and create my workspace',
    verifyingLabel: 'Verifying...',
    codeValidity: 'The code is valid for 30 minutes. Check your spam folder if you cannot find it.',
    trackStatus: 'Track my workspace status',
    pendingTitle: 'Trial request received',
    pendingFallback: 'Trial request received. Our team will contact you within 24 business hours.',
    pendingNote: 'Our instant workspace provisioning is temporarily unavailable (server restart). Your request is recorded: a Leopardo team member will email you within 24 business hours with access suited to your context.',
    readyTitle: 'Your workspace is ready!',
    readySubtitle: 'The demo sandbox is provisioned. Access it directly:',
    accessCta: 'Access my workspace',
    copyLink: 'Copy link',
    linkCopied: 'Link copied!',
    linkEmailed: 'Your access link was also sent by email.',
    failedTitle: 'Creation interrupted',
    failedBody: 'An error occurred while creating your workspace. Our team will contact you by email within 24 business hours with adapted access.',
    timeoutTitle: 'Still being created',
    timeoutBody: 'Your workspace is being prepared. We will email you the access link as soon as it is ready.',
    refreshStatus: 'Refresh status',
    preparingTitle: 'Preparing your workspace',
    preparingBody: 'We are provisioning your demo sandbox. This usually takes less than 30 seconds.',
    statusFor: 'For:',
    statusEvery5s: 'Status checked every 5 seconds.',
    successTitle: 'Your workspace is ready!',
    emailVerified: 'Your email address has been verified.',
    credsLabel: 'Sign-in credentials',
    fieldEmail: 'Email',
    fieldPassword: 'Password',
    copyPasswordTitle: 'Copy password',
    copied: 'Copied!',
    credsSentByEmail: 'These credentials were also sent by email to',
    credsEmailed: 'Your sign-in credentials were just sent by email.',
    trialNote: 'Free trial of',
    trialNoteSuffix: 'no credit card required',
    downloadApp: 'Download the app',
    changePasswordNote: 'Change your password on first sign-in.',
    defaultError: 'Something went wrong',
  },
  tr: {
    badge: '30 günlük ücretsiz deneme',
    title: "Leopardo'yu şirketinizle deneyin",
    subtitle: 'Deneme alanınızı 2 dakikada oluşturun. Kredi kartı gerekmez.',
    labelEmail: 'İş e-postası',
    placeholderEmail: 'siz@sirket.com',
    labelCompany: 'Şirket',
    placeholderCompany: 'Şirketinizin adı',
    labelRole: 'Rolünüz',
    rolePlaceholder: 'Seçin',
    roleFounder: 'Kurucu / Yönetici',
    roleManager: 'Yönetici',
    roleHr: 'İK',
    roleOperations: 'Saha operasyonları',
    roleOther: 'Diğer',
    labelTeamSize: 'Ekip boyutu',
    teamPlaceholder: 'Seçin',
    labelPhone: 'Telefon (isteğe bağlı)',
    placeholderPhone: '+90 555 000 0000',
    operationsNote: 'Sahaya odaklı bir kurulum hazırlayacağız: yoklama, görevler, kiosk ve ekip takibi.',
    agreePrefix: 'Kabul ediyorum:',
    termsLink: 'kullanım koşulları',
    privacyLink: 'gizlilik politikası',
    agreeSuffix: 've',
    submitLabel: 'Doğrulama kodumu al',
    submittingLabel: 'Kod gönderiliyor...',
    codeHint: 'Kimliğinizi doğrulamak için e-postanıza 6 haneli bir kod gönderilecek.',
    haveAccount: 'Zaten hesabınız var mı?',
    loginCta: 'Giriş yap',
    back: 'Geri',
    otpTitle: 'E-postanızı doğrulayın',
    otpSentTo: '6 haneli doğrulama kodunu şu adrese gönderdik:',
    otpInvalidLength: 'Lütfen kodun 6 hanesini girin.',
    otpInvalidCode: 'Geçersiz veya süresi dolmuş kod.',
    otpVerifyError: 'Doğrulama sırasında bir hata oluştu. Lütfen tekrar deneyin.',
    verifyLabel: 'Doğrula ve alanımı oluştur',
    verifyingLabel: 'Doğrulanıyor...',
    codeValidity: 'Kod 30 dakika geçerlidir. Bulamazsanız spam klasörünü kontrol edin.',
    trackStatus: 'Alanımın durumunu takip et',
    pendingTitle: 'Deneme talebi alındı',
    pendingFallback: 'Deneme talebi alındı. Ekibimiz 24 iş saati içinde sizinle iletişime geçecek.',
    pendingNote: 'Anlık alan oluşturma hizmetimiz geçici olarak kullanılamıyor (sunucu yeniden başlatıldı). Talebiniz kaydedildi: Leopardo ekibinden biri 24 iş saati içinde size uygun erişimle e-posta gönderecek.',
    readyTitle: 'Alanınız hazır!',
    readySubtitle: 'Demo sandbox hazır. Doğrudan erişin:',
    accessCta: 'Alanıma eriş',
    copyLink: 'Bağlantıyı kopyala',
    linkCopied: 'Bağlantı kopyalandı!',
    linkEmailed: 'Erişim bağlantınız e-postayla da gönderildi.',
    failedTitle: 'Oluşturma kesintiye uğradı',
    failedBody: 'Alanınız oluşturulurken bir hata oluştu. Ekibimiz 24 iş saati içinde uygun erişimle e-posta gönderecek.',
    timeoutTitle: 'Oluşturma hâlâ sürüyor',
    timeoutBody: 'Alanınız hazırlanıyor. Hazır olduğunda erişim bağlantısını e-postayla göndereceğiz.',
    refreshStatus: 'Durumu yenile',
    preparingTitle: 'Alanınız hazırlanıyor',
    preparingBody: 'Demo sandbox sağlanıyor. Bu genellikle 30 saniyeden az sürer.',
    statusFor: 'İçin:',
    statusEvery5s: 'Durum her 5 saniyede bir kontrol edilir.',
    successTitle: 'Alanınız hazır!',
    emailVerified: 'E-posta adresiniz doğrulandı.',
    credsLabel: 'Giriş bilgileri',
    fieldEmail: 'E-posta',
    fieldPassword: 'Parola',
    copyPasswordTitle: 'Parolayı kopyala',
    copied: 'Kopyalandı!',
    credsSentByEmail: 'Bu bilgiler e-postayla da gönderildi:',
    credsEmailed: 'Giriş bilgileriniz e-postayla gönderildi.',
    trialNote: 'Ücretsiz deneme:',
    trialNoteSuffix: 'kredi kartı gerekmez',
    downloadApp: 'Uygulamayı indir',
    changePasswordNote: 'İlk girişte parolanızı değiştirin.',
    defaultError: 'Bir hata oluştu',
  },
  ar: {
    badge: 'تجربة مجانية لمدة 30 يومًا',
    title: 'جرّب Leopardo مع شركتك',
    subtitle: 'أنشئ مساحة تجربتك في دقيقتين. لا حاجة لبطاقة ائتمانية.',
    labelEmail: 'البريد المهني',
    placeholderEmail: 'you@company.com',
    labelCompany: 'الشركة',
    placeholderCompany: 'اسم شركتك',
    labelRole: 'دورك',
    rolePlaceholder: 'اختر',
    roleFounder: 'مؤسس / مدير عام',
    roleManager: 'مدير',
    roleHr: 'موارد بشرية',
    roleOperations: 'عمليات ميدانية',
    roleOther: 'أخرى',
    labelTeamSize: 'حجم الفريق',
    teamPlaceholder: 'اختر',
    labelPhone: 'الهاتف (اختياري)',
    placeholderPhone: '+213 555 000 000',
    operationsNote: 'سنُعد مسارًا ميدانيًا: الحضور، المهام، الكشك ومتابعة الفريق.',
    agreePrefix: 'أوافق على',
    termsLink: 'شروط الاستخدام',
    privacyLink: 'سياسة الخصوصية',
    agreeSuffix: 'و',
    submitLabel: 'استلام رمز التحقق',
    submittingLabel: 'جارٍ إرسال الرمز...',
    codeHint: 'سيتم إرسال رمز من 6 أرقام إلى بريدك لتأكيد هويتك.',
    haveAccount: 'لديك حساب بالفعل؟',
    loginCta: 'تسجيل الدخول',
    back: 'رجوع',
    otpTitle: 'تحقق من بريدك الإلكتروني',
    otpSentTo: 'أرسلنا رمز تحقق من 6 أرقام إلى:',
    otpInvalidLength: 'يرجى إدخال الأرقام الستة للرمز.',
    otpInvalidCode: 'رمز غير صالح أو منتهي الصلاحية.',
    otpVerifyError: 'حدث خطأ أثناء التحقق. يرجى المحاولة مرة أخرى.',
    verifyLabel: 'تحقق وأنشئ مساحتي',
    verifyingLabel: 'جارٍ التحقق...',
    codeValidity: 'الرمز صالح لمدة 30 دقيقة. تحقق من البريد العشوائي إذا لم تجده.',
    trackStatus: 'تتبع حالة مساحتي',
    pendingTitle: 'تم استلام طلب التجربة',
    pendingFallback: 'تم استلام طلب التجربة. سيتواصل معك فريقنا خلال 24 ساعة عمل.',
    pendingNote: 'خدمة الإنشاء الفوري غير متاحة مؤقتًا (إعادة تشغيل الخادم). تم تسجيل طلبك: سيتواصل معك أحد أعضاء فريق Leopardo عبر البريد خلال 24 ساعة عمل مع وصول مناسب لسياقك.',
    readyTitle: 'مساحتك جاهزة!',
    readySubtitle: 'تم تجهيز بيئة العرض. يمكنك الوصول مباشرة:',
    accessCta: 'الوصول إلى مساحتي',
    copyLink: 'نسخ الرابط',
    linkCopied: 'تم نسخ الرابط!',
    linkEmailed: 'تم أيضًا إرسال رابط الوصول عبر البريد الإلكتروني.',
    failedTitle: 'تم إيقاف الإنشاء',
    failedBody: 'حدث خطأ أثناء إنشاء مساحتك. سيتواصل معك فريقنا عبر البريد خلال 24 ساعة عمل بوصول مناسب.',
    timeoutTitle: 'لا يزال الإنشاء جاريًا',
    timeoutBody: 'مساحتك قيد التجهيز. سنرسل لك رابط الوصول عبر البريد فور جاهزيته.',
    refreshStatus: 'تحديث الحالة',
    preparingTitle: 'تجهيز مساحتك',
    preparingBody: 'نقوم بتجهيز بيئة العرض. يستغرق هذا عادة أقل من 30 ثانية.',
    statusFor: 'إلى:',
    statusEvery5s: 'يتم التحقق من الحالة كل 5 ثوانٍ.',
    successTitle: 'مساحتك جاهزة!',
    emailVerified: 'تم التحقق من بريدك الإلكتروني بنجاح.',
    credsLabel: 'بيانات تسجيل الدخول',
    fieldEmail: 'البريد الإلكتروني',
    fieldPassword: 'كلمة المرور',
    copyPasswordTitle: 'نسخ كلمة المرور',
    copied: 'تم النسخ!',
    credsSentByEmail: 'تم إرسال هذه البيانات أيضًا عبر البريد إلى',
    credsEmailed: 'تم إرسال بيانات تسجيل الدخول إليك عبر البريد.',
    trialNote: 'تجربة مجانية لمدة',
    trialNoteSuffix: 'لا حاجة لبطاقة ائتمانية',
    downloadApp: 'تحميل التطبيق',
    changePasswordNote: 'غيّر كلمة مرورك عند أول تسجيل دخول.',
    defaultError: 'حدث خطأ ما',
  },
};


export function SignupForm({
  page = '/signup',
  onSuccess,
  onError,
  className = '',
}: SignupFormProps) {
  const { locale } = useVitrineLocale();
  const c = signupFormCopy[locale] ?? signupFormCopy.fr;

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
    watch,
  } = useForm<SignupFormData>({
    resolver: zodResolver(signupFormSchema(locale)),
    mode: 'onBlur',
  });

  const [formState, dispatch] = useReducer(createFormReducer(), initialFormState);
  const { trackSignup } = useAnalyticsForm();
  const role = watch('role');

  // Multi-step state
  const [currentStep, setCurrentStep] = useState<Step>('form');
  const [pendingEmail, setPendingEmail] = useState('');
  const [otpValues, setOtpValues] = useState<string[]>(['', '', '', '', '', '']);
  const [otpError, setOtpError] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  const [provisionedData, setProvisionedData] = useState<{
    manager?: { email: string; temp_password: string };
    trial?: { days: number; ends_at: string };
    company?: { name: string };
  } | null>(null);
  const [pendingMessage, setPendingMessage] = useState('');
  const [copied, setCopied] = useState(false);

  // #2469 — suivi du provisioning du guided trial
  const [trialToken, setTrialToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    try {
      return sessionStorage.getItem(TRIAL_TOKEN_STORAGE_KEY);
    } catch {
      return null;
    }
  });
  const [trialStatus, setTrialStatus] = useState<'pending' | 'ready' | 'failed' | 'unknown'>('pending');
  const [trialLoginUrl, setTrialLoginUrl] = useState('');
  const [trialTimedOut, setTrialTimedOut] = useState(false);
  const [isTracking, setIsTracking] = useState(false);

  const persistTrialToken = (token: string | null | undefined) => {
    if (!token) return;
    setTrialToken(token);
    try {
      sessionStorage.setItem(TRIAL_TOKEN_STORAGE_KEY, token);
    } catch {
      // sessionStorage indisponible (SSR/sandboxé) — le suivi restera en mémoire
    }
  };

  // #2469 — polling du statut (pending → ready/failed) tant que l'écran de
  // suivi est affiché ; repli honnête après ~60 s.
  useEffect(() => {
    if (currentStep !== 'tracking' || !trialToken) return;

    let cancelled = false;
    let attempts = 0;
    let intervalId: ReturnType<typeof setInterval> | null = null;

    const poll = async () => {
      if (cancelled) return;
      const res = await fetchTrialStatus(trialToken);
      if (cancelled) return;
      if (res.success && res.data?.status) {
        const status = res.data.status;
        if (status === 'ready') {
          setTrialStatus('ready');
          setTrialLoginUrl(res.data.login_url || '');
          if (intervalId) clearInterval(intervalId);
          return;
        }
        if (status === 'failed') {
          setTrialStatus('failed');
          if (intervalId) clearInterval(intervalId);
          return;
        }
      }
      attempts += 1;
      if (attempts >= TRIAL_POLL_MAX_ATTEMPTS) {
        setTrialTimedOut(true);
        if (intervalId) clearInterval(intervalId);
      }
    };

    void poll();
    intervalId = setInterval(() => void poll(), TRIAL_POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      if (intervalId) clearInterval(intervalId);
    };
  }, [currentStep, trialToken]);

  const startTracking = () => {
    if (!trialToken) return;
    setTrialStatus('pending');
    setTrialTimedOut(false);
    setIsTracking(true);
    setCurrentStep('tracking');
  };

  const copyPassword = async (password: string) => {
    try {
      await navigator.clipboard.writeText(password);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      const textarea = document.createElement('textarea');
      textarea.value = password;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  // ── Step 1: Submit signup form ──
  const onSubmit = async (data: SignupFormData) => {
    dispatch({ type: 'SUBMIT_START' });

    try {
      const response = await submitSignupForm(data, page);

      if (response.success) {
        trackSignup(data.email, {
          source: getLeadSource(),
          page,
          company: data.company,
          role: data.role,
          employees: data.employees,
        });

        setPendingEmail(data.email);
        dispatch({ type: 'RESET' });

        // #2469 : on conserve le token de provisioning (quand le backend en
        // renvoie un) pour permettre le suivi du statut sans email.
        persistTrialToken(response.data?.provisioning_token);

        if (response.provisioned === false) {
          // Backend could not send an OTP right now (e.g. cold-start timeout).
          // The lead was still captured, so tell the user honestly instead of
          // showing a verification screen for a code that was never sent.
          setPendingMessage(
            response.message ||
              "c.pendingFallback"
          );
          setCurrentStep('pending');
        } else {
          setCurrentStep('otp');
        }
      } else {
        dispatch({
          type: 'SUBMIT_ERROR',
          payload: {
            message: response.error || response.message,
          },
        });
        onError?.(response.error || response.message);
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : c.defaultError;
      dispatch({
        type: 'SUBMIT_ERROR',
        payload: { message: errorMessage },
      });
      onError?.(errorMessage);
    }
  };

  // ── OTP input handlers ──
  const handleOtpChange = useCallback((index: number, value: string) => {
    if (!/^\d*$/.test(value)) return;

    const newValues = [...otpValues];
    newValues[index] = value.slice(-1);
    setOtpValues(newValues);
    setOtpError('');

    // Auto-focus next input
    if (value && index < 5) {
      otpRefs.current[index + 1]?.focus();
    }
  }, [otpValues]);

  const handleOtpKeyDown = useCallback((index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' && !otpValues[index] && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }
  }, [otpValues]);

  const handleOtpPaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    if (pasted.length === 0) return;
    const newValues = [...otpValues];
    for (let i = 0; i < 6; i++) {
      newValues[i] = pasted[i] || '';
    }
    setOtpValues(newValues);
    const focusIdx = Math.min(pasted.length, 5);
    otpRefs.current[focusIdx]?.focus();
  }, [otpValues]);

  // ── Step 2: Verify OTP ──
  const handleVerify = async () => {
    const code = otpValues.join('');
    if (code.length !== 6) {
      setOtpError('c.otpInvalidLength');
      return;
    }

    setIsVerifying(true);
    setOtpError('');

    try {
      const response = await submitVerifyForm(pendingEmail, code);

      if (response.success) {
        setProvisionedData(response.data);
        setCurrentStep('success');
        reset();
        onSuccess?.({} as SignupFormData);
      } else {
        setOtpError(response.message || 'c.otpInvalidCode');
      }
    } catch (error) {
      setOtpError('c.otpVerifyError');
    } finally {
      setIsVerifying(false);
    }
  };

  // ── Render ──
  return (
    <Card className={`p-6 md:p-8 ${className}`}>
      <AnimatePresence mode="wait">
        {/* ═══════════════════════════════════════ */}
        {/* STEP 1: Signup Form                     */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'form' && (
          <motion.div
            key="step-form"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
          >
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
              <Sparkles className="h-3.5 w-3.5" />
              {c.badge}
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white md:text-3xl">
              {c.title}
            </h2>
            <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {c.subtitle}
            </p>

            {formState.isError && (
              <motion.div
                initial={{ opacity: 0, y: -10 }}
                animate={{ opacity: 1, y: 0 }}
                className="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
              >
                <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                <div>
                  <p className="font-semibold text-red-900 dark:text-red-100">{formState.message}</p>
                </div>
              </motion.div>
            )}

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              <Input
                label={c.labelEmail}
                type="email"
                placeholder={c.placeholderEmail}
                icon={<Mail className="h-4 w-4" />}
                error={errors.email?.message}
                required
                {...register('email')}
              />

              <Input
                label={c.labelCompany}
                type="text"
                placeholder={c.placeholderCompany}
                icon={<Building2 className="h-4 w-4" />}
                error={errors.company?.message}
                required
                {...register('company')}
              />

              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                  <span className="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">
                    {c.labelRole}
                  </span>
                  <select
                    className={selectClassName}
                    aria-invalid={errors.role ? true : undefined}
                    aria-describedby={errors.role ? 'signup-role-error' : undefined}
                    {...register('role')}
                  >
                    <option value="">{c.rolePlaceholder}</option>
                    <option value="founder">{c.roleFounder}</option>
                    <option value="manager">{c.roleManager}</option>
                    <option value="hr">{c.roleHr}</option>
                    <option value="operations">{c.roleOperations}</option>
                    <option value="other">{c.roleOther}</option>
                  </select>
                  {errors.role && (
                    <p id="signup-role-error" role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">
                      {errors.role.message}
                    </p>
                  )}
                </label>

                <label className="block">
                  <span className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <Users className="h-4 w-4" />
                    {c.labelTeamSize}
                  </span>
                  <select
                    className={selectClassName}
                    aria-invalid={errors.employees ? true : undefined}
                    aria-describedby={errors.employees ? 'signup-employees-error' : undefined}
                    {...register('employees')}
                  >
                    <option value="">Choisir</option>
                    <option value="1-10">1-10</option>
                    <option value="11-50">11-50</option>
                    <option value="51-200">51-200</option>
                    <option value="201-500">201-500</option>
                    <option value="500+">500+</option>
                  </select>
                  {errors.employees && (
                    <p id="signup-employees-error" role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">
                      {errors.employees.message}
                    </p>
                  )}
                </label>
              </div>

              <Input
                label={c.labelPhone}
                type="tel"
                placeholder={c.placeholderPhone}
                icon={<Phone className="h-4 w-4" />}
                error={errors.phone?.message}
                {...register('phone')}
              />

              {role === 'operations' && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                  {c.operationsNote}
                </div>
              )}

              <div className="flex items-start gap-3">
                <input
                  type="checkbox"
                  id="agreeToTerms"
                  className="mt-1 h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                  aria-invalid={errors.agreeToTerms ? true : undefined}
                  aria-describedby={errors.agreeToTerms ? 'signup-agree-terms-error' : undefined}
                  {...register('agreeToTerms')}
                />
                <label htmlFor="agreeToTerms" className="text-sm text-slate-600 dark:text-slate-400">
                  {c.agreePrefix}{' '}
                  <Link href="/terms" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    {c.termsLink}
                  </Link>{' '}
                  {c.agreeSuffix}{' '}
                  <Link href="/privacy" className="font-semibold text-emerald-600 hover:text-emerald-700">
                    {c.privacyLink}
                  </Link>
                </label>
              </div>
              {errors.agreeToTerms && (
                <p id="signup-agree-terms-error" role="alert" className="text-sm text-red-600 dark:text-red-400">
                  {errors.agreeToTerms.message}
                </p>
              )}

              <Button
                type="submit"
                variant="primary"
                size="lg"
                fullWidth
                loading={formState.isSubmitting}
                disabled={formState.isSubmitting}
              >
                {formState.isSubmitting ? c.submittingLabel : c.submitLabel}
              </Button>

              <p className="rounded-xl bg-transparent px-4 py-3 text-center text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                {c.codeHint}
              </p>

              <p className="text-center text-sm text-slate-600 dark:text-slate-400">
                {c.haveAccount}{' '}
                <Link href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                  {c.loginCta}
                </Link>
              </p>
            </form>
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2: OTP Verification                */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'otp' && (
          <motion.div
            key="step-otp"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            <button
              type="button"
              onClick={() => {
                setCurrentStep('form');
                setOtpValues(['', '', '', '', '', '']);
                setOtpError('');
              }}
              className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              <ArrowLeft className="h-4 w-4" />
              {c.back}
            </button>

            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
              <ShieldCheck className="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
              {c.otpTitle}
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {c.otpSentTo}
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-600 dark:text-emerald-400">
              {pendingEmail}
            </p>

            {/* OTP Inputs */}
            <div className="mb-4 flex justify-center gap-2">
              {otpValues.map((val, i) => (
                <input
                  key={i}
                  ref={(el) => { otpRefs.current[i] = el; }}
                  type="text"
                  inputMode="numeric"
                  maxLength={1}
                  value={val}
                  onChange={(e) => handleOtpChange(i, e.target.value)}
                  onKeyDown={(e) => handleOtpKeyDown(i, e)}
                  onPaste={i === 0 ? handleOtpPaste : undefined}
                  className={`h-14 w-12 rounded-xl border-2 text-center text-2xl font-bold outline-none transition-all
                    ${otpError
                      ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200'
                      : 'border-slate-200 bg-white text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white'
                    }`}
                  autoFocus={i === 0}
                />
              ))}
            </div>

            {otpError && (
              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="mb-4 text-sm font-medium text-red-600 dark:text-red-400"
              >
                {otpError}
              </motion.p>
            )}

            <Button
              type="button"
              variant="primary"
              size="lg"
              fullWidth
              loading={isVerifying}
              disabled={isVerifying || otpValues.join('').length !== 6}
              onClick={handleVerify}
            >
              {isVerifying ? c.verifyingLabel : c.verifyLabel}
            </Button>

            <p className="mt-4 text-xs text-slate-400 dark:text-slate-500">
              {c.codeValidity}
            </p>

            {trialToken && (
              <button
                type="button"
                onClick={startTracking}
                className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 transition hover:text-emerald-700 dark:text-emerald-400"
              >
                <Rocket className="h-4 w-4" />
                {c.trackStatus}
              </button>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2b: Pending (cold-start fallback)   */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'pending' && (
          <motion.div
            key="step-pending"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/40">
              <Clock3 className="h-8 w-8 text-amber-600 dark:text-amber-400" />
            </div>

            <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
              {c.pendingTitle}
            </h2>
            <p className="mb-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
              {pendingMessage}
            </p>
            <p className="mb-6 text-sm font-bold text-emerald-600 dark:text-emerald-400">
              {pendingEmail}
            </p>

            <div className="rounded-xl bg-transparent px-4 py-3 text-left text-xs leading-5 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
              Notre systeme de creation d&apos;espace instantane est momentanement
              indisponible (redemarrage serveur). Votre demande est bien
              enregistree : une personne de l&apos;equipe Leopardo vous contactera
              par email sous 24h ouvrables avec un acces adapte a votre
              contexte.
            </div>

            <p className="mt-4 text-center text-sm text-slate-600 dark:text-slate-400">
              Vous avez deja un compte?{' '}
              <Link href="/auth/login" className="font-semibold text-emerald-600 hover:text-emerald-700">
                Se connecter
              </Link>
            </p>

            {trialToken && (
              <button
                type="button"
                onClick={startTracking}
                className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 transition hover:text-emerald-700 dark:text-emerald-400"
              >
                <Rocket className="h-4 w-4" />
                Suivre l&apos;etat de mon espace
              </button>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 2c: Tracking (guided trial status) */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'tracking' && (
          <motion.div
            key="step-tracking"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
            className="text-center"
          >
            {trialStatus === 'ready' ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
                  <CheckCircle className="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.readyTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.readySubtitle}
                </p>
                {trialLoginUrl ? (
                  <div className="space-y-3">
                    <a
                      href={trialLoginUrl}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700"
                    >
                      <LogIn className="h-4 w-4" />
                      {c.accessCta}
                    </a>
                    <button
                      type="button"
                      onClick={() => {
                        void navigator.clipboard
                          ?.writeText(trialLoginUrl)
                          .then(() => setCopied(true))
                          .catch(() => undefined);
                        setTimeout(() => setCopied(false), 2000);
                      }}
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400"
                    >
                      <ClipboardCopy className="h-3.5 w-3.5" />
                      {copied ? c.linkCopied : c.copyLink}
                    </button>
                  </div>
                ) : (
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    {c.linkEmailed}
                  </p>
                )}
              </>
            ) : trialStatus === 'failed' ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/40">
                  <AlertCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.failedTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.failedBody}
                   
                </p>
              </>
            ) : trialTimedOut ? (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/40">
                  <Clock3 className="h-8 w-8 text-amber-600 dark:text-amber-400" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.timeoutTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.timeoutBody}
                   
                </p>
                <Button
                  type="button"
                  variant="secondary"
                  size="lg"
                  fullWidth
                  onClick={() => {
                    setTrialTimedOut(false);
                    setTrialStatus('pending');
                  }}
                >
                  {c.refreshStatus}
                </Button>
              </>
            ) : (
              <>
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
                  <div className="h-8 w-8 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent" />
                </div>
                <h2 className="mb-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                  {c.preparingTitle}
                </h2>
                <p className="mb-6 text-sm leading-6 text-slate-600 dark:text-slate-400">
                  {c.preparingBody}
                   
                </p>
                <p className="text-xs text-slate-400 dark:text-slate-500">
                  {pendingEmail ? `${c.statusFor} ${pendingEmail}` : c.statusEvery5s}
                </p>
              </>
            )}
          </motion.div>
        )}

        {/* ═══════════════════════════════════════ */}
        {/* STEP 3: Success                         */}
        {/* ═══════════════════════════════════════ */}
        {currentStep === 'success' && (
          <motion.div
            key="step-success"
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.4, ease: 'easeOut' }}
          >
            <div className="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/60 dark:border-emerald-800 dark:from-emerald-950/40 dark:via-slate-900 dark:to-emerald-950/20">
              <div className="flex items-center gap-3 bg-emerald-500/10 px-5 py-3 dark:bg-emerald-500/5">
                <Rocket className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                <h3 className="text-lg font-black text-emerald-900 dark:text-emerald-100">
                  Votre espace est pret !
                </h3>
              </div>

              <div className="space-y-4 p-5">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800/60 dark:ring-slate-700">
                  <div className="flex items-center gap-2 text-center justify-center mb-3">
                    <CheckCircle className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                      {c.emailVerified}
                    </p>
                  </div>
                  {provisionedData?.manager ? (
                    <>
                      <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {c.credsLabel}
                      </p>
                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="text-sm text-slate-600 dark:text-slate-300">{c.fieldEmail}</span>
                          <span className="font-mono text-sm font-bold text-slate-900 dark:text-white">
                            {provisionedData.manager.email}
                          </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                          <span className="text-sm text-slate-600 dark:text-slate-300">{c.fieldPassword}</span>
                          <div className="flex items-center gap-2">
                            <span className="rounded-lg bg-slate-100 px-3 py-1 font-mono text-sm font-bold text-slate-900 dark:bg-slate-700 dark:text-white">
                              {provisionedData.manager.temp_password}
                            </span>
                            <button
                              type="button"
                              onClick={() => copyPassword(provisionedData.manager!.temp_password)}
                              className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                              title={c.copyPasswordTitle}
                            >
                              <ClipboardCopy className="h-4 w-4" />
                            </button>
                          </div>
                        </div>
                        {copied && (
                          <p className="text-right text-xs font-medium text-emerald-600">{c.copied}</p>
                        )}
                      </div>
                      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {c.credsSentByEmail} {provisionedData.manager.email}.
                      </p>
                    </>
                  ) : (
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      {c.credsEmailed}
                    </p>
                  )}
                </div>

                {provisionedData?.trial && (
                  <p className="text-center text-sm text-slate-500 dark:text-slate-400">
                    {c.trialNote}{' '}
                    <span className="font-bold text-emerald-600">
                      {provisionedData.trial.days} jours
                    </span>{' '}
                    — {c.trialNoteSuffix}
                  </p>
                )}

                <div className="flex flex-col gap-2 sm:flex-row">
                  <Link
                    href="/auth/login"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700"
                  >
                    <LogIn className="h-4 w-4" />
                    Se connecter
                  </Link>
                  <Link
                    href="/download"
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-transparent dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    <Download className="h-4 w-4" />
                    {c.downloadApp}
                  </Link>
                </div>

                <p className="text-center text-xs text-slate-400 dark:text-slate-500">
                  {c.changePasswordNote}
                </p>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </Card>
  );
}

