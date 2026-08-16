'use client';

import { Suspense, useEffect, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { useSearchParams } from 'next/navigation';
import { Navbar, HeroSection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { Mail, Phone, MapPin, Clock, Send, CheckCircle, AlertCircle } from 'lucide-react';

// #4327 : libellés des sujets localisés ×4 locales (valeurs stables côté
// formulaire = libellé localisé, l'API les traite en texte libre).
const SUBJECT_IDS = [
  'general',
  'demo',
  'support',
  'partnership',
  'press',
  'password',
  'upgrade',
  'download-kiosk',
  'download-windows',
  'download-macos',
  'enterprise',
  'community',
  'other',
] as const;

type SubjectId = (typeof SUBJECT_IDS)[number];

const SUBJECT_LABELS: Record<string, Record<SubjectId, string>> = {
  fr: {
    general: 'Information générale',
    demo: 'Demande de démo',
    support: 'Support technique',
    partnership: 'Partenariat',
    press: 'Presse & Médias',
    password: 'Mot de passe oublié',
    upgrade: 'Mise à niveau (upgrade)',
    'download-kiosk': 'Téléchargement - Kiosque',
    'download-windows': 'Téléchargement - Windows',
    'download-macos': 'Téléchargement - macOS',
    enterprise: 'Enterprise',
    community: 'Communauté',
    other: 'Autre',
  },
  en: {
    general: 'General information',
    demo: 'Request a demo',
    support: 'Technical support',
    partnership: 'Partnership',
    press: 'Press & Media',
    password: 'Forgotten password',
    upgrade: 'Upgrade plan',
    'download-kiosk': 'Download - Kiosk',
    'download-windows': 'Download - Windows',
    'download-macos': 'Download - macOS',
    enterprise: 'Enterprise',
    community: 'Community',
    other: 'Other',
  },
  tr: {
    general: 'Genel bilgi',
    demo: 'Demo isteği',
    support: 'Teknik destek',
    partnership: 'Ortaklık',
    press: 'Basın ve Medya',
    password: 'Unutulan şifre',
    upgrade: 'Plan yükseltme',
    'download-kiosk': 'İndirme - Kiosk',
    'download-windows': 'İndirme - Windows',
    'download-macos': 'İndirme - macOS',
    enterprise: 'Kurumsal',
    community: 'Topluluk',
    other: 'Diğer',
  },
  ar: {
    general: 'معلومات عامة',
    demo: 'طلب عرض توضيحي',
    support: 'الدعم الفني',
    partnership: 'شراكة',
    press: 'الصحافة والإعلام',
    password: 'كلمة المرور المفقودة',
    upgrade: 'ترقية الباقة',
    'download-kiosk': 'تحميل - كشك',
    'download-windows': 'تحميل - ويندوز',
    'download-macos': 'تحميل - ماك',
    enterprise: 'المؤسسات',
    community: 'المجتمع',
    other: 'أخرى',
  },
};

// Maps the `?topic=` query param (used by links across login, dashboard,
// navbar/footer and the download page) to a subject ID.
// See issue #1304: without this mapping the "Sujet" select always fell
// back to its default value regardless of which link was clicked.
const TOPIC_TO_SUBJECT_ID: Record<string, SubjectId> = {
  password: 'password',
  upgrade: 'upgrade',
  support: 'support',
  community: 'community',
  'download-kiosk': 'download-kiosk',
  'download-windows': 'download-windows',
  'download-macos': 'download-macos',
  download: 'general',
  // #3254 : le CTA Enterprise (pricing/home/checkout) doit préremplir un sujet réel
  enterprise: 'enterprise',
};

const contactCopy: Record<string, {
  hero: { headline: string; subheadline: string; cta1: string; cta2: string; badge: string };
  info: { title: string; email: string; phone: string; address: string; addressValue: string; hours: string; hoursValue: string; responseTime: string };
  form: { name: string; email: string; company: string; subject: string; subjectPlaceholder: string; message: string; send: string; sending: string; successTitle: string; successBody: string; errorSend: string; errorGeneric: string };
}> = {
  fr: {
    hero: { headline: 'Contactez-nous', subheadline: 'Notre équipe est là pour répondre à toutes vos questions', cta1: 'Envoyer un message', cta2: 'Demander une démo', badge: 'Contact' },
    info: { title: 'Informations', email: 'Email', phone: 'Téléphone', address: 'Adresse', addressValue: 'Alger, Algérie', hours: 'Horaires', hoursValue: 'Lun-Ven 9h-18h (GMT+1)', responseTime: 'Temps de réponse moyen : moins de 24 h' },

    form: { name: 'Nom complet', email: 'Email', company: 'Entreprise', subject: 'Sujet', subjectPlaceholder: 'Choisir un sujet', message: 'Message', send: 'Envoyer le message', sending: 'Envoi en cours...', successTitle: 'Message envoyé !', successBody: 'Notre équipe vous répondra sous 24h.', errorSend: 'Erreur lors de l\'envoi', errorGeneric: 'Une erreur est survenue. Veuillez réessayer.' },
  },
  en: {
    hero: { headline: 'Contact us', subheadline: 'Our team is here to answer all your questions', cta1: 'Send a message', cta2: 'Request a demo', badge: 'Contact' },
    info: { title: 'Information', email: 'Email', phone: 'Phone', address: 'Address', addressValue: 'Algiers, Algeria', hours: 'Hours', hoursValue: 'Mon-Fri 9am-6pm (GMT+1)', responseTime: 'Average response time: under 24h' },
    form: { name: 'Full name', email: 'Email', company: 'Company', subject: 'Subject', subjectPlaceholder: 'Choose a subject', message: 'Message', send: 'Send message', sending: 'Sending...', successTitle: 'Message sent!', successBody: 'Our team will reply within 24 hours.', errorSend: 'Error while sending', errorGeneric: 'Something went wrong. Please try again.' },
  },
  tr: {
    hero: { headline: 'Bize ulaşın', subheadline: 'Ekibimiz tüm sorularınızı yanıtlamak için burada', cta1: 'Mesaj gönder', cta2: 'Demo iste', badge: 'İletişim' },
    info: { title: 'Bilgiler', email: 'E-posta', phone: 'Telefon', address: 'Adres', addressValue: 'Cezayir, Cezayir', hours: 'Çalışma saatleri', hoursValue: 'Pzt-Cum 9:00-18:00 (GMT+1)', responseTime: 'Ortalama yanıt süresi: 24 saatten az' },
    form: { name: 'Ad soyad', email: 'E-posta', company: 'Şirket', subject: 'Konu', subjectPlaceholder: 'Konu seçin', message: 'Mesaj', send: 'Mesajı gönder', sending: 'Gönderiliyor...', successTitle: 'Mesaj gönderildi!', successBody: 'Ekibimiz 24 saat içinde yanıt verecektir.', errorSend: 'Gönderilirken hata oluştu', errorGeneric: 'Bir şeyler ters gitti. Lütfen tekrar deneyin.' },
  },
  ar: {
    hero: { headline: 'اتصل بنا', subheadline: 'فريقنا هنا للإجابة على جميع أسئلتك', cta1: 'أرسل رسالة', cta2: 'اطلب عرضاً تجريبياً', badge: 'اتصال' },
    info: { title: 'معلومات', email: 'البريد الإلكتروني', phone: 'الهاتف', address: 'العنوان', addressValue: 'الجزائر العاصمة، الجزائر', hours: 'ساعات العمل', hoursValue: 'الإثنين-الجمعة 9:00-18:00 (GMT+1)', responseTime: 'متوسط وقت الاستجابة: أقل من 24 ساعة' },
    form: { name: 'الاسم الكامل', email: 'البريد الإلكتروني', company: 'الشركة', subject: 'الموضوع', subjectPlaceholder: 'اختر موضوعاً', message: 'الرسالة', send: 'إرسال الرسالة', sending: 'جارٍ الإرسال...', successTitle: 'تم إرسال الرسالة!', successBody: 'سيرد فريقنا خلال 24 ساعة.', errorSend: 'خطأ أثناء الإرسال', errorGeneric: 'حدث خطأ ما. يرجى المحاولة مرة أخرى.' },
  },
};

function ContactPageInner() {
  const searchParams = useSearchParams();
  const { isDark, toggleDarkMode } = useDarkMode();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { locale } = useVitrineLocale();
  const copy = contactCopy[locale] ?? contactCopy.fr;
  useScrollReveal();

  const [form, setForm] = useState({
    name: '',
    email: '',
    company: '',
    subject: '',
    message: '',
  });

  // Prefill the "Sujet" select from the `?topic=` query param so contextual
  // links (forgot password, upgrade, support, download, community) land on
  // the right subject instead of the default empty option (issue #1304).
  // #4327 : le libellé prérempli est résolu dans la locale active.
  useEffect(() => {
    const topic = searchParams.get('topic');
    if (!topic) return;
    const mappedId = TOPIC_TO_SUBJECT_ID[topic];
    if (!mappedId) return;
    setForm(prev => ({ ...prev, subject: SUBJECT_LABELS[locale][mappedId] }));
  }, [searchParams, locale]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const res = await fetch('/api/forms/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...form, timestamp: new Date().toISOString() }),
      });
      if (!res.ok) throw new Error(copy.form.errorSend);
      setIsSubmitted(true);
    } catch {
      setError(copy.form.errorGeneric);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta1, href: '#contact-form' }}
        ctaSecondary={{ text: copy.hero.cta2, href: '/demo' }}
        badge={{ text: copy.hero.badge, icon: <Mail className="w-3 h-3" /> }}
      />

      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-3 gap-12">
            {/* Contact Info */}
            <div className="space-y-8">
              <motion.div initial={{ opacity: 0, x: -20 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}>
                <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-6">{copy.info.title}</h2>
                <div className="space-y-6">
                  {[
                    { icon: Mail, label: copy.info.email, value: 'contact@leopardo-rh.com' },
                    { icon: Phone, label: copy.info.phone, value: '+213 (0) 555 123 456' },
                    { icon: MapPin, label: copy.info.address, value: copy.info.addressValue },
                    { icon: Clock, label: copy.info.hours, value: copy.info.hoursValue },
                  ].map((item, i) => (
                    <div key={i} className="flex items-start gap-4">
                      <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                        <item.icon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                      </div>
                      <div>
                        <p className="text-sm text-slate-500 dark:text-slate-400">{item.label}</p>
                        <p className="font-medium text-slate-900 dark:text-white">{item.value}</p>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="mt-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                  <p className="text-sm text-emerald-700 dark:text-emerald-400 font-medium">
                    {copy.info.responseTime}
                  </p>
                </div>
              </motion.div>
            </div>

            {/* Contact Form */}
            <div className="lg:col-span-2" id="contact-form">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-slate-200 dark:border-slate-700"
              >
                {isSubmitted ? (
                  <div className="text-center py-12">
                    <CheckCircle className="w-16 h-16 text-emerald-500 mx-auto mb-4" />
                    <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2">{copy.form.successTitle}</h3>
                    <p className="text-slate-500 dark:text-slate-400">{copy.form.successBody}</p>
                  </div>
                ) : (
                  <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid sm:grid-cols-2 gap-6">
                      <div>
                        <label htmlFor="name" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          {copy.form.name} <span className="text-red-500">*</span>
                        </label>
                        <input
                          id="name" name="name" required value={form.name} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label htmlFor="email" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          {copy.form.email} <span className="text-red-500">*</span>
                        </label>
                        <input
                          id="email" name="email" type="email" required value={form.email} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                    </div>

                    <div className="grid sm:grid-cols-2 gap-6">
                      <div>
                        <label htmlFor="company" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{copy.form.company}</label>
                        <input
                          id="company" name="company" value={form.company} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label htmlFor="subject" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                          {copy.form.subject} <span className="text-red-500">*</span>
                        </label>
                        <select
                          id="subject" name="subject" required value={form.subject} onChange={handleChange}
                          className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        >
                          <option value="">{copy.form.subjectPlaceholder}</option>
                          {SUBJECT_IDS.map(id => (
                            <option key={id} value={SUBJECT_LABELS[locale][id]}>{SUBJECT_LABELS[locale][id]}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div>
                      <label htmlFor="message" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.form.message} <span className="text-red-500">*</span>
                      </label>
                      <textarea
                        id="message" name="message" required rows={5} value={form.message} onChange={handleChange}
                        className="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"
                      />
                    </div>

                    {error && (
                      <div className="flex items-center gap-2 text-red-600 dark:text-red-400 text-sm" role="alert">
                        <AlertCircle className="w-4 h-4" />{error}
                      </div>
                    )}

                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="w-full sm:w-auto px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-colors disabled:opacity-50 flex items-center gap-2"
                    >
                      <Send className="w-4 h-4" />
                      {isSubmitting ? copy.form.sending : copy.form.send}
                    </button>
                  </form>
                )}
              </motion.div>
            </div>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}

export default function ContactPage() {
  return (
    <Suspense fallback={null}>
      <ContactPageInner />
    </Suspense>
  );
}

