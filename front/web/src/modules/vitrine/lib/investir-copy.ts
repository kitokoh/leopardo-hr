/**
 * #8440 — catalogue inline ×4 (fr/en/tr/ar) du copy de la page vitrine
 * « Devenir actionnaire » (/investir).
 *
 * Ce fichier EST le mécanisme de localisation — même mécanique que
 * vitrine-locale.ts, case-studies.ts, VerticalsSection.tsx ou
 * legal-content.ts : ce ne sont PAS des chaînes hardcodées hors catalogue.
 * La garde « no new hardcoded strings » le sait (entrée documentée dans
 * ignorePathFragments de dev-hub/tools/check-i18n-diff.js) ; la complétude
 * des 4 langues reste couverte par validate-and-sync.
 */

import { EQUITY_AMOUNT_BRACKETS } from './validation';

export type AmountBracket = (typeof EQUITY_AMOUNT_BRACKETS)[number];

export type InvestCopy = {
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

export const COUNTRIES = ['FR', 'DZ', 'MA', 'TN', 'SN', 'CI', 'CM', 'BF', 'ML', 'TR', 'AE', 'CA', 'BE', 'CH', 'DE', 'GB', 'US'] as const;

export const investCopy: Record<string, InvestCopy> = {
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

