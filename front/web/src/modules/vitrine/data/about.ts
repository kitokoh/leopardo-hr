import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /about par locale (issue #2605 — T004).
// Les icônes restent dans la page ; ce fichier ne porte que les chaînes.

export type AboutValue = {
  title: string
  description: string
}

export type AboutTeamMember = {
  name: string
  role: string
  bio: string
  image: string
}

export type AboutStat = {
  value: string
  label: string
}

export type AboutContent = {
  hero: {
    badge: string
    headline: string
    subheadline: string
    ctaPrimary: string
    ctaSecondary: string
  }
  story: {
    badge: string
    title: string
    paragraphs: string[]
  }
  values: {
    badge: string
    title: string
    items: AboutValue[]
  }
  team: {
    badge: string
    title: string
    members: AboutTeamMember[]
  }
  stats: {
    badge: string
    title: string
    footnote: string
    items: AboutStat[]
  }
  join: {
    badge: string
    title: string
    body: string
    cta: string
  }
  cta: {
    headline: string
    subheadline: string
    primary: string
    secondary: string
  }
}

const aboutByLocale: Record<AppLocale, AboutContent> = {
  fr: {
    hero: {
      badge: 'Notre Histoire',
      headline: 'À Propos de Leopardo',
      subheadline: "Nous aidons les PME à gérer leurs employés simplement",
      ctaPrimary: 'Nous Contacter',
      ctaSecondary: "Rejoindre l'Équipe",
    },
    story: {
      badge: 'Notre Histoire',
      title: 'Comment Tout a Commencé',
      paragraphs: [
        "Leopardo a été fondée en 2020 par Ahmed Benali, un entrepreneur passionné qui a constaté que les PME manquaient d'une solution RH complète et abordable. Après avoir géré les ressources humaines avec Excel pendant des années, il a décidé de créer une plateforme qui changerait tout.",
        "Aujourd'hui, Leopardo accompagne des équipes RH en Afrique francophone et en Europe sur la paie, les congés et les documents employés, avec une exigence forte de sécurité et de conformité.",
        'Notre mission est simple: rendre la gestion RH accessible à tous, peu importe la taille de votre entreprise. Nous croyons que la technologie doit être simple, sécurisée et abordable.',
      ],
    },
    values: {
      badge: 'Nos Valeurs',
      title: 'Ce Qui Nous Guide',
      items: [
        { title: 'Simplicité', description: 'Nous croyons que la technologie doit être simple et intuitive, pas compliquée.' },
        { title: 'Support', description: "Nous sommes là pour vous. Support réactif et équipe dévouée à votre succès." },
        { title: 'Innovation', description: 'Nous innovons constamment pour vous offrir les meilleures solutions.' },
        { title: 'Confiance', description: 'Sécurité et transparence avant tout : vos données méritent une protection exemplaire.' },
      ],
    },
    team: {
      badge: 'Notre Équipe',
      title: 'Les Gens Derrière Leopardo',
      members: [
        { name: 'Ahmed Benali', role: 'Fondateur & CEO', bio: "Entrepreneur passionné avec 10 ans d'expérience en RH et technologie.", image: '/avatars/ahmed.svg' },
        { name: 'Fatima Dupont', role: 'CTO', bio: 'Architecte logiciel avec expertise en scalabilité et sécurité.', image: '/avatars/fatima.svg' },
        { name: 'Jean Martin', role: 'VP Product', bio: "Product manager avec passion pour l'expérience utilisateur.", image: '/avatars/jean.svg' },
        { name: 'Sophie Bernard', role: 'VP Sales', bio: 'Sales leader avec track record de croissance exponentielle.', image: '/avatars/sophie.svg' },
      ],
    },
    stats: {
      badge: 'Chiffres Clés',
      title: 'Leopardo en Chiffres',
      footnote: 'Métriques vérifiables dans le dépôt public du produit — aucun chiffre client inventé.',
      items: [
        { value: '19', label: 'Pays avec règles de paie dédiées' },
        { value: '4', label: 'Langues produit (FR/EN/TR/AR)' },
        { value: '7', label: 'Surfaces produit (web, mobile, kiosk)' },
        { value: '1200+', label: 'Tests automatisés backend' },
      ],
    },
    join: {
      badge: 'Rejoignez-Nous',
      title: 'Nous Recrutons!',
      body: "Vous êtes passionné par la technologie et l'innovation? Rejoignez notre équipe et aidez-nous à transformer la gestion RH pour les PME.",
      cta: "Voir les Offres d'Emploi",
    },
    cta: {
      headline: 'Prêt à Rejoindre Leopardo?',
      subheadline: 'Commencez votre essai gratuit de 14 jours dès maintenant',
      primary: 'Essai gratuit',
      secondary: 'Nous Contacter',
    },
  },
  en: {
    hero: {
      badge: 'Our Story',
      headline: 'About Leopardo',
      subheadline: 'We help SMEs manage their employees simply',
      ctaPrimary: 'Contact Us',
      ctaSecondary: 'Join the Team',
    },
    story: {
      badge: 'Our Story',
      title: 'How It All Started',
      paragraphs: [
        "Leopardo was founded in 2020 by Ahmed Benali, a passionate entrepreneur who saw that SMEs lacked a complete, affordable HR solution. After years of managing human resources with Excel, he decided to build a platform that would change everything.",
        "Today, Leopardo supports HR teams in French-speaking Africa and Europe on payroll, leave, and employee documents, with a strong focus on security and compliance.",
        'Our mission is simple: make HR management accessible to everyone, whatever the size of your company. We believe technology should be simple, secure, and affordable.',
      ],
    },
    values: {
      badge: 'Our Values',
      title: 'What Guides Us',
      items: [
        { title: 'Simplicity', description: 'We believe technology should be simple and intuitive, not complicated.' },
        { title: 'Support', description: 'We are here for you. Responsive support and a team dedicated to your success.' },
        { title: 'Innovation', description: 'We keep innovating to bring you the best solutions.' },
        { title: 'Trust', description: 'Security and transparency first: your data deserves exemplary protection.' },
      ],
    },
    team: {
      badge: 'Our Team',
      title: 'The People Behind Leopardo',
      members: [
        { name: 'Ahmed Benali', role: 'Founder & CEO', bio: 'Passionate entrepreneur with 10 years of experience in HR and technology.', image: '/avatars/ahmed.svg' },
        { name: 'Fatima Dupont', role: 'CTO', bio: 'Software architect with expertise in scalability and security.', image: '/avatars/fatima.svg' },
        { name: 'Jean Martin', role: 'VP Product', bio: 'Product manager passionate about user experience.', image: '/avatars/jean.svg' },
        { name: 'Sophie Bernard', role: 'VP Sales', bio: 'Sales leader with a track record of exponential growth.', image: '/avatars/sophie.svg' },
      ],
    },
    stats: {
      badge: 'Key Numbers',
      title: 'Leopardo in Numbers',
      footnote: 'Metrics verifiable in the public product repository — no invented customer figures.',
      items: [
        { value: '19', label: 'Countries with dedicated payroll rules' },
        { value: '4', label: 'Product languages (FR/EN/TR/AR)' },
        { value: '7', label: 'Product surfaces (web, mobile, kiosk)' },
        { value: '1200+', label: 'Automated backend tests' },
      ],
    },
    join: {
      badge: 'Join Us',
      title: 'We Are Hiring!',
      body: 'Passionate about technology and innovation? Join our team and help us transform HR management for SMEs.',
      cta: 'See Job Openings',
    },
    cta: {
      headline: 'Ready to Join Leopardo?',
      subheadline: 'Start your free 14-day trial now',
      primary: 'Free trial',
      secondary: 'Contact Us',
    },
  },
  tr: {
    hero: {
      badge: 'Hikayemiz',
      headline: 'Leopardo Hakkında',
      subheadline: 'KOBİlerin çalışanlarını kolayca yönetmesine yardımcı oluyoruz',
      ctaPrimary: 'Bize Ulaşın',
      ctaSecondary: 'Ekibe Katılın',
    },
    story: {
      badge: 'Hikayemiz',
      title: 'Her Şey Nasıl Başladı',
      paragraphs: [
        'Leopardo, 2020 yılında, KOBİlerin eksiksiz ve uygun fiyatlı bir İK çözümünden yoksun olduğunu gören tutkulu bir girişimci olan Ahmed Benali tarafından kuruldu. Yıllarca Excel ile insan kaynaklarını yönettikten sonra her şeyi değiştirecek bir platform oluşturmaya karar verdi.',
        'Bugün Leopardo, Fransızca konuşulan Afrika ve Avrupa\'daki İK ekiplerine maaş, izin ve çalışan belgeleri konusunda güçlü bir güvenlik ve uyumluluk anlayışıyla destek oluyor.',
        'Misyonumuz basit: şirketinizin büyüklüğü ne olursa olsun İK yönetimini herkes için erişilebilir kılmak. Teknolojinin basit, güvenli ve uygun fiyatlı olması gerektiğine inanıyoruz.',
      ],
    },
    values: {
      badge: 'Değerlerimiz',
      title: 'Bize Yön Veren',
      items: [
        { title: 'Basitlik', description: 'Teknolojinin basit ve sezgisel olması gerektiğine inanıyoruz, karmaşık değil.' },
        { title: 'Destek', description: 'Sizin için buradayız. Hızlı destek ve başarınıza adanmış bir ekip.' },
        { title: 'Yenilik', description: 'En iyi çözümleri sunmak için sürekli yenilik yapıyoruz.' },
        { title: 'Güven', description: 'Önce güvenlik ve şeffaflık: verileriniz örnek bir korumayı hak ediyor.' },
      ],
    },
    team: {
      badge: 'Ekibimiz',
      title: 'Leopardo\'nun Arkasındaki İnsanlar',
      members: [
        { name: 'Ahmed Benali', role: 'Kurucu & CEO', bio: 'İK ve teknolojide 10 yıllık deneyime sahip tutkulu girişimci.', image: '/avatars/ahmed.svg' },
        { name: 'Fatima Dupont', role: 'CTO', bio: 'Ölçeklenebilirlik ve güvenlik konusunda uzman yazılım mimarı.', image: '/avatars/fatima.svg' },
        { name: 'Jean Martin', role: 'VP Ürün', bio: 'Kullanıcı deneyimine tutkuyla bağlı ürün yöneticisi.', image: '/avatars/jean.svg' },
        { name: 'Sophie Bernard', role: 'VP Satış', bio: 'Üstel büyüme geçmişine sahip satış lideri.', image: '/avatars/sophie.svg' },
      ],
    },
    stats: {
      badge: 'Önemli Rakamlar',
      title: 'Rakamlarla Leopardo',
      footnote: 'Ürünün herkese açık deposunda doğrulanabilir metrikler — uydurma müşteri verisi yok.',
      items: [
        { value: '19', label: 'Özel maaş kuralları olan ülke' },
        { value: '4', label: 'Ürün dili (FR/EN/TR/AR)' },
        { value: '7', label: 'Ürün yüzeyi (web, mobil, kiosk)' },
        { value: '1200+', label: 'Otomatikleştirilmiş backend testi' },
      ],
    },
    join: {
      badge: 'Bize Katılın',
      title: 'İşe Alıyoruz!',
      body: 'Teknoloji ve yenilik konusunda tutkulu musunuz? Ekibimize katılın ve KOBİler için İK yönetimini dönüştürmemize yardım edin.',
      cta: 'Açık Pozisyonları Gör',
    },
    cta: {
      headline: 'Leopardo\'ya Katılmaya Hazır mısınız?',
      subheadline: 'Hemen ücretsiz 14 günlük denemenize başlayın',
      primary: 'Ücretsiz deneme',
      secondary: 'Bize Ulaşın',
    },
  },
  ar: {
    hero: {
      badge: 'قصتنا',
      headline: 'عن ليوباردو',
      subheadline: 'نساعد الشركات الصغيرة والمتوسطة على إدارة موظفيها ببساطة',
      ctaPrimary: 'اتصل بنا',
      ctaSecondary: 'انضم إلى الفريق',
    },
    story: {
      badge: 'قصتنا',
      title: 'كيف بدأ كل شيء',
      paragraphs: [
        'تأسست ليوباردو في عام 2020 على يد أحمد بن علي، رائد أعمال شغوف لاحظ افتقار الشركات الصغيرة والمتوسطة إلى حل موارد بشرية متكامل وبأسعار معقولة. بعد سنوات من إدارة الموارد البشرية باستخدام Excel، قرر إنشاء منصة تغير كل شيء.',
        'اليوم، تدعم ليوباردو فرق الموارد البشرية في أفريقيا الناطقة بالفرنسية وأوروبا في الرواتب والإجازات ووثائق الموظفين، مع تركيز قوي على الأمان والامتثال.',
        'مهمتنا بسيطة: جعل إدارة الموارد البشرية في متناول الجميع، مهما كان حجم شركتك. نؤمن بأن التكنولوجيا يجب أن تكون بسيطة وآمنة وبأسعار معقولة.',
      ],
    },
    values: {
      badge: 'قيمنا',
      title: 'ما يوجهنا',
      items: [
        { title: 'البساطة', description: 'نؤمن بأن التكنولوجيا يجب أن تكون بسيطة وبديهية، وليست معقدة.' },
        { title: 'الدعم', description: 'نحن هنا من أجلك. دعم سريع وفريق مكرس لنجاحك.' },
        { title: 'الابتكار', description: 'نبتكر باستمرار لنقدم لك أفضل الحلول.' },
        { title: 'الثقة', description: 'الأمان والشفافية أولاً: بياناتك تستحق حماية نموذجية.' },
      ],
    },
    team: {
      badge: 'فريقنا',
      title: 'الأشخاص خلف ليوباردو',
      members: [
        { name: 'أحمد بن علي', role: 'المؤسس والرئيس التنفيذي', bio: 'رائد أعمال شغوف بخبرة 10 سنوات في الموارد البشرية والتكنولوجيا.', image: '/avatars/ahmed.svg' },
        { name: 'فاطمة دوبون', role: 'المديرة التقنية', bio: 'مهندسة برمجيات بخبرة في قابلية التوسع والأمان.', image: '/avatars/fatima.svg' },
        { name: 'جان مارتن', role: 'نائب رئيس المنتج', bio: 'مدير منتج شغوف بتجربة المستخدم.', image: '/avatars/jean.svg' },
        { name: 'صوفي برنار', role: 'نائبة رئيس المبيعات', bio: 'قائدة مبيعات بسجل نمو متسارع.', image: '/avatars/sophie.svg' },
      ],
    },
    stats: {
      badge: 'أرقام رئيسية',
      title: 'ليوباردو بالأرقام',
      footnote: 'مقاييس يمكن التحقق منها في المستودع العام للمنتج — لا أرقام عملاء مختلقة.',
      items: [
        { value: '19', label: 'دول بقواعد رواتب مخصصة' },
        { value: '4', label: 'لغات المنتج (FR/EN/TR/AR)' },
        { value: '7', label: 'أسطح المنتج (ويب، موبايل، كشك)' },
        { value: '1200+', label: 'اختبار آلي للخلفية' },
      ],
    },
    join: {
      badge: 'انضم إلينا',
      title: 'نحن نوظف!',
      body: 'هل أنت شغوف بالتكنولوجيا والابتكار؟ انضم إلى فريقنا وساعدنا في تحويل إدارة الموارد البشرية للشركات الصغيرة والمتوسطة.',
      cta: 'عرض الوظائف الشاغرة',
    },
    cta: {
      headline: 'هل أنت مستعد للانضمام إلى ليوباردو؟',
      subheadline: 'ابدأ تجربتك المجانية لمدة 14 يوماً الآن',
      primary: 'تجربة مجانية',
      secondary: 'اتصل بنا',
    },
  },
}

export function getAboutContent(locale: AppLocale): AboutContent {
  return aboutByLocale[locale] ?? aboutByLocale.fr
}

