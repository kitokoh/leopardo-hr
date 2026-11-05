/**
 * Leopardo Toolbox — copie i18n du module (fr, en, tr, ar).
 *
 * Le module est AUTONOME : sa copie vit ici plutôt que dans le catalogue
 * partagé `shared/i18n` (qui est propagé vers le backend et le mobile, où la
 * Toolbox n'existe pas). Les 4 locales sont obligatoires — la vitrine est
 * servie en fr/en/tr/ar et l'arabe est RTL.
 *
 * ⚠️ Toute chaîne visible doit venir d'ici : aucune copie en dur dans les
 * composants (convention vitrine, gardes mojibake/accents).
 */

export const TOOLBOX_LOCALES = ['fr', 'en', 'tr', 'ar'] as const;
export type ToolboxLocale = (typeof TOOLBOX_LOCALES)[number];

export interface ToolCopy {
  name: string;
  description: string;
  /** Libellés des champs, indexés par identifiant de champ. */
  fields: Record<string, string>;
  /** Libellés des options de listes déroulantes, indexés par valeur. */
  options?: Record<string, string>;
  /** Libellés des lignes de résultat, indexés par identifiant. */
  results: Record<string, string>;
  /** Notes d'hypothèses affichées sous le formulaire. */
  notes?: string[];
}

export interface ToolboxCopy {
  meta: { title: string; description: string };
  nav: { label: string };
  catalog: {
    title: string;
    subtitle: string;
    searchPlaceholder: string;
    noResults: string;
    allCategories: string;
    toolsCount: string;
    openTool: string;
  };
  categories: Record<string, string>;
  warnings: Record<string, string>;
  refs: Record<string, string>;
  common: {
    result: string;
    copy: string;
    copied: string;
    reset: string;
    reference: string;
    version: string;
    backToCatalog: string;
    disclaimer: string;
    invalidInput: string;
    notFound: string;
    notFoundHint: string;
  };
  tools: Record<string, ToolCopy>;
}

const fr: ToolboxCopy = {
  meta: {
    title: 'Outils en ligne gratuits',
    description:
      'Calculateurs et convertisseurs gratuits, utilisables immédiatement sans compte : IMC, TVA, salaire brut en net, conversion d’unités et pourcentages.',
  },
  nav: { label: 'Outils' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Des outils numériques universels, gratuits et sans inscription. Choisissez un outil et utilisez-le immédiatement.',
    searchPlaceholder: 'Rechercher un outil…',
    noResults: 'Aucun outil ne correspond à votre recherche.',
    allCategories: 'Toutes les catégories',
    toolsCount: 'outils disponibles',
    openTool: 'Ouvrir l’outil',
  },
  categories: {
    calcul: 'Calcul',
    conversion: 'Conversion',
    finance: 'Finance',
    sante: 'Santé',
    travail: 'Travail',
  },
  warnings: {
    estimate:
      'Résultat indicatif : cette estimation ne remplace pas un document officiel (bulletin de paie, devis, facture).',
    theoretical:
      'Résultat théorique : il ne constitue en aucun cas une validation de sécurité.',
    notAdvice:
      'Information à titre éducatif uniquement : cela ne constitue pas un conseil professionnel, médical ou juridique.',
  },
  refs: {
    whoBmi: 'Organisation mondiale de la santé — obésité et IMC',
    frVatRates: 'impots.gouv.fr — les taux de TVA',
    siBrochure: 'BIPM — brochure sur le Système international d’unités (SI)',
  },
  common: {
    result: 'Résultat',
    copy: 'Copier',
    copied: 'Copié',
    reset: 'Réinitialiser',
    reference: 'Référence',
    version: 'Version du calcul',
    backToCatalog: 'Retour à tous les outils',
    disclaimer:
      'Les outils Leopardo sont fournis à titre informatif. Vérifiez toujours les résultats avant un usage professionnel.',
    invalidInput: 'Saisissez une valeur valide.',
    notFound: 'Outil introuvable',
    notFoundHint: 'Cet outil n’existe pas ou a été renommé. Parcourez le catalogue pour trouver l’outil souhaité.',
  },
  tools: {
    imc: {
      name: 'IMC — Indice de masse corporelle',
      description:
        'Calculez votre indice de masse corporelle à partir de votre poids et de votre taille, avec la classification de l’OMS.',
      fields: { weight: 'Poids (kg)', height: 'Taille (cm)' },
      results: { imc: 'IMC', category: 'Interprétation' },
      notes: [
        'Formule : IMC = poids (kg) ÷ taille (m)².',
        'Classification OMS : < 18,5 insuffisance pondérale ; 18,5–24,9 corpulence normale ; 25–29,9 surpoids ; ≥ 30 obésité.',
        'L’IMC ne tient pas compte de la masse musculaire, de l’âge ni de la morphologie.',
      ],
    },
    tva: {
      name: 'TVA — calcul HT / TTC',
      description:
        'Calculez la TVA, le montant hors taxes ou le montant toutes taxes comprises à partir d’un taux.',
      fields: { amount: 'Montant', rate: 'Taux de TVA', direction: 'Sens du calcul' },
      options: {
        'ht-to-ttc': 'HT → TTC (ajouter la TVA)',
        'ttc-to-ht': 'TTC → HT (retirer la TVA)',
      },
      results: { ht: 'Montant HT', vat: 'TVA', ttc: 'Montant TTC' },
      notes: [
        'Formule : TTC = HT × (1 + taux) ; HT = TTC ÷ (1 + taux).',
        'Taux français courants : 20 % (normal), 10 %, 5,5 %, 2,1 %.',
      ],
    },
    'salaire-net': {
      name: 'Salaire brut → net (estimation)',
      description:
        'Estimez le salaire net mensuel à partir du brut, avec un taux de cotisations salariales paramétrable.',
      fields: { gross: 'Salaire brut mensuel', rate: 'Taux de cotisations salariales (%)' },
      results: { net: 'Salaire net estimé', deductions: 'Cotisations estimées' },
      notes: [
        'Formule : net ≈ brut × (1 − taux de cotisations salariales).',
        'Le taux réel dépend du statut, du pays et de l’entreprise : ajustez-le pour affiner l’estimation.',
        'Cette estimation ne remplace pas un bulletin de paie officiel.',
      ],
    },
    'convertisseur-unites': {
      name: 'Convertisseur d’unités',
      description:
        'Convertissez longueurs, masses, volumes, surfaces, vitesses et températures avec des facteurs exacts.',
      fields: { value: 'Valeur', category: 'Catégorie', from: 'De', to: 'Vers' },
      results: { converted: 'Valeur convertie' },
      notes: [
        'Les facteurs de conversion sont exacts (définitions SI) ; la température utilise des formules affines.',
        'Les résultats sont arrondis à l’affichage, le calcul reste en pleine précision.',
      ],
    },
    pourcentage: {
      name: 'Pourcentages et évolution',
      description:
        'Calculez un pourcentage d’une valeur, une évolution entre deux valeurs ou une remise.',
      fields: { mode: 'Calcul', a: 'Valeur A', b: 'Valeur B' },
      options: {
        'percent-of': 'Pourcentage de A (%)',
        evolution: 'Évolution de A vers B (%)',
        discount: 'Remise de B % sur A',
      },
      results: { value: 'Résultat' },
      notes: [
        'Pourcentage de A : A × B ÷ 100.',
        'Évolution : (B − A) ÷ A × 100.',
        'Remise : A × (1 − B ÷ 100).',
      ],
    },
  },
};

const en: ToolboxCopy = {
  meta: {
    title: 'Free online tools',
    description:
      'Free calculators and converters you can use right away, no account needed: BMI, VAT, gross-to-net salary, unit conversion and percentages.',
  },
  nav: { label: 'Tools' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Universal digital tools, free and with no sign-up. Pick a tool and use it immediately.',
    searchPlaceholder: 'Search for a tool…',
    noResults: 'No tool matches your search.',
    allCategories: 'All categories',
    toolsCount: 'tools available',
    openTool: 'Open tool',
  },
  categories: {
    calcul: 'Calculation',
    conversion: 'Conversion',
    finance: 'Finance',
    sante: 'Health',
    travail: 'Work',
  },
  warnings: {
    estimate:
      'Indicative result: this estimate does not replace an official document (payslip, quote, invoice).',
    theoretical: 'Theoretical result: it is in no way a safety validation.',
    notAdvice:
      'Educational information only: this is not professional, medical or legal advice.',
  },
  refs: {
    whoBmi: 'World Health Organization — obesity and BMI',
    frVatRates: 'impots.gouv.fr — French VAT rates',
    siBrochure: 'BIPM — SI brochure (International System of Units)',
  },
  common: {
    result: 'Result',
    copy: 'Copy',
    copied: 'Copied',
    reset: 'Reset',
    reference: 'Reference',
    version: 'Calculation version',
    backToCatalog: 'Back to all tools',
    disclaimer:
      'Leopardo tools are provided for information only. Always verify results before professional use.',
    invalidInput: 'Enter a valid value.',
    notFound: 'Tool not found',
    notFoundHint: 'This tool does not exist or has been renamed. Browse the catalogue to find the tool you need.',
  },
  tools: {
    imc: {
      name: 'BMI — Body mass index',
      description:
        'Compute your body mass index from your weight and height, with the WHO classification.',
      fields: { weight: 'Weight (kg)', height: 'Height (cm)' },
      results: { imc: 'BMI', category: 'Interpretation' },
      notes: [
        'Formula: BMI = weight (kg) ÷ height (m)².',
        'WHO classification: < 18.5 underweight; 18.5–24.9 normal; 25–29.9 overweight; ≥ 30 obesity.',
        'BMI does not account for muscle mass, age or body shape.',
      ],
    },
    tva: {
      name: 'VAT — net / gross calculation',
      description: 'Compute VAT, the net amount or the gross amount from a rate.',
      fields: { amount: 'Amount', rate: 'VAT rate', direction: 'Calculation direction' },
      options: {
        'ht-to-ttc': 'Net → gross (add VAT)',
        'ttc-to-ht': 'Gross → net (remove VAT)',
      },
      results: { ht: 'Net amount', vat: 'VAT', ttc: 'Gross amount' },
      notes: [
        'Formula: gross = net × (1 + rate); net = gross ÷ (1 + rate).',
        'Common French rates: 20% (standard), 10%, 5.5%, 2.1%.',
      ],
    },
    'salaire-net': {
      name: 'Gross → net salary (estimate)',
      description:
        'Estimate the monthly net salary from the gross amount, with an adjustable employee contribution rate.',
      fields: { gross: 'Monthly gross salary', rate: 'Employee contribution rate (%)' },
      results: { net: 'Estimated net salary', deductions: 'Estimated contributions' },
      notes: [
        'Formula: net ≈ gross × (1 − employee contribution rate).',
        'The real rate depends on status, country and employer: adjust it to refine the estimate.',
        'This estimate does not replace an official payslip.',
      ],
    },
    'convertisseur-unites': {
      name: 'Unit converter',
      description:
        'Convert lengths, masses, volumes, areas, speeds and temperatures with exact factors.',
      fields: { value: 'Value', category: 'Category', from: 'From', to: 'To' },
      results: { converted: 'Converted value' },
      notes: [
        'Conversion factors are exact (SI definitions); temperature uses affine formulas.',
        'Results are rounded for display; the computation keeps full precision.',
      ],
    },
    pourcentage: {
      name: 'Percentages and change',
      description: 'Compute a percentage of a value, a change between two values, or a discount.',
      fields: { mode: 'Calculation', a: 'Value A', b: 'Value B' },
      options: {
        'percent-of': 'Percentage of A (%)',
        evolution: 'Change from A to B (%)',
        discount: 'Discount of B% on A',
      },
      results: { value: 'Result' },
      notes: [
        'Percentage of A: A × B ÷ 100.',
        'Change: (B − A) ÷ A × 100.',
        'Discount: A × (1 − B ÷ 100).',
      ],
    },
  },
};

const tr: ToolboxCopy = {
  meta: {
    title: 'Ücretsiz çevrimiçi araçlar',
    description:
      'Hemen kullanabileceğiniz ücretsiz hesaplayıcılar ve dönüştürücüler, hesap gerekmez: VKİ, KDV, brütten nete maaş, birim dönüştürme ve yüzdeler.',
  },
  nav: { label: 'Araçlar' },
  catalog: {
    title: 'Leopardo Toolbox',
    subtitle:
      'Evrensel dijital araçlar, ücretsiz ve kayıt gerektirmez. Bir araç seçin ve hemen kullanın.',
    searchPlaceholder: 'Araç ara…',
    noResults: 'Aramanızla eşleşen araç yok.',
    allCategories: 'Tüm kategoriler',
    toolsCount: 'araç mevcut',
    openTool: 'Aracı aç',
  },
  categories: {
    calcul: 'Hesaplama',
    conversion: 'Dönüştürme',
    finance: 'Finans',
    sante: 'Sağlık',
    travail: 'İş',
  },
  warnings: {
    estimate:
      'Gösterge niteliğinde sonuç: bu tahmin resmi bir belgenin (bordro, teklif, fatura) yerini tutmaz.',
    theoretical: 'Teorik sonuç: hiçbir şekilde bir güvenlik doğrulaması değildir.',
    notAdvice: 'Yalnızca bilgilendirme amaçlıdır: profesyonel, tıbbi veya hukuki tavsiye değildir.',
  },
  refs: {
    whoBmi: 'Dünya Sağlık Örgütü — obezite ve VKİ',
    frVatRates: 'impots.gouv.fr — Fransız KDV oranları',
    siBrochure: 'BIPM — Uluslararası Birimler Sistemi (SI) broşürü',
  },
  common: {
    result: 'Sonuç',
    copy: 'Kopyala',
    copied: 'Kopyalandı',
    reset: 'Sıfırla',
    reference: 'Kaynak',
    version: 'Hesaplama sürümü',
    backToCatalog: 'Tüm araçlara dön',
    disclaimer:
      'Leopardo araçları yalnızca bilgi amaçlıdır. Profesyonel kullanımdan önce sonuçları her zaman doğrulayın.',
    invalidInput: 'Geçerli bir değer girin.',
    notFound: 'Araç bulunamadı',
    notFoundHint: 'Bu araç yok veya yeniden adlandırıldı. İhtiyacınız olan aracı bulmak için kataloğa göz atın.',
  },
  tools: {
    imc: {
      name: 'VKİ — Vücut kitle indeksi',
      description:
        'Kilo ve boyunuzdan vücut kitle indeksinizi WHO sınıflandırmasıyla hesaplayın.',
      fields: { weight: 'Kilo (kg)', height: 'Boy (cm)' },
      results: { imc: 'VKİ', category: 'Yorum' },
      notes: [
        'Formül: VKİ = kilo (kg) ÷ boy (m)².',
        'WHO sınıflandırması: < 18,5 zayıf; 18,5–24,9 normal; 25–29,9 fazla kilolu; ≥ 30 obez.',
        'VKİ kas kütlesini, yaşı veya vücut yapısını dikkate almaz.',
      ],
    },
    tva: {
      name: 'KDV — net / brüt hesaplama',
      description: 'Bir orandan KDV, net tutar veya brüt tutarı hesaplayın.',
      fields: { amount: 'Tutar', rate: 'KDV oranı', direction: 'Hesaplama yönü' },
      options: {
        'ht-to-ttc': 'Net → brüt (KDV ekle)',
        'ttc-to-ht': 'Brüt → net (KDV çıkar)',
      },
      results: { ht: 'Net tutar', vat: 'KDV', ttc: 'Brüt tutar' },
      notes: [
        'Formül: brüt = net × (1 + oran); net = brüt ÷ (1 + oran).',
        'Yaygın Fransız oranları: %20 (normal), %10, %5,5, %2,1.',
      ],
    },
    'salaire-net': {
      name: 'Brüt → net maaş (tahmin)',
      description:
        'Brüt tutardan aylık net maaşı, ayarlanabilir çalışan prim oranıyla tahmin edin.',
      fields: { gross: 'Aylık brüt maaş', rate: 'Çalışan prim oranı (%)' },
      results: { net: 'Tahmini net maaş', deductions: 'Tahmini primler' },
      notes: [
        'Formül: net ≈ brüt × (1 − çalışan prim oranı).',
        'Gerçek oran statüye, ülkeye ve işverene bağlıdır: tahmini iyileştirmek için ayarlayın.',
        'Bu tahmin resmi bir bordronun yerini tutmaz.',
      ],
    },
    'convertisseur-unites': {
      name: 'Birim dönüştürücü',
      description:
        'Uzunluk, kütle, hacim, alan, hız ve sıcaklığı kesin faktörlerle dönüştürün.',
      fields: { value: 'Değer', category: 'Kategori', from: 'Kaynak', to: 'Hedef' },
      results: { converted: 'Dönüştürülen değer' },
      notes: [
        'Dönüştürme faktörleri kesindir (SI tanımları); sıcaklık afin formüller kullanır.',
        'Sonuçlar gösterim için yuvarlanır; hesaplama tam hassasiyeti korur.',
      ],
    },
    pourcentage: {
      name: 'Yüzdeler ve değişim',
      description: 'Bir değerin yüzdesini, iki değer arasındaki değişimi veya bir indirimi hesaplayın.',
      fields: { mode: 'Hesaplama', a: 'Değer A', b: 'Değer B' },
      options: {
        'percent-of': 'A’nın yüzdesi (%)',
        evolution: 'A’dan B’ye değişim (%)',
        discount: 'A üzerinde %B indirim',
      },
      results: { value: 'Sonuç' },
      notes: [
        'A’nın yüzdesi: A × B ÷ 100.',
        'Değişim: (B − A) ÷ A × 100.',
        'İndirim: A × (1 − B ÷ 100).',
      ],
    },
  },
};

const ar: ToolboxCopy = {
  meta: {
    title: 'أدوات مجانية عبر الإنترنت',
    description:
      'حاسبات ومحوّلات مجانية يمكنك استخدامها فورًا دون حساب: مؤشر كتلة الجسم، ضريبة القيمة المضافة، الراتب من الإجمالي إلى الصافي، تحويل الوحدات والنسب المئوية.',
  },
  nav: { label: 'أدوات' },
  catalog: {
    title: 'صندوق أدوات ليوباردو',
    subtitle: 'أدوات رقمية عالمية، مجانية وبدون تسجيل. اختر أداة واستخدمها فورًا.',
    searchPlaceholder: 'ابحث عن أداة…',
    noResults: 'لا توجد أداة تطابق بحثك.',
    allCategories: 'كل الفئات',
    toolsCount: 'أداة متاحة',
    openTool: 'افتح الأداة',
  },
  categories: {
    calcul: 'حساب',
    conversion: 'تحويل',
    finance: 'مالية',
    sante: 'صحة',
    travail: 'عمل',
  },
  warnings: {
    estimate: 'نتيجة إرشادية: هذا التقدير لا يحل محل وثيقة رسمية (قسيمة راتب، عرض سعر، فاتورة).',
    theoretical: 'نتيجة نظرية: لا تشكّل بأي حال من الأحوال تحققًا من السلامة.',
    notAdvice: 'معلومات لأغراض تعليمية فقط: لا تُعد نصيحة مهنية أو طبية أو قانونية.',
  },
  refs: {
    whoBmi: 'منظمة الصحة العالمية — السمنة ومؤشر كتلة الجسم',
    frVatRates: 'impots.gouv.fr — معدلات ضريبة القيمة المضافة الفرنسية',
    siBrochure: 'BIPM — كتيّب النظام الدولي للوحدات (SI)',
  },
  common: {
    result: 'النتيجة',
    copy: 'نسخ',
    copied: 'تم النسخ',
    reset: 'إعادة تعيين',
    reference: 'المرجع',
    version: 'إصدار الحساب',
    backToCatalog: 'العودة إلى كل الأدوات',
    disclaimer: 'أدوات ليوباردو مقدَّمة لأغراض إعلامية فقط. تحقّق دائمًا من النتائج قبل الاستخدام المهني.',
    invalidInput: 'أدخل قيمة صحيحة.',
    notFound: 'الأداة غير موجودة',
    notFoundHint: 'هذه الأداة غير موجودة أو تم تغيير اسمها. تصفّح الكتالوج للعثور على الأداة التي تحتاجها.',
  },
  tools: {
    imc: {
      name: 'مؤشر كتلة الجسم',
      description: 'احسب مؤشر كتلة جسمك من وزنك وطولك، مع تصنيف منظمة الصحة العالمية.',
      fields: { weight: 'الوزن (كغ)', height: 'الطول (سم)' },
      results: { imc: 'مؤشر كتلة الجسم', category: 'التفسير' },
      notes: [
        'الصيغة: مؤشر كتلة الجسم = الوزن (كغ) ÷ الطول (م)².',
        'تصنيف منظمة الصحة العالمية: أقل من 18.5 نقص وزن؛ 18.5–24.9 طبيعي؛ 25–29.9 زيادة وزن؛ 30 فأكثر سمنة.',
        'لا يأخذ المؤشر في الحسبان الكتلة العضلية أو العمر أو شكل الجسم.',
      ],
    },
    tva: {
      name: 'ضريبة القيمة المضافة — حساب الصافي / الإجمالي',
      description: 'احسب ضريبة القيمة المضافة أو المبلغ الصافي أو الإجمالي انطلاقًا من معدل.',
      fields: { amount: 'المبلغ', rate: 'معدل الضريبة', direction: 'اتجاه الحساب' },
      options: {
        'ht-to-ttc': 'صافي ← إجمالي (إضافة الضريبة)',
        'ttc-to-ht': 'إجمالي ← صافي (إزالة الضريبة)',
      },
      results: { ht: 'المبلغ الصافي', vat: 'الضريبة', ttc: 'المبلغ الإجمالي' },
      notes: [
        'الصيغة: الإجمالي = الصافي × (1 + المعدل)؛ الصافي = الإجمالي ÷ (1 + المعدل).',
        'المعدلات الفرنسية الشائعة: 20٪ (العادي)، 10٪، 5.5٪، 2.1٪.',
      ],
    },
    'salaire-net': {
      name: 'الراتب من الإجمالي إلى الصافي (تقدير)',
      description: 'قدّر الراتب الصافي الشهري من الإجمالي، مع معدل اشتراكات قابل للتعديل.',
      fields: { gross: 'الراتب الإجمالي الشهري', rate: 'معدل اشتراكات الموظف (٪)' },
      results: { net: 'الراتب الصافي المقدَّر', deductions: 'الاشتراكات المقدَّرة' },
      notes: [
        'الصيغة: الصافي ≈ الإجمالي × (1 − معدل اشتراكات الموظف).',
        'يعتمد المعدل الحقيقي على الوضع والبلد وصاحب العمل: عدّله لتحسين التقدير.',
        'هذا التقدير لا يحل محل قسيمة راتب رسمية.',
      ],
    },
    'convertisseur-unites': {
      name: 'محوّل الوحدات',
      description: 'حوّل الأطوال والكتل والأحجام والمساحات والسرعات ودرجات الحرارة بمعاملات دقيقة.',
      fields: { value: 'القيمة', category: 'الفئة', from: 'من', to: 'إلى' },
      results: { converted: 'القيمة المحوَّلة' },
      notes: [
        'معاملات التحويل دقيقة (تعريفات النظام الدولي)؛ تستخدم الحرارة صيغًا خطية.',
        'تُقرَّب النتائج للعرض، ويحتفظ الحساب بالدقة الكاملة.',
      ],
    },
    pourcentage: {
      name: 'النسب المئوية والتغيّر',
      description: 'احسب نسبة من قيمة، أو تغيّرًا بين قيمتين، أو خصمًا.',
      fields: { mode: 'الحساب', a: 'القيمة أ', b: 'القيمة ب' },
      options: {
        'percent-of': 'نسبة من أ (٪)',
        evolution: 'التغيّر من أ إلى ب (٪)',
        discount: 'خصم ب٪ على أ',
      },
      results: { value: 'النتيجة' },
      notes: [
        'نسبة من أ: أ × ب ÷ 100.',
        'التغيّر: (ب − أ) ÷ أ × 100.',
        'الخصم: أ × (1 − ب ÷ 100).',
      ],
    },
  },
};

const CATALOGS: Record<ToolboxLocale, ToolboxCopy> = { fr, en, tr, ar };

/** Retourne la copie complète du module pour une locale (repli : fr). */
export function getToolboxCopy(locale: string): ToolboxCopy {
  return CATALOGS[locale as ToolboxLocale] ?? CATALOGS.fr;
}

/** Retourne la copie d'un outil, ou `null` si le slug est inconnu. */
export function getToolCopy(locale: string, slug: string): ToolCopy | null {
  return getToolboxCopy(locale).tools[slug] ?? null;
}
