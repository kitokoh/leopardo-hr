import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /careers par locale (issue #2605 — T004).
// Les icônes restent dans la page ; ce fichier ne porte que les chaînes.

export type CareersOpening = {
  title: string
  department: string
  location: string
  type: string
  description: string
}

export type CareersContent = {
  hero: {
    badge: string
    headline: string
    subheadline: string
    cta: string
  }
  values: {
    title: string
    subtitle: string
    items: Array<{ title: string; description: string }>
  }
  benefits: {
    title: string
    items: string[]
  }
  openings: {
    title: string
    subtitle: string
    items: CareersOpening[]
  }
  cta: {
    headline: string
    subheadline: string
    ctaText: string
  }
}

const careersByLocale: Record<AppLocale, CareersContent> = {
  fr: {
    hero: {
      badge: 'Carrières',
      headline: "Rejoignez l'Équipe",
      subheadline: 'Construisez le futur de la gestion RH avec nous',
      cta: 'Voir les Postes',
    },
    values: {
      title: 'Nos Valeurs',
      subtitle: 'Ce qui nous anime au quotidien',
      items: [
        { title: 'Impact réel', description: "Nous construisons des outils qui simplifient la vie de milliers de professionnels RH chaque jour." },
        { title: 'Innovation', description: "Nous adoptons les dernières technologies et expérimentons constamment pour offrir le meilleur produit." },
        { title: 'Diversité', description: "Notre équipe est distribuée globalement. Nous valorisons les perspectives différentes." },
        { title: 'Collaboration', description: "Nous travaillons ensemble, partageons nos connaissances et célébrons nos réussites en équipe." },
      ],
    },
    benefits: {
      title: 'Avantages',
      items: [
        'Télétravail flexible (full remote possible)',
        'Mutuelle santé premium',
        'Budget formation annuel',
        'Matériel au choix (Mac/PC/Linux)',
        'Tickets restaurant',
        'Team buildings trimestriels',
        'Congés supplémentaires après 2 ans',
        'Stock options pour les postes senior',
      ],
    },
    openings: {
      title: 'Postes Ouverts',
      subtitle: 'postes disponibles',
      items: [
        { title: 'Développeur Full-Stack Senior', department: 'Engineering', location: 'Paris / Remote', type: 'CDI', description: "Rejoignez notre équipe pour développer les nouvelles fonctionnalités de la plateforme RH." },
        { title: 'Designer UI/UX', department: 'Design', location: 'Paris / Remote', type: 'CDI', description: 'Concevez des interfaces intuitives pour notre application web et mobile.' },
        { title: 'Customer Success Manager', department: 'Customer Success', location: 'Paris', type: 'CDI', description: "Accompagnez nos clients dans l'adoption de Leopardo RH." },
        { title: 'Développeur Mobile Flutter', department: 'Engineering', location: 'Remote', type: 'CDI', description: 'Développez et améliorez notre application mobile multi-plateforme.' },
        { title: 'DevOps Engineer', department: 'Engineering', location: 'Paris / Remote', type: 'CDI', description: 'Optimisez notre infrastructure cloud et nos pipelines CI/CD.' },
      ],
    },
    cta: {
      headline: 'Aucun Poste ne Correspond ?',
      subheadline: 'Envoyez-nous votre candidature spontanée',
      ctaText: 'Candidature Spontanée',
    },
  },
  en: {
    hero: {
      badge: 'Careers',
      headline: "Join the Team",
      subheadline: 'Build the future of HR management with us',
      cta: 'See Openings',
    },
    values: {
      title: 'Our Values',
      subtitle: 'What drives us every day',
      items: [
        { title: 'Real Impact', description: 'We build tools that simplify the daily life of thousands of HR professionals.' },
        { title: 'Innovation', description: 'We adopt the latest technologies and experiment constantly to deliver the best product.' },
        { title: 'Diversity', description: 'Our team is distributed globally. We value different perspectives.' },
        { title: 'Collaboration', description: 'We work together, share knowledge, and celebrate team successes.' },
      ],
    },
    benefits: {
      title: 'Benefits',
      items: [
        'Flexible remote work (fully remote possible)',
        'Premium health insurance',
        'Annual learning budget',
        'Hardware of your choice (Mac/PC/Linux)',
        'Meal vouchers',
        'Quarterly team buildings',
        'Extra leave after 2 years',
        'Stock options for senior roles',
      ],
    },
    openings: {
      title: 'Open Positions',
      subtitle: 'open positions',
      items: [
        { title: 'Senior Full-Stack Developer', department: 'Engineering', location: 'Paris / Remote', type: 'Full-time', description: 'Join our team to build the new features of the HR platform.' },
        { title: 'UI/UX Designer', department: 'Design', location: 'Paris / Remote', type: 'Full-time', description: 'Design intuitive interfaces for our web and mobile application.' },
        { title: 'Customer Success Manager', department: 'Customer Success', location: 'Paris', type: 'Full-time', description: 'Support our clients in adopting Leopardo RH.' },
        { title: 'Flutter Mobile Developer', department: 'Engineering', location: 'Remote', type: 'Full-time', description: 'Build and improve our cross-platform mobile application.' },
        { title: 'DevOps Engineer', department: 'Engineering', location: 'Paris / Remote', type: 'Full-time', description: 'Optimize our cloud infrastructure and CI/CD pipelines.' },
      ],
    },
    cta: {
      headline: 'No Position Matches?',
      subheadline: 'Send us your open application',
      ctaText: 'Open Application',
    },
  },
  tr: {
    hero: {
      badge: 'Kariyer',
      headline: 'Ekibe Katılın',
      subheadline: 'Bizimle İK yönetiminin geleceğini inşa edin',
      cta: 'Pozisyonları Gör',
    },
    values: {
      title: 'Değerlerimiz',
      subtitle: 'Bizi her gün motive eden şey',
      items: [
        { title: 'Gerçek Etki', description: 'Binlerce İK profesyonelinin günlük işini kolaylaştıran araçlar inşa ediyoruz.' },
        { title: 'Yenilik', description: 'En iyi ürünü sunmak için son teknolojileri benimsiyor ve sürekli deniyoruz.' },
        { title: 'Çeşitlilik', description: 'Ekibimiz küresel olarak dağılmıştır. Farklı bakış açılarına değer veriyoruz.' },
        { title: 'İşbirliği', description: 'Birlikte çalışıyor, bilgi paylaşıyor ve ekip başarılarını kutluyoruz.' },
      ],
    },
    benefits: {
      title: 'Yan Haklar',
      items: [
        'Esnek uzaktan çalışma (tamamen remote mümkün)',
        'Premium sağlık sigortası',
        'Yıllık eğitim bütçesi',
        'Seçiminize göre donanım (Mac/PC/Linux)',
        'Yemek kartı',
        'Üç ayda bir team building',
        '2 yıl sonra ekstra izin',
        'Kıdemli roller için hisse opsiyonları',
      ],
    },
    openings: {
      title: 'Açık Pozisyonlar',
      subtitle: 'açık pozisyon',
      items: [
        { title: 'Kıdemli Full-Stack Geliştirici', department: 'Mühendislik', location: 'Paris / Remote', type: 'Tam zamanlı', description: 'İK platformunun yeni özelliklerini geliştirmek için ekibimize katılın.' },
        { title: 'UI/UX Tasarımcısı', department: 'Tasarım', location: 'Paris / Remote', type: 'Tam zamanlı', description: 'Web ve mobil uygulamamız için sezgisel arayüzler tasarlayın.' },
        { title: 'Müşteri Başarı Yöneticisi', department: 'Müşteri Başarısı', location: 'Paris', type: 'Tam zamanlı', description: 'Müşterilerimize Leopardo RH benimsenmesinde eşlik edin.' },
        { title: 'Flutter Mobil Geliştirici', department: 'Mühendislik', location: 'Remote', type: 'Tam zamanlı', description: 'Çok platformlu mobil uygulamamızı geliştirin ve iyileştirin.' },
        { title: 'DevOps Mühendisi', department: 'Mühendislik', location: 'Paris / Remote', type: 'Tam zamanlı', description: 'Bulut altyapımızı ve CI/CD hatlarımızı optimize edin.' },
      ],
    },
    cta: {
      headline: 'Uygun Pozisyon Yok mu?',
      subheadline: 'Bize açık başvurunuzu gönderin',
      ctaText: 'Açık Başvuru',
    },
  },
  ar: {
    hero: {
      badge: 'الوظائف',
      headline: 'انضم إلى الفريق',
      subheadline: 'ابنِ مستقبل إدارة الموارد البشرية معنا',
      cta: 'عرض الوظائف',
    },
    values: {
      title: 'قيمنا',
      subtitle: 'ما يحفزنا كل يوم',
      items: [
        { title: 'أثر حقيقي', description: 'نبني أدوات تبسط الحياة اليومية لآلاف المتخصصين في الموارد البشرية.' },
        { title: 'الابتكار', description: 'نعتمد أحدث التقنيات ونجرب باستمرار لنقدم أفضل منتج.' },
        { title: 'التنوع', description: 'فريقنا موزع عالمياً. نقدر وجهات النظر المختلفة.' },
        { title: 'التعاون', description: 'نعمل معاً، نشارك المعرفة، ونحتفل بنجاحات الفريق.' },
      ],
    },
    benefits: {
      title: 'المزايا',
      items: [
        'عمل عن بُعد مرن (عن بُعد كلياً ممكن)',
        'تأمين صحي ممتاز',
        'ميزانية تدريب سنوية',
        'جهاز من اختيارك (Mac/PC/Linux)',
        'بطاقات وجبات',
        'أنشطة جماعية فصلية',
        'إجازات إضافية بعد سنتين',
        'خيارات أسهم للمناصب العليا',
      ],
    },
    openings: {
      title: 'الوظائف الشاغرة',
      subtitle: 'وظيفة شاغرة',
      items: [
        { title: 'مطور Full-Stack أول', department: 'الهندسة', location: 'باريس / عن بُعد', type: 'دوام كامل', description: 'انضم إلى فريقنا لتطوير الميزات الجديدة لمنصة الموارد البشرية.' },
        { title: 'مصمم UI/UX', department: 'التصميم', location: 'باريس / عن بُعد', type: 'دوام كامل', description: 'صمم واجهات بديهية لتطبيقنا على الويب والجوال.' },
        { title: 'مدير نجاح العملاء', department: 'نجاح العملاء', location: 'باريس', type: 'دوام كامل', description: 'رافق عملاءنا في اعتماد ليوباردو RH.' },
        { title: 'مطور تطبيقات Flutter', department: 'الهندسة', location: 'عن بُعد', type: 'دوام كامل', description: 'طور وحسّن تطبيقنا للجوال متعدد المنصات.' },
        { title: 'مهندس DevOps', department: 'الهندسة', location: 'باريس / عن بُعد', type: 'دوام كامل', description: 'حسّن بنيتنا السحابية وخطوط CI/CD.' },
      ],
    },
    cta: {
      headline: 'لا يوجد منصب مناسب؟',
      subheadline: 'أرسل لنا طلب توظيف مفتوح',
      ctaText: 'طلب توظيف مفتوح',
    },
  },
}

export function getCareersContent(locale: AppLocale): CareersContent {
  return careersByLocale[locale] ?? careersByLocale.fr
}

