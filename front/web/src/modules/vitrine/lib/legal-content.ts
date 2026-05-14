import type { AppLocale } from '@/lib/i18n'

export type LegalPageKind = 'privacy' | 'terms'

type LegalSection = {
  title: string
  body: string[]
}

export type LegalPageCopy = {
  eyebrow: string
  title: string
  intro: string
  updatedAt: string
  backLabel: string
  languageLabel: string
  sections: LegalSection[]
  contact: {
    title: string
    body: string
    email: string
  }
}

const legalPages: Record<AppLocale, Record<LegalPageKind, LegalPageCopy>> = {
  fr: {
    privacy: {
      eyebrow: 'Conformite et donnees RH',
      title: 'Politique de confidentialite',
      intro:
        'Leopardo RH traite des donnees RH sensibles pour aider les entreprises a piloter le pointage, la paie, les absences et les workflows terrain. Cette page explique notre approche de protection, de transparence et de controle.',
      updatedAt: 'Derniere mise a jour : 14 mai 2026',
      backLabel: 'Retour a l accueil',
      languageLabel: 'Langue du document',
      sections: [
        {
          title: 'Donnees traitees',
          body: [
            'Nous pouvons traiter des donnees d identification, de contact, de poste, de pointage, d absence, de paie, de documents RH, de roles, de permissions et de journaux techniques.',
            'Les donnees biometriques ou assimilees ne doivent etre activees que lorsque le client dispose d une base legale claire et d un consentement ou cadre interne documente.',
          ],
        },
        {
          title: 'Finalites',
          body: [
            'Les traitements servent a securiser l acces, executer les processus RH, produire les rapports, notifier les utilisateurs, auditer les actions et maintenir la plateforme.',
            'Les donnees ne sont pas revendues. Les acces internes sont limites aux besoins de support, securite, exploitation et conformite.',
          ],
        },
        {
          title: 'Droits des utilisateurs',
          body: [
            'Les utilisateurs peuvent demander l export de leurs donnees, une correction, une limitation ou une suppression selon les lois applicables et les obligations de conservation de l employeur.',
            'La plateforme expose aussi des controles produit pour tracer les demandes de suppression et le consentement biometrique.',
          ],
        },
        {
          title: 'Securite',
          body: [
            'Leopardo RH applique l isolation multi-tenant, le controle d acces par roles, la journalisation des acces sensibles et une logique de moindre privilege.',
            'Les clients restent responsables de la configuration de leurs utilisateurs, de leurs politiques internes et de la verification des obligations locales.',
          ],
        },
      ],
      contact: {
        title: 'Contact confidentialite',
        body: 'Pour une demande de confidentialite ou de conformite, contactez notre equipe avec le nom de votre entreprise et le contexte de la demande.',
        email: 'privacy@leopardo-rh.com',
      },
    },
    terms: {
      eyebrow: 'Conditions de service',
      title: 'Conditions generales d utilisation',
      intro:
        'Ces conditions encadrent l utilisation de Leopardo RH par les entreprises, administrateurs, managers, employes, kiosques et integrateurs autorises.',
      updatedAt: 'Derniere mise a jour : 14 mai 2026',
      backLabel: 'Retour a l accueil',
      languageLabel: 'Langue du document',
      sections: [
        {
          title: 'Acces a la plateforme',
          body: [
            'L acces est reserve aux utilisateurs autorises par une entreprise cliente ou par Leopardo RH pour l administration de la plateforme.',
            'Chaque utilisateur doit proteger ses identifiants, respecter les permissions accordees et signaler toute activite suspecte.',
          ],
        },
        {
          title: 'Usage acceptable',
          body: [
            'La plateforme doit etre utilisee pour des processus RH legitimes : pointage, paie, absences, documents, notifications, reporting, support et operations associees.',
            'Il est interdit de contourner la securite, d extraire massivement des donnees sans autorisation ou d utiliser le service pour surveiller des personnes hors cadre legal.',
          ],
        },
        {
          title: 'Responsabilites client',
          body: [
            'Le client configure ses roles, ses workflows, ses regles RH, ses contenus et ses obligations de conformite locales.',
            'Les integrations, exports et imports doivent etre verifies avant usage en production, surtout lorsqu ils alimentent la paie ou des documents officiels.',
          ],
        },
        {
          title: 'Disponibilite et evolution',
          body: [
            'Leopardo RH peut faire evoluer les modules, APIs et interfaces pour ameliorer la securite, la performance et la valeur produit.',
            'Les operations critiques de maintenance, migration ou incident sont traitees selon les procedures d exploitation et de support en vigueur.',
          ],
        },
      ],
      contact: {
        title: 'Contact service',
        body: 'Pour une question contractuelle ou une demande liee au service, contactez notre equipe avec votre identifiant entreprise.',
        email: 'support@leopardo-rh.com',
      },
    },
  },
  en: {
    privacy: {
      eyebrow: 'HR data compliance',
      title: 'Privacy policy',
      intro:
        'Leopardo RH processes sensitive HR data to help companies run attendance, payroll, leave and field workflows. This page explains our approach to protection, transparency and control.',
      updatedAt: 'Last updated: May 14, 2026',
      backLabel: 'Back to home',
      languageLabel: 'Document language',
      sections: [
        {
          title: 'Data we process',
          body: [
            'We may process identity, contact, job, attendance, leave, payroll, HR document, role, permission and technical audit data.',
            'Biometric or biometric-like data should only be enabled when the customer has a clear legal basis and documented consent or internal policy.',
          ],
        },
        {
          title: 'Purposes',
          body: [
            'Processing supports secure access, HR workflows, reports, notifications, audit trails and platform operations.',
            'We do not sell HR data. Internal access is limited to support, security, operations and compliance needs.',
          ],
        },
        {
          title: 'User rights',
          body: [
            'Users may request export, correction, limitation or deletion according to applicable laws and employer retention obligations.',
            'The platform also exposes product controls to track deletion requests and biometric consent.',
          ],
        },
        {
          title: 'Security',
          body: [
            'Leopardo RH applies tenant isolation, role-based access control, sensitive data access logging and least-privilege principles.',
            'Customers remain responsible for user configuration, internal policies and local legal obligations.',
          ],
        },
      ],
      contact: {
        title: 'Privacy contact',
        body: 'For privacy or compliance requests, contact our team with your company name and request context.',
        email: 'privacy@leopardo-rh.com',
      },
    },
    terms: {
      eyebrow: 'Service terms',
      title: 'Terms of use',
      intro:
        'These terms govern the use of Leopardo RH by companies, administrators, managers, employees, kiosks and authorized integrators.',
      updatedAt: 'Last updated: May 14, 2026',
      backLabel: 'Back to home',
      languageLabel: 'Document language',
      sections: [
        {
          title: 'Platform access',
          body: [
            'Access is limited to users authorized by a customer company or by Leopardo RH for platform administration.',
            'Each user must protect credentials, respect granted permissions and report suspicious activity.',
          ],
        },
        {
          title: 'Acceptable use',
          body: [
            'The platform must be used for legitimate HR processes: attendance, payroll, leave, documents, notifications, reporting, support and related operations.',
            'Bypassing security, extracting data without authorization or monitoring people outside a legal framework is prohibited.',
          ],
        },
        {
          title: 'Customer responsibilities',
          body: [
            'The customer configures roles, workflows, HR rules, content and local compliance obligations.',
            'Integrations, exports and imports must be verified before production use, especially when they feed payroll or official documents.',
          ],
        },
        {
          title: 'Availability and evolution',
          body: [
            'Leopardo RH may evolve modules, APIs and interfaces to improve security, performance and product value.',
            'Critical maintenance, migration or incident operations are handled through the current operations and support procedures.',
          ],
        },
      ],
      contact: {
        title: 'Service contact',
        body: 'For contractual or service questions, contact our team with your company identifier.',
        email: 'support@leopardo-rh.com',
      },
    },
  },
  tr: {
    privacy: {
      eyebrow: 'IK veri uyumu',
      title: 'Gizlilik politikasi',
      intro:
        'Leopardo RH, sirketlerin devam, bordro, izin ve saha is akışlarini yonetmesine yardim etmek icin hassas IK verilerini isler. Bu sayfa koruma, seffaflik ve kontrol yaklasimimizi aciklar.',
      updatedAt: 'Son guncelleme: 14 Mayis 2026',
      backLabel: 'Ana sayfaya don',
      languageLabel: 'Belge dili',
      sections: [
        {
          title: 'Islenen veriler',
          body: [
            'Kimlik, iletisim, gorev, devam, izin, bordro, IK belgeleri, roller, izinler ve teknik denetim kayitlari islenebilir.',
            'Biyometrik veya benzeri veriler yalnizca musteri acik bir hukuki dayanak ve belgelenmis onay ya da ic politika sagladiginda etkinlestirilmelidir.',
          ],
        },
        {
          title: 'Amaclar',
          body: [
            'Isleme; guvenli erisim, IK is akislari, raporlar, bildirimler, denetim kayitlari ve platform operasyonlarini destekler.',
            'IK verileri satilmaz. Ic erisim destek, guvenlik, operasyon ve uyum ihtiyaclariyla sinirlidir.',
          ],
        },
        {
          title: 'Kullanici haklari',
          body: [
            'Kullanicilar, geçerli yasalara ve isveren saklama yukumluluklerine gore ihrac, duzeltme, sinirlama veya silme talebinde bulunabilir.',
            'Platform ayrica silme taleplerini ve biyometrik onayi izlemek icin urun kontrolleri sunar.',
          ],
        },
        {
          title: 'Guvenlik',
          body: [
            'Leopardo RH tenant izolasyonu, rol tabanli erisim kontrolu, hassas veri erisim gunlugu ve en az ayricalik ilkelerini uygular.',
            'Musteriler kullanici yapilandirmasi, ic politikalar ve yerel hukuki yukumluluklerden sorumludur.',
          ],
        },
      ],
      contact: {
        title: 'Gizlilik iletisimi',
        body: 'Gizlilik veya uyum talepleri icin sirket adiniz ve talep baglaminizla ekibimize ulasin.',
        email: 'privacy@leopardo-rh.com',
      },
    },
    terms: {
      eyebrow: 'Hizmet sartlari',
      title: 'Kullanim kosullari',
      intro:
        'Bu kosullar Leopardo RH nin sirketler, yoneticiler, mudurler, calisanlar, kiosklar ve yetkili entegratorler tarafindan kullanimini duzenler.',
      updatedAt: 'Son guncelleme: 14 Mayis 2026',
      backLabel: 'Ana sayfaya don',
      languageLabel: 'Belge dili',
      sections: [
        {
          title: 'Platform erisimi',
          body: [
            'Erisim, musteri sirket tarafindan veya platform yonetimi icin Leopardo RH tarafindan yetkilendirilen kullanicilarla sinirlidir.',
            'Her kullanici kimlik bilgilerini korumali, verilen izinlere uymali ve supheli etkinlikleri bildirmelidir.',
          ],
        },
        {
          title: 'Kabul edilebilir kullanim',
          body: [
            'Platform mesru IK surecleri icin kullanilmalidir: devam, bordro, izin, belgeler, bildirimler, raporlama, destek ve ilgili operasyonlar.',
            'Guvenligi asmak, yetkisiz veri cikarmak veya kisileri hukuki cerceve disinda izlemek yasaktir.',
          ],
        },
        {
          title: 'Musteri sorumluluklari',
          body: [
            'Musteri rollerini, is akislari, IK kurallari, icerikler ve yerel uyum yukumluluklerini yapilandirir.',
            'Entegrasyonlar, ihraclar ve ithalatlar uretimden once dogrulanmalidir; ozellikle bordro veya resmi belgeleri besliyorsa.',
          ],
        },
        {
          title: 'Erisilebilirlik ve gelisim',
          body: [
            'Leopardo RH guvenlik, performans ve urun degerini artirmak icin modulleri, API leri ve arayuzleri gelistirebilir.',
            'Kritik bakim, migrasyon veya olay operasyonlari guncel isletim ve destek prosedurleriyle yonetilir.',
          ],
        },
      ],
      contact: {
        title: 'Hizmet iletisimi',
        body: 'Sozlesme veya hizmet sorulari icin sirket kimliginizle ekibimize ulasin.',
        email: 'support@leopardo-rh.com',
      },
    },
  },
  ar: {
    privacy: {
      eyebrow: 'الامتثال وبيانات الموارد البشرية',
      title: 'سياسة الخصوصية',
      intro:
        'تعالج Leopardo RH بيانات موارد بشرية حساسة لمساعدة الشركات على إدارة الحضور والرواتب والإجازات وسير العمل الميداني. توضح هذه الصفحة نهجنا في الحماية والشفافية والتحكم.',
      updatedAt: 'آخر تحديث: 14 مايو 2026',
      backLabel: 'العودة إلى الصفحة الرئيسية',
      languageLabel: 'لغة الوثيقة',
      sections: [
        {
          title: 'البيانات التي نعالجها',
          body: [
            'قد نعالج بيانات الهوية والاتصال والوظيفة والحضور والإجازات والرواتب ووثائق الموارد البشرية والأدوار والصلاحيات وسجلات التدقيق التقنية.',
            'لا ينبغي تفعيل البيانات البيومترية أو المشابهة لها إلا عندما يملك العميل أساسا قانونيا واضحا وموافقة أو سياسة داخلية موثقة.',
          ],
        },
        {
          title: 'الأغراض',
          body: [
            'تدعم المعالجة الوصول الآمن وسير عمل الموارد البشرية والتقارير والإشعارات وسجلات التدقيق وتشغيل المنصة.',
            'لا نبيع بيانات الموارد البشرية. يقتصر الوصول الداخلي على احتياجات الدعم والأمن والتشغيل والامتثال.',
          ],
        },
        {
          title: 'حقوق المستخدمين',
          body: [
            'يمكن للمستخدمين طلب تصدير البيانات أو تصحيحها أو تقييدها أو حذفها وفق القوانين المطبقة والتزامات صاحب العمل بالاحتفاظ.',
            'توفر المنصة أيضا ضوابط لتتبع طلبات الحذف وموافقة البيانات البيومترية.',
          ],
        },
        {
          title: 'الأمان',
          body: [
            'تطبق Leopardo RH عزل المستأجرين والتحكم في الوصول حسب الأدوار وتسجيل الوصول إلى البيانات الحساسة ومبدأ أقل صلاحية.',
            'يبقى العملاء مسؤولين عن إعداد المستخدمين والسياسات الداخلية والتحقق من الالتزامات القانونية المحلية.',
          ],
        },
      ],
      contact: {
        title: 'التواصل بخصوص الخصوصية',
        body: 'لطلبات الخصوصية أو الامتثال، تواصل مع فريقنا مع اسم شركتك وسياق الطلب.',
        email: 'privacy@leopardo-rh.com',
      },
    },
    terms: {
      eyebrow: 'شروط الخدمة',
      title: 'شروط الاستخدام',
      intro:
        'تنظم هذه الشروط استخدام Leopardo RH من قبل الشركات والمسؤولين والمديرين والموظفين وأجهزة الكشك والمكاملين المعتمدين.',
      updatedAt: 'آخر تحديث: 14 مايو 2026',
      backLabel: 'العودة إلى الصفحة الرئيسية',
      languageLabel: 'لغة الوثيقة',
      sections: [
        {
          title: 'الوصول إلى المنصة',
          body: [
            'يقتصر الوصول على المستخدمين المصرح لهم من شركة عميلة أو من Leopardo RH لإدارة المنصة.',
            'يجب على كل مستخدم حماية بيانات الدخول واحترام الصلاحيات الممنوحة والإبلاغ عن أي نشاط مشبوه.',
          ],
        },
        {
          title: 'الاستخدام المقبول',
          body: [
            'يجب استخدام المنصة لعمليات موارد بشرية مشروعة مثل الحضور والرواتب والإجازات والوثائق والإشعارات والتقارير والدعم والعمليات المرتبطة.',
            'يحظر تجاوز الأمان أو استخراج البيانات دون تصريح أو مراقبة الأشخاص خارج إطار قانوني.',
          ],
        },
        {
          title: 'مسؤوليات العميل',
          body: [
            'يقوم العميل بإعداد الأدوار وسير العمل وقواعد الموارد البشرية والمحتوى والتزامات الامتثال المحلية.',
            'يجب التحقق من التكاملات وعمليات التصدير والاستيراد قبل استخدامها في الإنتاج، خاصة عندما تغذي الرواتب أو الوثائق الرسمية.',
          ],
        },
        {
          title: 'التوفر والتطور',
          body: [
            'قد تطور Leopardo RH الوحدات وواجهات API والواجهات لتحسين الأمان والأداء وقيمة المنتج.',
            'تدار عمليات الصيانة أو الترحيل أو الحوادث الحرجة وفق إجراءات التشغيل والدعم المعمول بها.',
          ],
        },
      ],
      contact: {
        title: 'التواصل بخصوص الخدمة',
        body: 'لأسئلة العقود أو الخدمة، تواصل مع فريقنا مع معرف شركتك.',
        email: 'support@leopardo-rh.com',
      },
    },
  },
}

export function getLegalPageCopy(locale: AppLocale, page: LegalPageKind): LegalPageCopy {
  return legalPages[locale][page] ?? legalPages.fr[page]
}
