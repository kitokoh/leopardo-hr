'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { motion } from 'framer-motion';
import { Calendar, Building2, Users, CheckCircle } from 'lucide-react';

const employeeOptions = ['1-10', '11-50', '51-200', '201-500', '500+'] as const;

interface DemoFormData {
  name: string;
  email: string;
  company: string;
  phone: string;
  employees: (typeof employeeOptions)[number] | '';
  preferredDate: string;
  message: string;
}

type DemoCopy = {
  hero: {
    headline: string;
    subheadline: string;
    cta: string;
    badge: string;
  };
  benefitsTitle: string;
  benefits: Array<{ title: string; desc: string }>;
  formTitle: string;
  successTitle: string;
  successMessage: string;
  submitError: string;
  fields: {
    name: string;
    email: string;
    company: string;
    phone: string;
    employees: string;
    employeesPlaceholder: string;
    employeeSuffix: string;
    preferredDate: string;
    message: string;
    messagePlaceholder: string;
  };
  placeholders: {
    name: string;
    email: string;
    company: string;
    phone: string;
  };
  submit: string;
  submitting: string;
};

const demoCopy: Record<AppLocale, DemoCopy> = {
  fr: {
    hero: {
      headline: 'Demandez une demo Leopardo RH',
      subheadline: 'Voyez comment la plateforme connecte RH, paie, pointage, mobile et admin dans un seul socle.',
      cta: 'Remplir le formulaire',
      badge: 'Demo gratuite',
    },
    benefitsTitle: 'Ce que vous decouvrirez',
    benefits: [
      { title: 'Gestion complete des employes', desc: 'Pointage, absences, contrats et documents dans une experience unifiee.' },
      { title: 'Paie multi-pays automatisee', desc: 'Modeles DZ, MA, TN, FR et TR avec cotisations, IR et bulletins PDF.' },
      { title: 'Dashboard temps reel', desc: 'KPIs, alertes et donnees operationnelles pour les RH et managers.' },
      { title: 'Securite enterprise', desc: 'Isolation tenant, roles, audit trail, chiffrement et workflows controles.' },
    ],
    formTitle: 'Planifiez votre demo',
    successTitle: 'Demande envoyee',
    successMessage: 'Notre equipe vous contactera sous 24h pour organiser une demo adaptee a votre contexte.',
    submitError: 'Erreur lors de la soumission',
    fields: {
      name: 'Nom complet *',
      email: 'Email professionnel *',
      company: 'Entreprise *',
      phone: 'Telephone',
      employees: 'Nombre d employes',
      employeesPlaceholder: 'Selectionnez',
      employeeSuffix: 'employes',
      preferredDate: 'Date preferee',
      message: 'Message (optionnel)',
      messagePlaceholder: 'Decrivez vos besoins...',
    },
    placeholders: {
      name: 'Votre nom',
      email: 'vous@entreprise.com',
      company: 'Nom de votre entreprise',
      phone: '+213 5XX XXX XXX',
    },
    submit: 'Demander une demo',
    submitting: 'Envoi en cours...',
  },
  en: {
    hero: {
      headline: 'Request a Leopardo RH demo',
      subheadline: 'See how HR, payroll, attendance, mobile and platform admin work together in one foundation.',
      cta: 'Fill the form',
      badge: 'Free demo',
    },
    benefitsTitle: 'What you will discover',
    benefits: [
      { title: 'Complete employee management', desc: 'Attendance, leave, contracts and documents in one unified experience.' },
      { title: 'Automated multi-country payroll', desc: 'DZ, MA, TN, FR and TR models with contributions, income tax and PDF pay slips.' },
      { title: 'Real-time dashboard', desc: 'KPIs, alerts and operational data for HR teams and managers.' },
      { title: 'Enterprise security', desc: 'Tenant isolation, roles, audit trail, encryption and controlled workflows.' },
    ],
    formTitle: 'Schedule your demo',
    successTitle: 'Request sent',
    successMessage: 'Our team will contact you within 24 hours to plan a demo tailored to your context.',
    submitError: 'Unable to submit the request',
    fields: {
      name: 'Full name *',
      email: 'Work email *',
      company: 'Company *',
      phone: 'Phone',
      employees: 'Number of employees',
      employeesPlaceholder: 'Select',
      employeeSuffix: 'employees',
      preferredDate: 'Preferred date',
      message: 'Message (optional)',
      messagePlaceholder: 'Describe your needs...',
    },
    placeholders: {
      name: 'Your name',
      email: 'you@company.com',
      company: 'Your company name',
      phone: '+1 555 0100',
    },
    submit: 'Request a demo',
    submitting: 'Sending...',
  },
  tr: {
    hero: {
      headline: 'Leopardo RH demosu talep edin',
      subheadline: 'IK, bordro, devam takibi, mobil ve platform admin alaninin tek bir zeminde nasil calistigini gorun.',
      cta: 'Formu doldur',
      badge: 'Ucretsiz demo',
    },
    benefitsTitle: 'Neleri goreceksiniz',
    benefits: [
      { title: 'Tam calisan yonetimi', desc: 'Devam takibi, izinler, sozlesmeler ve belgeler tek deneyimde.' },
      { title: 'Cok ulkeli otomatik bordro', desc: 'DZ, MA, TN, FR ve TR modelleri; kesintiler, gelir vergisi ve PDF bordro.' },
      { title: 'Gercek zamanli panel', desc: 'IK ekipleri ve yoneticiler icin KPI, uyari ve operasyon verileri.' },
      { title: 'Kurumsal guvenlik', desc: 'Tenant izolasyonu, roller, denetim kaydi, sifreleme ve kontrollu is akislar.' },
    ],
    formTitle: 'Demonuzu planlayin',
    successTitle: 'Talep gonderildi',
    successMessage: 'Ekibimiz 24 saat icinde sizinle iletisime gecerek uygun demoyu planlayacak.',
    submitError: 'Talep gonderilemedi',
    fields: {
      name: 'Ad soyad *',
      email: 'Is e-postasi *',
      company: 'Sirket *',
      phone: 'Telefon',
      employees: 'Calisan sayisi',
      employeesPlaceholder: 'Secin',
      employeeSuffix: 'calisan',
      preferredDate: 'Tercih edilen tarih',
      message: 'Mesaj (opsiyonel)',
      messagePlaceholder: 'Ihtiyaclarinizi yazin...',
    },
    placeholders: {
      name: 'Adiniz',
      email: 'siz@sirket.com',
      company: 'Sirket adiniz',
      phone: '+90 5XX XXX XX XX',
    },
    submit: 'Demo talep et',
    submitting: 'Gonderiliyor...',
  },
  ar: {
    hero: {
      headline: 'اطلب عرضا توضيحيا لمنصة Leopardo RH',
      subheadline: 'شاهد كيف تعمل الموارد البشرية والرواتب والحضور والتطبيق والإدارة في منصة واحدة.',
      cta: 'املأ النموذج',
      badge: 'عرض مجاني',
    },
    benefitsTitle: 'ما الذي ستكتشفه',
    benefits: [
      { title: 'إدارة كاملة للموظفين', desc: 'الحضور، الإجازات، العقود والمستندات في تجربة موحدة.' },
      { title: 'رواتب آلية متعددة الدول', desc: 'نماذج DZ و MA و TN و FR و TR مع الاشتراكات والضريبة وقسائم PDF.' },
      { title: 'لوحة بيانات فورية', desc: 'مؤشرات وتنبيهات وبيانات تشغيلية لفرق الموارد البشرية والمديرين.' },
      { title: 'أمان مؤسسي', desc: 'عزل الشركات، الأدوار، سجل التدقيق، التشفير ومسارات عمل مضبوطة.' },
    ],
    formTitle: 'خطط العرض التوضيحي',
    successTitle: 'تم إرسال الطلب',
    successMessage: 'سيتواصل معك فريقنا خلال 24 ساعة لتنظيم عرض مناسب لسياقك.',
    submitError: 'تعذر إرسال الطلب',
    fields: {
      name: 'الاسم الكامل *',
      email: 'البريد المهني *',
      company: 'الشركة *',
      phone: 'الهاتف',
      employees: 'عدد الموظفين',
      employeesPlaceholder: 'اختر',
      employeeSuffix: 'موظف',
      preferredDate: 'التاريخ المفضل',
      message: 'رسالة (اختياري)',
      messagePlaceholder: 'صف احتياجاتك...',
    },
    placeholders: {
      name: 'اسمك',
      email: 'you@company.com',
      company: 'اسم شركتك',
      phone: '+213 5XX XXX XXX',
    },
    submit: 'طلب عرض توضيحي',
    submitting: 'جار الإرسال...',
  },
};

const benefitIcons = [
  <Users key="users" className="w-6 h-6" />,
  <Building2 key="building" className="w-6 h-6" />,
  <Calendar key="calendar" className="w-6 h-6" />,
  <CheckCircle key="check" className="w-6 h-6" />,
];

export default function DemoPage() {
  const [isDark, setIsDark] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { locale, direction } = useVitrineLocale();
  const copy = demoCopy[locale] ?? demoCopy.fr;
  useScrollReveal();

  const [formData, setFormData] = useState<DemoFormData>({
    name: '',
    email: '',
    company: '',
    phone: '',
    employees: '',
    preferredDate: '',
    message: '',
  });

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const res = await fetch('/api/forms/demo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...formData,
          locale,
          page: '/demo',
          timestamp: new Date().toISOString(),
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || copy.submitError);
      }

      setIsSubmitted(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : copy.submitError);
    } finally {
      setIsSubmitting(false);
    }
  };

  const inputClass =
    'w-full px-4 py-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all';

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#demo-form' }}
        badge={{
          text: copy.hero.badge,
          icon: <Calendar className="w-3 h-3" />,
        }}
      />

      <section id="demo-form" className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16">
            <motion.div
              initial={{ opacity: 0, x: direction === 'rtl' ? 20 : -20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6 }}
            >
              <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-8 tracking-tight">
                {copy.benefitsTitle}
              </h2>

              <div className="space-y-6">
                {copy.benefits.map((item, i) => (
                  <motion.div
                    key={item.title}
                    initial={{ opacity: 0, y: 10 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.4, delay: i * 0.1 }}
                    className="flex gap-4"
                  >
                    <div className="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                      {benefitIcons[i]}
                    </div>
                    <div>
                      <h3 className="font-bold text-slate-900 dark:text-white mb-1">
                        {item.title}
                      </h3>
                      <p className="text-slate-600 dark:text-slate-400 text-sm">
                        {item.desc}
                      </p>
                    </div>
                  </motion.div>
                ))}
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, x: direction === 'rtl' ? -20 : 20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6 }}
            >
              {isSubmitted ? (
                <div className="p-8 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-center">
                  <CheckCircle className="w-16 h-16 text-emerald-600 dark:text-emerald-400 mx-auto mb-4" />
                  <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
                    {copy.successTitle}
                  </h3>
                  <p className="text-slate-600 dark:text-slate-400">
                    {copy.successMessage}
                  </p>
                </div>
              ) : (
                <form
                  onSubmit={handleSubmit}
                  className="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800"
                >
                  <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-6">
                    {copy.formTitle}
                  </h3>

                  {error && (
                    <div className="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
                      {error}
                    </div>
                  )}

                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.name}
                      </label>
                      <input
                        type="text"
                        name="name"
                        required
                        value={formData.name}
                        onChange={handleChange}
                        placeholder={copy.placeholders.name}
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.email}
                      </label>
                      <input
                        type="email"
                        name="email"
                        required
                        value={formData.email}
                        onChange={handleChange}
                        placeholder={copy.placeholders.email}
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.company}
                      </label>
                      <input
                        type="text"
                        name="company"
                        required
                        value={formData.company}
                        onChange={handleChange}
                        placeholder={copy.placeholders.company}
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.phone}
                      </label>
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        placeholder={copy.placeholders.phone}
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.employees}
                      </label>
                      <select
                        name="employees"
                        value={formData.employees}
                        onChange={handleChange}
                        className={inputClass}
                      >
                        <option value="">{copy.fields.employeesPlaceholder}</option>
                        {employeeOptions.map((opt) => (
                          <option key={opt} value={opt}>
                            {opt} {copy.fields.employeeSuffix}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.preferredDate}
                      </label>
                      <input
                        type="date"
                        name="preferredDate"
                        value={formData.preferredDate}
                        onChange={handleChange}
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {copy.fields.message}
                      </label>
                      <textarea
                        name="message"
                        value={formData.message}
                        onChange={handleChange}
                        rows={3}
                        placeholder={copy.fields.messagePlaceholder}
                        className={inputClass}
                      />
                    </div>

                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="w-full py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-400 text-white font-bold transition-colors"
                    >
                      {isSubmitting ? copy.submitting : copy.submit}
                    </button>
                  </div>
                </form>
              )}
            </motion.div>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
