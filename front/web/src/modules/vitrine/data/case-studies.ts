/**
 * Études de cas de la vitrine — données par locale (#4299).
 *
 * Les cartes de /case-studies étaient 100% FR (données en dur dans la page)
 * malgré des metadata localisées. Source unique désormais : cette table,
 * consommée par la page listing ; les pages /case-studies/[slug] (issues du
 * contenu module content.ts, périmètre #4196) reçoivent leurs libellés UI
 * de `caseStudyUiCopy`.
 */

import type { AppLocale } from '@/lib/i18n';

export type CaseStudyItem = {
  company: string;
  industry: string;
  employees: string;
  country: string;
  challenge: string;
  solution: string;
  results: Array<{ metric: string; label: string }>;
  testimonial: string;
  author: string;
  color: 'emerald' | 'blue' | 'amber';
};

export const caseStudiesByLocale: Record<AppLocale, CaseStudyItem[]> = {
  fr: [
    {
      company: 'TechCorp Algérie',
      industry: 'Technologie',
      employees: '120',
      country: 'Algérie',
      challenge: 'Suivi des présences manuel avec des feuilles Excel, erreurs fréquentes en paie, 3 jours par mois perdus en vérifications.',
      solution: 'Déploiement de bornes ZKTeco + module pointage Leopardo RH avec synchronisation automatique vers la paie.',
      results: [
        { metric: '-80%', label: 'Temps de suivi présences' },
        { metric: '-95%', label: 'Erreurs de paie' },
        { metric: '3j/mois', label: 'Temps récupéré' },
      ],
      testimonial: 'Le ROI a été immédiat. Nous avons éliminé les erreurs de pointage dès la première semaine.',
      author: 'Amina Belkacem, DRH',
      color: 'emerald',
    },
    {
      company: 'Atlas Industries',
      industry: 'Manufacture',
      employees: '350',
      country: 'Maroc, Tunisie, France',
      challenge: 'Gestion RH fragmentée sur 3 pays avec des logiciels différents, impossibilité de consolider les rapports.',
      solution: 'Migration vers Leopardo RH multi-tenant avec paie multi-pays (barèmes fiscaux locaux) et tableau de bord consolidé.',
      results: [
        { metric: '1', label: 'Plateforme unique pour 3 pays' },
        { metric: '-60%', label: 'Coût logiciel RH' },
        { metric: '100%', label: 'Conformité locale' },
      ],
      testimonial: 'Nous gérons maintenant 3 filiales depuis un seul dashboard. La paie multi-pays est un game-changer.',
      author: 'Mehdi Ouazzani, DG',
      color: 'blue',
    },
    {
      company: 'LogiTrans Express',
      industry: 'Transport & Logistique',
      employees: '200',
      country: 'Algérie',
      challenge: 'Chauffeurs sur le terrain sans accès au bureau, suivi des véhicules déconnecté de la RH, pointage impossible.',
      solution: 'App mobile Flutter pour pointage terrain + module flotte véhicules + kiosque ZKTeco aux dépôts.',
      results: [
        { metric: '100%', label: 'Couverture pointage terrain' },
        { metric: '-40%', label: 'Coûts carburant (optimisation routes)' },
        { metric: '24/7', label: 'Visibilité temps réel' },
      ],
      testimonial: 'Le suivi flotte + RH combiné est unique. Nos chauffeurs pointent depuis leur mobile et on suit tout en temps réel.',
      author: 'Karim Benali, CEO',
      color: 'amber',
    },
  ],
  en: [
    {
      company: 'TechCorp Algeria',
      industry: 'Technology',
      employees: '120',
      country: 'Algeria',
      challenge: 'Manual attendance tracking with Excel spreadsheets, frequent payroll errors, 3 days per month lost to verification.',
      solution: 'Deployment of ZKTeco terminals + Leopardo HR attendance module with automatic payroll synchronization.',
      results: [
        { metric: '-80%', label: 'Attendance tracking time' },
        { metric: '-95%', label: 'Payroll errors' },
        { metric: '3d/month', label: 'Time recovered' },
      ],
      testimonial: 'The ROI was immediate. We eliminated attendance errors within the first week.',
      author: 'Amina Belkacem, HR Director',
      color: 'emerald',
    },
    {
      company: 'Atlas Industries',
      industry: 'Manufacturing',
      employees: '350',
      country: 'Morocco, Tunisia, France',
      challenge: 'Fragmented HR management across 3 countries with different tools, impossible to consolidate reports.',
      solution: 'Migration to multi-tenant Leopardo HR with multi-country payroll (local tax scales) and a consolidated dashboard.',
      results: [
        { metric: '1', label: 'Single platform for 3 countries' },
        { metric: '-60%', label: 'HR software cost' },
        { metric: '100%', label: 'Local compliance' },
      ],
      testimonial: 'We now manage 3 subsidiaries from a single dashboard. Multi-country payroll is a game-changer.',
      author: 'Mehdi Ouazzani, CEO',
      color: 'blue',
    },
    {
      company: 'LogiTrans Express',
      industry: 'Transport & Logistics',
      employees: '200',
      country: 'Algeria',
      challenge: 'Field drivers with no office access, vehicle tracking disconnected from HR, impossible attendance.',
      solution: 'Flutter mobile app for field attendance + vehicle fleet module + ZKTeco kiosks at depots.',
      results: [
        { metric: '100%', label: 'Field attendance coverage' },
        { metric: '-40%', label: 'Fuel costs (route optimization)' },
        { metric: '24/7', label: 'Real-time visibility' },
      ],
      testimonial: 'The combined fleet + HR tracking is unique. Our drivers clock in from their phones and we track everything in real time.',
      author: 'Karim Benali, CEO',
      color: 'amber',
    },
  ],
  tr: [
    {
      company: 'TechCorp Cezayir',
      industry: 'Teknoloji',
      employees: '120',
      country: 'Cezayir',
      challenge: 'Excel tablolarıyla manuel devam takibi, sık maaş hataları, ayda 3 gün kontrol kaybı.',
      solution: 'ZKTeco terminalleri + maaş ile otomatik senkronizasyonlu Leopardo RH devam modülü.',
      results: [
        { metric: '-%80', label: 'Devam takip süresi' },
        { metric: '-%95', label: 'Maaş hataları' },
        { metric: '3g/ay', label: 'Kazanılan zaman' },
      ],
      testimonial: 'ROI anında geldi. İlk haftadan devam hatalarını ortadan kaldırdık.',
      author: 'Amina Belkacem, İK Direktörü',
      color: 'emerald',
    },
    {
      company: 'Atlas Industries',
      industry: 'Üretim',
      employees: '350',
      country: 'Fas, Tunus, Fransa',
      challenge: '3 ülkede farklı yazılımlarla parçalı İK yönetimi, raporların birleştirilememesi.',
      solution: 'Çok ülkeli maaş (yerel vergi dilimleri) ve konsolide panel ile çok kiracılı Leopardo RH geçişi.',
      results: [
        { metric: '1', label: '3 ülke için tek platform' },
        { metric: '-%60', label: 'İK yazılım maliyeti' },
        { metric: '%100', label: 'Yerel uyumluluk' },
      ],
      testimonial: 'Artık 3 şubeyi tek panelden yönetiyoruz. Çok ülkeli maaş oyunun kurallarını değiştirdi.',
      author: 'Mehdi Ouazzani, Genel Müdür',
      color: 'blue',
    },
    {
      company: 'LogiTrans Express',
      industry: 'Ulaşım & Lojistik',
      employees: '200',
      country: 'Cezayir',
      challenge: 'Sahadaki şoförlerin ofise erişimi yok, araç takibi İK ile bağlantısız, devam imkânsız.',
      solution: 'Saha devamı için Flutter mobil uygulama + araç filosu modülü + depolarda ZKTeco kioskları.',
      results: [
        { metric: '%100', label: 'Saha devam kapsamı' },
        { metric: '-%40', label: 'Yakıt maliyetleri (rota optimizasyonu)' },
        { metric: '7/24', label: 'Gerçek zamanlı görünürlük' },
      ],
      testimonial: 'Filo + İK takibinin birleşimi benzersiz. Şoförlerimiz telefondan imza atıyor, her şeyi anlık takip ediyoruz.',
      author: 'Karim Benali, CEO',
      color: 'amber',
    },
  ],
  ar: [
    {
      company: 'TechCorp الجزائر',
      industry: 'تكنولوجيا',
      employees: '120',
      country: 'الجزائر',
      challenge: 'تتبع حضور يدوي بجداول Excel، أخطاء رواتب متكررة، خسارة 3 أيام شهرياً في التدقيق.',
      solution: 'نشر أجهزة ZKTeco + وحدة الحضور في Leopardo RH مع مزامنة تلقائية مع الرواتب.',
      results: [
        { metric: '-80%', label: 'وقت تتبع الحضور' },
        { metric: '-95%', label: 'أخطاء الرواتب' },
        { metric: '3أ/شهر', label: 'الوقت المستعاد' },
      ],
      testimonial: 'كان العائد فورياً. تخلصنا من أخطاء الحضور منذ الأسبوع الأول.',
      author: 'أمينة بلقاسم، مديرة الموارد البشرية',
      color: 'emerald',
    },
    {
      company: 'Atlas Industries',
      industry: 'تصنيع',
      employees: '350',
      country: 'المغرب، تونس، فرنسا',
      challenge: 'إدارة موارد بشرية مجزأة عبر 3 دول بأنظمة مختلفة، واستحالة دمج التقارير.',
      solution: 'الانتقال إلى Leopardo RH متعدد المستأجرين مع رواتب متعددة الدول (شرائح ضريبية محلية) ولوحة تحكم موحدة.',
      results: [
        { metric: '1', label: 'منصة واحدة لـ3 دول' },
        { metric: '-60%', label: 'تكلفة برمجيات الموارد البشرية' },
        { metric: '100%', label: 'امتثال محلي' },
      ],
      testimonial: 'ندير الآن 3 فروع من لوحة تحكم واحدة. الرواتب متعددة الدول غيّرت قواعد اللعبة.',
      author: 'مهدي الوزاني، المدير العام',
      color: 'blue',
    },
    {
      company: 'LogiTrans Express',
      industry: 'نقل ولوجستيات',
      employees: '200',
      country: 'الجزائر',
      challenge: 'سائقون ميدانيون دون وصول للمكتب، تتبع مركبات منفصل عن الموارد البشرية، واستحالة تسجيل الحضور.',
      solution: 'تطبيق جوال Flutter للحضور الميداني + وحدة أسطول المركبات + أجهزة ZKTeco في المستودعات.',
      results: [
        { metric: '100%', label: 'تغطية الحضور الميداني' },
        { metric: '-40%', label: 'تكاليف الوقود (تحسين المسارات)' },
        { metric: '24/7', label: 'رؤية فورية' },
      ],
      testimonial: 'الجمع بين تتبع الأسطول والموارد البشرية فريد. سائقونا يسجلون حضورهم من هواتفهم ونتابع كل شيء لحظياً.',
      author: 'كريم بن علي، الرئيس التنفيذي',
      color: 'amber',
    },
  ],
};

/** Libellés UI de la page détail /case-studies/[slug] (#4299). */
export const caseStudyUiCopy: Record<AppLocale, {
  backLink: string;
  resultsTitle: string;
  seeAll: string;
  ctaTitle: string;
  demoCta: string;
  usageBadge: string;
  moduleIllustrates: string;
  moduleExplore: string;
  discoverModule: string;
  ctaDescription: string;
  ctaPrimaryText: string;
  useCase: string;
}> = {
  fr: {
    backLink: 'Toutes les études de cas',
    resultsTitle: 'Résultats clés',
    seeAll: 'Voir toutes les études de cas',
    ctaTitle: 'Votre entreprise pourrait être la prochaine',
    demoCta: 'Demander une démo',
    usageBadge: 'Cas d\'usage — {module}',
    moduleIllustrates: 'Ce cas illustre le module {module}',
    moduleExplore: 'Découvrez comment Leopardo RH couvre ce besoin au quotidien, ou explorez les autres études de cas.',
    discoverModule: 'Découvrir {module}',
    ctaDescription: 'Rejoignez les entreprises qui ont choisi Leopardo RH',
    ctaPrimaryText: 'Essai gratuit',
    useCase: "Cas d'usage — {module}",
  },
  en: {
    backLink: 'All case studies',
    resultsTitle: 'Key results',
    seeAll: 'See all case studies',
    ctaTitle: 'Your company could be next',
    demoCta: 'Request a demo',
    usageBadge: 'Use case — {module}',
    moduleIllustrates: 'This case illustrates the {module} module',
    moduleExplore: 'Discover how Leopardo RH covers this need day to day, or explore other case studies.',
    discoverModule: 'Discover {module}',
    ctaDescription: 'Join the companies that chose Leopardo RH',
    ctaPrimaryText: 'Free trial',
    useCase: 'Use case — {module}',
  },
  tr: {
    backLink: 'Tüm vaka çalışmaları',
    resultsTitle: 'Ana sonuçlar',
    seeAll: 'Tüm vaka çalışmalarını gör',
    ctaTitle: 'Sıradaki şirket siz olabilirsiniz',
    demoCta: 'Demo iste',
    usageBadge: 'Kullanım alanı — {module}',
    moduleIllustrates: 'Bu vaka {module} modülünü gösterir',
    moduleExplore: 'Leopardo RH’nin bu ihtiyacı günlük olarak nasıl karşıladığını keşfedin veya diğer vaka çalışmalarına göz atın.',
    discoverModule: '{module} modülünü keşfedin',
    ctaDescription: 'Leopardo RH’yi seçen şirketlere katılın',
    ctaPrimaryText: 'Ücretsiz deneme',
    useCase: 'Kullanım senaryosu — {module}',
  },
  ar: {
    backLink: 'كل دراسات الحالة',
    resultsTitle: 'النتائج الرئيسية',
    seeAll: 'عرض كل دراسات الحالة',
    ctaTitle: 'يمكن أن تكون شركتك التالية',
    demoCta: 'اطلب عرضاً توضيحياً',
    usageBadge: 'حالة استخدام — {module}',
    moduleIllustrates: 'توضح هذه الحالة وحدة {module}',
    moduleExplore: 'اكتشف كيف تغطي Leopardo RH هذه الحاجة يومياً، أو استكشف دراسات الحالة الأخرى.',
    discoverModule: 'اكتشف {module}',
    ctaDescription: 'انضم إلى الشركات التي اختارت Leopardo RH',
    ctaPrimaryText: 'تجربة مجانية',
    useCase: 'حالة استخدام — {module}',
  },
};
