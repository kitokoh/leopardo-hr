'use client';

/**
 * #8440 — page vitrine « Devenir actionnaire » (/investir).
 *
 * Positionnement (décidé par le propriétaire) : permettre à un visiteur de
 * MANIFESTER SON INTÉRÊT pour prendre des parts de l'entreprise. Ce n'est
 * **pas** une offre publique de titres : la manifestation est non
 * contraignante, le suivi (échange, term-sheet, closing) se fait en direct,
 * hors plateforme. La page le dit explicitement — conformité et honnêteté.
 *
 * Entrée volontairement discrète : lien en footer (colonne « Légal »),
 * aucune carte home ni bandeau. La capture passe par `/api/forms/equity`
 * (canal leads `equity_interest`) et se suit dans l'admin plateforme.
 */

import { Suspense, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, HeroSection, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import {
  AlertCircle,
  CheckCircle,
  Handshake,
  LineChart,
  Scale,
  Send,
  ShieldCheck,
  TrendingUp,
  Users,
} from 'lucide-react';
import { FormDataNotice } from '@/modules/vitrine/components/FormDataNotice';
import { antispamFields } from '@/modules/vitrine/lib/antispam-client';
import { HoneypotField } from '@/modules/vitrine/components/common/HoneypotField';
import { Input, Textarea, Button } from '@/modules/vitrine/components/common';
import { EQUITY_AMOUNT_BRACKETS, equityFormSchema, parseZodErrors } from '@/modules/vitrine/lib/validation';

type AmountBracket = (typeof EQUITY_AMOUNT_BRACKETS)[number];

type InvestCopy = {
  hero: { badge: string; headline: string; subheadline: string; cta: string };
  notice: string;
  pillars: { title: string; items: { title: string; body: string }[] };
  steps: { title: string; items: { title: string; body: string }[] };
  form: {
    title: string;
    name: string;
    email: string;
    phone: string;
    country: string;
    countryPlaceholder: string;
    bracket: string;
    bracketPlaceholder: string;
    brackets: Record<AmountBracket, string>;
    message: string;
    consent: string;
    send: string;
    sending: string;
    successTitle: string;
    successBody: string;
    errorGeneric: string;
  };
};

const COUNTRIES = ['FR', 'DZ', 'MA', 'TN', 'SN', 'CI', 'CM', 'BF', 'ML', 'TR', 'AE', 'CA', 'BE', 'CH', 'DE', 'GB', 'US'] as const;

const investCopy: Record<string, InvestCopy> = {
  fr: {
    hero: {
      badge: 'Devenir actionnaire',
      headline: 'Prenez part à la construction de Leopardo',
      subheadline:
        'Nous ouvrons une partie de notre capital à celles et ceux qui croient au projet. Manifestez votre intérêt en quelques minutes — démarche simple et sans engagement.',
      cta: 'Manifester mon intérêt',
    },
    notice:
      'Cette page recueille des manifestations d’intérêt non contraignantes. Elle ne constitue ni une offre publique de titres, ni un engagement d’investissement : chaque projet de participation fait l’objet d’un échange direct et de documents juridiques dédiés.',
    pillars: {
      title: 'Pourquoi cette ouverture',
      items: [
        {
          title: 'Un produit qui grandit',
          body: 'Leopardo équipe déjà des équipes terrain, des restaurants, des stations-service et des cagnottes solidaires. La trajectoire est réelle et mesurable.',
        },
        {
          title: 'Une démarche encadrée',
          body: 'Votre manifestation d’intérêt n’engage à rien. Elle ouvre un dialogue direct avec la direction, au cours duquel les termes exacts sont présentés.',
        },
        {
          title: 'Un cap clair',
          body: 'Les fonds servent à accélérer le produit et la distribution, avec une information régulière des personnes actionnaires.',
        },
      ],
    },
    steps: {
      title: 'Comment ça se passe',
      items: [
        {
          title: '1. Vous manifestez votre intérêt',
          body: 'Le formulaire ci-dessous suffit : identité, tranche indicative, message libre. Aucun paiement à ce stade.',
        },
        {
          title: '2. Nous échangeons',
          body: 'La direction vous recontacte personnellement pour présenter la société, répondre à vos questions et détailler les termes.',
        },
        {
          title: '3. Vous décidez',
          body: 'La participation se conclut uniquement après documents juridiques complets — ou pas du tout. Votre décision est libre.',
        },
      ],
    },
    form: {
      title: 'Manifester mon intérêt',
      name: 'Nom complet',
      email: 'Email',
      phone: 'Téléphone (optionnel)',
      country: 'Pays (optionnel)',
      countryPlaceholder: 'Choisir un pays',
      bracket: 'Tranche d’investissement envisagée',
      bracketPlaceholder: 'Choisir une tranche',
      brackets: {
        lt_1k: 'Moins de 1 000 €',
        '1k_10k': '1 000 € – 10 000 €',
        '10k_50k': '10 000 € – 50 000 €',
        gt_50k: 'Plus de 50 000 €',
        discuss: 'À discuter',
      },
      message: 'Message (optionnel)',
      consent: 'J’accepte d’être recontacté(e) au sujet d’une prise de participation dans Leopardo.',
      send: 'Envoyer ma manifestation d’intérêt',
      sending: 'Envoi en cours…',
      successTitle: 'Manifestation d’intérêt enregistrée !',
      successBody: 'Merci pour votre confiance. La direction vous recontactera personnellement.',
      errorGeneric: 'Une erreur est survenue. Veuillez réessayer.',
    },
  },
  en: {
    hero: {
      badge: 'Become a shareholder',
      headline: 'Take part in building Leopardo',
      subheadline:
        'We are opening part of our capital to people who believe in the project. Express your interest in a few minutes — a simple, non-binding step.',
      cta: 'Express my interest',
    },
    notice:
      'This page collects non-binding expressions of interest. It is neither a public offering of securities nor an investment commitment: every participation project is handled through a direct conversation and dedicated legal documents.',
    pillars: {
      title: 'Why we are opening up',
      items: [
        {
          title: 'A growing product',
          body: 'Leopardo already powers field teams, restaurants, fuel stations and solidarity fundraisers. The trajectory is real and measurable.',
        },
        {
          title: 'A supervised process',
          body: 'Your expression of interest commits you to nothing. It opens a direct dialogue with the founders, during which the exact terms are presented.',
        },
        {
          title: 'A clear course',
          body: 'Funds are used to accelerate the product and distribution, with regular information for shareholders.',
        },
      ],
    },
    steps: {
      title: 'How it works',
      items: [
        {
          title: '1. You express your interest',
          body: 'The form below is enough: identity, indicative bracket, free message. No payment at this stage.',
        },
        {
          title: '2. We talk',
          body: 'The founders personally get back to you to present the company, answer your questions and detail the terms.',
        },
        {
          title: '3. You decide',
          body: 'A participation is concluded only after complete legal documents — or not at all. The decision is yours.',
        },
      ],
    },
    form: {
      title: 'Express my interest',
      name: 'Full name',
      email: 'Email',
      phone: 'Phone (optional)',
      country: 'Country (optional)',
      countryPlaceholder: 'Choose a country',
      bracket: 'Indicative investment bracket',
      bracketPlaceholder: 'Choose a bracket',
      brackets: {
        lt_1k: 'Under €1,000',
        '1k_10k': '€1,000 – €10,000',
        '10k_50k': '€10,000 – €50,000',
        gt_50k: 'Over €50,000',
        discuss: 'To be discussed',
      },
      message: 'Message (optional)',
      consent: 'I agree to be contacted about taking an equity stake in Leopardo.',
      send: 'Send my expression of interest',
      sending: 'Sending…',
      successTitle: 'Expression of interest recorded!',
      successBody: 'Thank you for your trust. The founders will contact you personally.',
      errorGeneric: 'Something went wrong. Please try again.',
    },
  },
  tr: {
    hero: {
      badge: 'Ortak olun',
      headline: 'Leopardo’nun inşasında yer alın',
      subheadline:
        'Sermayemizin bir bölümünü projeye inananlara açıyoruz. Birkaç dakikada ilginizi bildirin — basit ve bağlayıcı olmayan bir adım.',
      cta: 'İlgimi bildir',
    },
    notice:
      'Bu sayfa bağlayıcı olmayan ilgi beyanları toplar. Halka açık bir menkul kıymet teklifi veya yatırım taahhüdü değildir: her katılım projesi doğrudan görüşme ve özel hukuki belgelerle yürütülür.',
    pillars: {
      title: 'Neden açılıyoruz',
      items: [
        {
          title: 'Büyüyen bir ürün',
          body: 'Leopardo halihazırda saha ekiplerine, restoranlara, akaryakıt istasyonlarına ve dayanışma fonlarına hizmet veriyor. Gidişat gerçek ve ölçülebilir.',
        },
        {
          title: 'Denetimli bir süreç',
          body: 'İlgi beyanınız sizi hiçbir şeye bağlamaz. Kesin şartların sunulacağı yönetimle doğrudan bir diyalog açar.',
        },
        {
          title: 'Net bir rota',
          body: 'Fonlar, ortaklara düzenli bilgilendirme ile ürünü ve dağıtımı hızlandırmak için kullanılır.',
        },
      ],
    },
    steps: {
      title: 'Nasıl işliyor',
      items: [
        {
          title: '1. İlginizi bildirirsiniz',
          body: 'Aşağıdaki form yeterli: kimlik, gösterge niteliğinde dilim, serbest mesaj. Bu aşamada ödeme yok.',
        },
        {
          title: '2. Görüşürüz',
          body: 'Yönetim, şirketi tanıtmak, sorularınızı yanıtlamak ve şartları ayrıntılandırmak için size bizzat geri döner.',
        },
        {
          title: '3. Karar verirsiniz',
          body: 'Katılım ancak eksiksiz hukuki belgelerden sonra sonuçlanır — ya da hiç sonuçlanmaz. Karar sizindir.',
        },
      ],
    },
    form: {
      title: 'İlgimi bildir',
      name: 'Ad soyad',
      email: 'E-posta',
      phone: 'Telefon (isteğe bağlı)',
      country: 'Ülke (isteğe bağlı)',
      countryPlaceholder: 'Ülke seçin',
      bracket: 'Öngörülen yatırım dilimi',
      bracketPlaceholder: 'Dilim seçin',
      brackets: {
        lt_1k: '1.000 € altı',
        '1k_10k': '1.000 € – 10.000 €',
        '10k_50k': '10.000 € – 50.000 €',
        gt_50k: '50.000 € üzeri',
        discuss: 'Görüşülecek',
      },
      message: 'Mesaj (isteğe bağlı)',
      consent: 'Leopardo’ya ortak olma konusunda benimle iletişime geçilmesini kabul ediyorum.',
      send: 'İlgi beyanımı gönder',
      sending: 'Gönderiliyor…',
      successTitle: 'İlgi beyanınız kaydedildi!',
      successBody: 'Güveniniz için teşekkürler. Yönetim size bizzat geri dönecek.',
      errorGeneric: 'Bir şeyler ters gitti. Lütfen tekrar deneyin.',
    },
  },
  ar: {
    hero: {
      badge: 'كن مساهماً',
      headline: 'شارك في بناء ليوباردو',
      subheadline:
        'نفتح جزءاً من رأس مالنا لمن يؤمنون بالمشروع. عبّر عن اهتمامك في دقائق — خطوة بسيطة وغير ملزمة.',
      cta: 'أعبّر عن اهتمامي',
    },
    notice:
      'تجمع هذه الصفحة تعبيرات اهتمام غير ملزمة. ليست عرضاً عاماً للأوراق المالية ولا التزاماً بالاستثمار: كل مشروع مشاركة يتم عبر حوار مباشر ووثائق قانونية مخصصة.',
    pillars: {
      title: 'لماذا هذا الانفتاح',
      items: [
        {
          title: 'منتج ينمو',
          body: 'تخدم ليوباردو اليوم فرق الميدان والمطاعم ومحطات الوقود وحملات التضامن. المسار حقيقي وقابل للقياس.',
        },
        {
          title: 'مسار منضبط',
          body: 'تعبيرك عن الاهتمام لا يلزمك بشيء. إنه يفتح حواراً مباشراً مع الإدارة تُعرض فيه الشروط الدقيقة.',
        },
        {
          title: 'وجهة واضحة',
          body: 'تُستخدم الأموال لتسريع المنتج والتوزيع، مع اطلاع منتظم للمساهمين.',
        },
      ],
    },
    steps: {
      title: 'كيف تتم العملية',
      items: [
        {
          title: '1. تعبّر عن اهتمامك',
          body: 'يكفي النموذج أدناه: الهوية، شريحة استثمار إرشادية، رسالة حرة. لا دفع في هذه المرحلة.',
        },
        {
          title: '2. نتحاور',
          body: 'تتواصل معك الإدارة شخصياً لتقديم الشركة والإجابة عن أسئلتك وتفصيل الشروط.',
        },
        {
          title: '3. تقرر',
          body: 'لا تُبرم المشاركة إلا بعد وثائق قانونية كاملة — أو لا تُبرم أصلاً. القرار لك.',
        },
      ],
    },
    form: {
      title: 'أعبّر عن اهتمامي',
      name: 'الاسم الكامل',
      email: 'البريد الإلكتروني',
      phone: 'الهاتف (اختياري)',
      country: 'البلد (اختياري)',
      countryPlaceholder: 'اختر بلداً',
      bracket: 'شريحة الاستثمار المتوقعة',
      bracketPlaceholder: 'اختر شريحة',
      brackets: {
        lt_1k: 'أقل من 1,000 €',
        '1k_10k': '1,000 € – 10,000 €',
        '10k_50k': '10,000 € – 50,000 €',
        gt_50k: 'أكثر من 50,000 €',
        discuss: 'للنقاش',
      },
      message: 'رسالة (اختياري)',
      consent: 'أوافق على أن يتم التواصل معي بشأن المساهمة في رأس مال ليوباردو.',
      send: 'أرسل تعبير اهتمامي',
      sending: 'جارٍ الإرسال…',
      successTitle: 'تم تسجيل تعبير اهتمامك!',
      successBody: 'شكراً لثقتك. ستتواصل معك الإدارة شخصياً.',
      errorGeneric: 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
    },
  },
};

const PILLAR_ICONS = [TrendingUp, Scale, LineChart] as const;
const STEP_ICONS = [Send, Users, ShieldCheck] as const;

function InvestirPageInner() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale } = useVitrineLocale();
  const copy = investCopy[locale] ?? investCopy.fr;
  useScrollReveal();

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    country: '',
    amountBracket: '' as '' | AmountBracket,
    message: '',
    consent: false,
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const target = e.target;
    const value = target instanceof HTMLInputElement && target.type === 'checkbox' ? target.checked : target.value;
    setForm(prev => ({ ...prev, [target.name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    const validation = equityFormSchema(locale).safeParse(form);
    if (!validation.success) {
      const errors: Record<string, string> = {};
      for (const { field, message } of parseZodErrors(validation.error)) {
        if (!errors[field]) errors[field] = message;
      }
      setFieldErrors(errors);
      return;
    }
    setFieldErrors({});
    setIsSubmitting(true);

    try {
      const res = await fetch('/api/forms/equity', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...antispamFields(),
          name: form.name,
          email: form.email,
          phone: form.phone || undefined,
          country: form.country || undefined,
          amountBracket: form.amountBracket,
          message: form.message || undefined,
          consent: form.consent,
          locale,
          page: '/investir',
          source: 'equity_form',
          timestamp: new Date().toISOString(),
        }),
      });
      if (!res.ok) throw new Error('send failed');
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
        ctaPrimary={{ text: copy.hero.cta, href: '#investir-form' }}
        badge={{ text: copy.hero.badge, icon: <Handshake className="w-3 h-3" /> }}
      />

      {/* Mention de cadrage — dite clairement, dès le haut de page. */}
      <section className="pt-4 pb-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <p className="text-sm leading-relaxed text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-4 bg-slate-50 dark:bg-slate-900/40">
            {copy.notice}
          </p>
        </div>
      </section>

      {/* Pourquoi cette ouverture */}
      <section className="py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-8 text-center">{copy.pillars.title}</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {copy.pillars.items.map((item, index) => {
              const Icon = PILLAR_ICONS[index] ?? TrendingUp;
              return (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 16 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.08 }}
                  className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700"
                >
                  <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-4">
                    <Icon className="w-5 h-5 text-emerald-700 dark:text-emerald-400" />
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2">{item.title}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.body}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Étapes */}
      <section className="py-12 bg-slate-50 dark:bg-slate-900/30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-black text-slate-900 dark:text-white mb-8 text-center">{copy.steps.title}</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {copy.steps.items.map((item, index) => {
              const Icon = STEP_ICONS[index] ?? Send;
              return (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 16 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.08 }}
                  className="rounded-2xl p-6 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                >
                  <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-4">
                    <Icon className="w-5 h-5 text-emerald-700 dark:text-emerald-400" />
                  </div>
                  <h3 className="font-bold text-slate-900 dark:text-white mb-2">{item.title}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.body}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Formulaire */}
      <section className="py-16">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8" id="investir-form">
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
              <>
                <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-6">{copy.form.title}</h2>
                <form onSubmit={handleSubmit} className="space-y-6">
                  <HoneypotField />
                  <div className="grid sm:grid-cols-2 gap-6">
                    <Input
                      id="name" name="name" required value={form.name} onChange={handleChange}
                      label={copy.form.name} error={fieldErrors.name}
                    />
                    <Input
                      id="email" name="email" type="email" required value={form.email} onChange={handleChange}
                      label={copy.form.email} error={fieldErrors.email}
                    />
                  </div>

                  <div className="grid sm:grid-cols-2 gap-6">
                    <Input
                      id="phone" name="phone" value={form.phone} onChange={handleChange}
                      label={copy.form.phone} error={fieldErrors.phone}
                    />
                    <div className="w-full">
                      <label htmlFor="country" className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        {copy.form.country}
                      </label>
                      <select
                        id="country" name="country" value={form.country} onChange={handleChange}
                        className="w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 dark:text-white"
                      >
                        <option value="">{copy.form.countryPlaceholder}</option>
                        {COUNTRIES.map(code => (
                          <option key={code} value={code}>{code}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="w-full">
                    <label htmlFor="amountBracket" className="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                      {copy.form.bracket} <span className="text-red-500 ml-1">*</span>
                    </label>
                    <select
                      id="amountBracket" name="amountBracket" required value={form.amountBracket} onChange={handleChange}
                      aria-invalid={fieldErrors.amountBracket ? true : undefined}
                      className={`w-full px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 dark:text-white ${fieldErrors.amountBracket ? 'border-red-500 focus:ring-red-500' : 'border-slate-300 dark:border-slate-700'}`}
                    >
                      <option value="">{copy.form.bracketPlaceholder}</option>
                      {EQUITY_AMOUNT_BRACKETS.map(bracket => (
                        <option key={bracket} value={bracket}>{copy.form.brackets[bracket]}</option>
                      ))}
                    </select>
                    {fieldErrors.amountBracket && (
                      <p role="alert" className="flex items-center gap-1.5 mt-2 text-sm text-red-600 dark:text-red-400">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />{fieldErrors.amountBracket}
                      </p>
                    )}
                  </div>

                  <Textarea
                    id="message" name="message" rows={4} value={form.message} onChange={handleChange}
                    label={copy.form.message} error={fieldErrors.message} className="resize-none"
                  />

                  <div>
                    <label className="flex items-start gap-3 cursor-pointer">
                      <input
                        type="checkbox" name="consent" checked={form.consent} onChange={handleChange}
                        className="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                      />
                      <span className="text-sm text-slate-600 dark:text-slate-300">{copy.form.consent}</span>
                    </label>
                    {fieldErrors.consent && (
                      <p role="alert" className="flex items-center gap-1.5 mt-2 text-sm text-red-600 dark:text-red-400">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />{fieldErrors.consent}
                      </p>
                    )}
                  </div>

                  {error && (
                    <div className="flex items-center gap-2 text-red-600 dark:text-red-400 text-sm" role="alert" aria-live="assertive">
                      <AlertCircle className="w-4 h-4" />{error}
                    </div>
                  )}

                  <FormDataNotice purpose="equity" />

                  <Button
                    type="submit"
                    size="lg"
                    loading={isSubmitting}
                    icon={<Send className="w-4 h-4" />}
                    iconPosition="left"
                    className="w-full sm:w-auto"
                  >
                    {isSubmitting ? copy.form.sending : copy.form.send}
                  </Button>
                </form>
              </>
            )}
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}

export default function InvestirPage() {
  return (
    <Suspense fallback={null}>
      <InvestirPageInner />
    </Suspense>
  );
}
