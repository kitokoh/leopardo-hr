import type { AppLocale } from '@/lib/i18n'

/**
 * Pages légales de la vitrine (#7593).
 *
 * ⚠️ À COMPLÉTER PAR LE PROPRIÉTAIRE avant mise en production, dans la section
 * « Identifiants légaux » des mentions légales : dénomination sociale exacte,
 * adresse du siège, numéro d'immatriculation, identifiant fiscal, et le nom de
 * l'hébergeur. Ces valeurs ne sont pas dans le dépôt et **n'ont pas été
 * inventées** : en attendant, la page indique comment les obtenir. Le reste
 * (rôles, bases légales, durées, sous-traitants, transferts, droits, cookies,
 * autorité de contrôle) est rédigé dans les 4 langues.
 */
export type LegalPageKind = 'privacy' | 'terms' | 'legal'

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
      eyebrow: 'Conformite et données RH',
      title: 'Politique de confidentialité',
      intro:
        'Leopardo traite des données RH sensibles pour aider les entreprises a piloter le pointage, la paie, les absences et les workflows terrain. Cette page explique notre approche de protection, de transparence et de controle.',
      updatedAt: 'Dernière mise a jour : 14 mai 2026',
      backLabel: 'Retour à l’accueil',
      languageLabel: 'Langue du document',
      sections: [
        {
          title: 'Données traitees',
          body: [
            'Nous pouvons traiter des données d identification, de contact, de poste, de pointage, d absence, de paie, de documents RH, de roles, de permissions et de journaux techniques.',
            'Les données biometriques ou assimilees ne doivent être activées que lorsque le client dispose d une base legale claire et d un consentement ou cadre interne documente.',
          ],
        },
        {
          title: 'Finalites',
          body: [
            'Les traitements servent a securiser l accès, executer les processus RH, produire les rapports, notifier les utilisateurs, auditer les actions et maintenir la plateforme.',
            'Les données ne sont pas revendues. Les acces internes sont limites aux besoins de support, sécurité, exploitation et conformité.',
          ],
        },
        {
          title: 'Droits des utilisateurs',
          body: [
            'Les utilisateurs peuvent demander l export de leurs données, une correction, une limitation ou une suppression selon les lois applicables et les obligations de conservation de l employeur.',
            'La plateforme expose aussi des contrôles produit pour tracer les demandes de suppression et le consentement biométrique.',
          ],
        },
        {
          title: 'Sécurité',
          body: [
            'Leopardo applique l isolation multi-tenant, le controle d accès par roles, la journalisation des accès sensibles et une logique de moindre privilege.',
            'Les clients restent responsables de la configuration de leurs utilisateurs, de leurs politiques internes et de la vérification des obligations locales.',
          ],
        },
        {
          title: 'Responsable de traitement',
          body: [
            'Leopardo édite et héberge la plateforme. Pour les données de vos salariés (pointage, absences, paie, documents RH), votre entreprise est responsable de traitement et Leopardo agit comme sous-traitant, sur vos instructions.',
            'Pour les données de la vitrine et de la relation commerciale, Leopardo est responsable de traitement.',
          ],
        },
        {
          title: 'Base légale',
          body: [
            'Chaque traitement repose sur une base identifiée : exécution du contrat (fourniture du service), obligation légale (conservation des documents de paie et sociaux), intérêt légitime (sécurité, prévention de la fraude, support), ou consentement (mesure d\'audience, personnalisation marketing, données biométriques).',
          ],
        },
        {
          title: 'Durées de conservation',
          body: [
            'Compte et données de production : pendant la relation contractuelle, puis 12 mois. Journaux techniques : 12 mois. Documents de paie et sociaux : durées légales applicables. Mesure d\'audience : 14 mois maximum. Choix de consentement : 6 mois, puis re-sollicitation.',
          ],
        },
        {
          title: 'Destinataires et sous-traitants',
          body: [
            'Accès interne limité aux équipes support, sécurité et ingénierie, selon le besoin d\'en connaître.',
            'Sous-traitants : hébergement (infrastructure située dans l\'Union européenne), supervision des erreurs applicatives, et mesure d\'audience (Google Analytics 4, Mixpanel) uniquement après votre accord explicite. La liste à jour est communiquée sur demande.',
          ],
        },
        {
          title: 'Transferts hors Union européenne',
          body: [
            'L\'hébergement des données de production est situé dans l\'Union européenne. Certains prestataires peuvent traiter des données hors UE : ces transferts sont encadrés par des garanties appropriées (clauses contractuelles types). La mesure d\'audience, susceptible d\'impliquer un tel transfert, est désactivée par défaut.',
          ],
        },
        {
          title: 'Cookies et mesure d’audience',
          body: [
            'Les cookies nécessaires (session, langue, sécurité) sont toujours actifs. La mesure d\'audience et la personnalisation marketing ne sont activées qu\'après votre accord, et ce choix se modifie à tout moment via « Gérer mes cookies », en pied de page. Votre choix est conservé 6 mois.',
          ],
        },
        {
          title: 'Exercice des droits et réclamation',
          body: [
            'Vous pouvez exercer vos droits (accès, rectification, effacement, limitation, opposition, portabilité) en écrivant à privacy@leopardo-rh.com ; nous répondons dans un délai d\'un mois. Vous pouvez également saisir l\'autorité de protection des données compétente : en Algérie, l\'ANPDP ; dans l\'Union européenne, l\'autorité de votre pays de résidence.',
          ],
        },
        {
          title: 'Violations de données',
          body: [
            'En cas de violation susceptible d\'engendrer un risque élevé pour vos droits, nous informons les clients concernés et, lorsque la réglementation l\'exige, l\'autorité compétente, dans les délais prévus.',
          ],
        },
      ],
      contact: {
        title: 'Contact confidentialité',
        body: 'Pour une demande de confidentialité ou de conformité, contactez notre équipe avec le nom de votre entreprise et le contexte de la demande.',
        email: 'privacy@leopardo-rh.com',
      },
    },
    terms: {
      eyebrow: 'Conditions de service',
      title: 'Conditions generales d utilisation',
      intro:
        'Ces conditions encadrent l utilisation de Leopardo par les entreprises, administrateurs, managers, employés, kiosques et integrateurs autorises.',
      updatedAt: 'Dernière mise a jour : 14 mai 2026',
      backLabel: 'Retour à l’accueil',
      languageLabel: 'Langue du document',
      sections: [
        {
          title: 'Accès a la plateforme',
          body: [
            'L accès est reserve aux utilisateurs autorises par une entreprise cliente ou par Leopardo pour l’administration de la plateforme.',
            'Chaque utilisateur doit proteger ses identifiants, respecter les permissions accordees et signaler toute activité suspecte.',
          ],
        },
        {
          title: 'Usage acceptable',
          body: [
            'La plateforme doit etre utilisee pour des processus RH legitimes : pointage, paie, absences, documents, notifications, reporting, support et operations associees.',
            'Il est interdit de contourner la sécurité, d extraire massivement des données sans autorisation ou d utiliser le service pour surveiller des personnes hors cadre legal.',
          ],
        },
        {
          title: 'Responsabilites client',
          body: [
            'Le client configure ses roles, ses workflows, ses regles RH, ses contenus et ses obligations de conformité locales.',
            'Les integrations, exports et imports doivent etre verifies avant usage en production, surtout lorsqu ils alimentent la paie ou des documents officiels.',
          ],
        },
        {
          title: 'Disponibilité et evolution',
          body: [
            'Leopardo peut faire evoluer les modules, APIs et interfaces pour ameliorer la sécurité, la performance et la valeur produit.',
            'Les operations critiques de maintenance, migration ou incident sont traitees selon les procedures d exploitation et de support en vigueur.',
          ],
        },
      ],
      contact: {
        title: 'Contact service',
        body: 'Pour une question contractuelle ou une demande liée au service, contactez notre équipe avec votre identifiant entreprise.',
        email: 'support@leopardo-rh.com',
      },
    },
      legal: {
      eyebrow: 'Informations légales',
      title: 'Mentions légales',
      intro:
        'Informations relatives à l\'éditeur du site, à son hébergement et à la propriété de ses contenus.',
      updatedAt: 'Dernière mise à jour : 16 septembre 2026',
      backLabel: 'Retour à l’accueil',
      languageLabel: 'Langue du document',
      sections: [
        {
          title: 'Éditeur du site',
          body: [
            'Le site vitrine et la plateforme Leopardo sont édités par Leopardo (Alger, Algérie).',
            'Contact : contact@leopardo-rh.com.',
          ],
        },
        {
          title: 'Identifiants légaux',
          body: [
            'Les identifiants de l\'éditeur (dénomination, siège social, immatriculation, identifiant fiscal) et le nom de l\'hébergeur sont communiqués sur simple demande à contact@leopardo-rh.com, en attendant leur publication sur cette page.',
          ],
        },
        {
          title: 'Hébergement',
          body: [
            'L\'infrastructure d\'hébergement est située dans l\'Union européenne ; les données de production ne sont pas hébergées hors de l\'Union européenne.',
          ],
        },
        {
          title: 'Propriété intellectuelle',
          body: [
            'La marque, le code, les interfaces et les contenus du site sont protégés. Toute reproduction ou réutilisation sans autorisation écrite préalable est interdite.',
          ],
        },
        {
          title: 'Données personnelles',
          body: [
            'Le traitement des données personnelles est décrit dans la politique de confidentialité : finalités, bases légales, durées de conservation, destinataires et exercice de vos droits.',
          ],
        },
      ],
      contact: {
        title: 'Contact',
        body: 'Pour toute question relative à ces mentions, écrivez-nous.',
        email: 'contact@leopardo-rh.com',
      },
    },
},
  en: {
    privacy: {
      eyebrow: 'HR data compliance',
      title: 'Privacy policy',
      intro:
        'Leopardo processes sensitive HR data to help companies run attendance, payroll, leave and field workflows. This page explains our approach to protection, transparency and control.',
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
            'Leopardo applies tenant isolation, role-based access control, sensitive data access logging and least-privilege principles.',
            'Customers remain responsible for user configuration, internal policies and local legal obligations.',
          ],
        },
        {
          title: 'Data controller',
          body: [
            'Leopardo publishes and hosts the platform. For your employees\' data (attendance, leave, payroll, HR documents), your company is the data controller and Leopardo acts as a processor, on your instructions.',
            'For website and commercial relationship data, Leopardo is the data controller.',
          ],
        },
        {
          title: 'Legal basis',
          body: [
            'Each processing activity relies on an identified basis: performance of the contract (service delivery), legal obligation (retention of payroll and social documents), legitimate interest (security, fraud prevention, support), or consent (analytics, marketing personalisation, biometric data).',
          ],
        },
        {
          title: 'Retention periods',
          body: [
            'Account and production data: for the duration of the contract, then 12 months. Technical logs: 12 months. Payroll and social documents: applicable statutory periods. Analytics: 14 months maximum. Consent choice: 6 months, then asked again.',
          ],
        },
        {
          title: 'Recipients and processors',
          body: [
            'Internal access is limited to support, security and engineering teams on a need-to-know basis.',
            'Processors: hosting (infrastructure located in the European Union), application error monitoring, and analytics (Google Analytics 4, Mixpanel) only after your explicit consent. The up-to-date list is available on request.',
          ],
        },
        {
          title: 'Transfers outside the European Union',
          body: [
            'Production data is hosted in the European Union. Some providers may process data outside the EU: such transfers are covered by appropriate safeguards (standard contractual clauses). Analytics, which may involve such a transfer, is off by default.',
          ],
        },
        {
          title: 'Cookies and analytics',
          body: [
            'Necessary cookies (session, language, security) are always active. Analytics and marketing personalisation are enabled only after your consent, and you can change that choice at any time via “Manage my cookies” in the footer. Your choice is stored for 6 months.',
          ],
        },
        {
          title: 'Exercising your rights and complaints',
          body: [
            'You can exercise your rights (access, rectification, erasure, restriction, objection, portability) by writing to privacy@leopardo-rh.com; we reply within one month. You may also contact the competent data protection authority: in Algeria, the ANPDP; in the European Union, the authority of your country of residence.',
          ],
        },
        {
          title: 'Data breaches',
          body: [
            'If a breach is likely to result in a high risk to your rights, we inform the affected customers and, where required by law, the competent authority, within the applicable timeframes.',
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
        'These terms govern the use of Leopardo by companies, administrators, managers, employees, kiosks and authorized integrators.',
      updatedAt: 'Last updated: May 14, 2026',
      backLabel: 'Back to home',
      languageLabel: 'Document language',
      sections: [
        {
          title: 'Platform access',
          body: [
            'Access is limited to users authorized by a customer company or by Leopardo for platform administration.',
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
            'Leopardo may evolve modules, APIs and interfaces to improve security, performance and product value.',
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
      legal: {
      eyebrow: 'Legal information',
      title: 'Legal notice',
      intro:
        'Information about the publisher of this site, its hosting and the ownership of its content.',
      updatedAt: 'Last updated: 16 September 2026',
      backLabel: 'Back to home',
      languageLabel: 'Document language',
      sections: [
        {
          title: 'Site publisher',
          body: [
            'The Leopardo website and platform are published by Leopardo (Algiers, Algeria).',
            'Contact: contact@leopardo-rh.com.',
          ],
        },
        {
          title: 'Legal identifiers',
          body: [
            'The publisher\'s identifiers (company name, registered office, registration number, tax identifier) and the name of the host are provided on request at contact@leopardo-rh.com, pending publication on this page.',
          ],
        },
        {
          title: 'Hosting',
          body: [
            'The hosting infrastructure is located in the European Union; production data is not hosted outside the European Union.',
          ],
        },
        {
          title: 'Intellectual property',
          body: [
            'The brand, code, interfaces and content of this site are protected. Any reproduction or reuse without prior written permission is prohibited.',
          ],
        },
        {
          title: 'Personal data',
          body: [
            'How personal data is processed is described in the privacy policy: purposes, legal bases, retention periods, recipients and how to exercise your rights.',
          ],
        },
      ],
      contact: {
        title: 'Contact',
        body: 'For any question about this legal notice, write to us.',
        email: 'contact@leopardo-rh.com',
      },
    },
},
  tr: {
    privacy: {
      eyebrow: 'IK veri uyumu',
      title: 'Gizlilik politikasi',
      intro:
        'Leopardo, sirketlerin devam, bordro, izin ve saha is akışlarini yonetmesine yardim etmek icin hassas IK verilerini isler. Bu sayfa koruma, seffaflik ve kontrol yaklasimimizi aciklar.',
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
            'Leopardo tenant izolasyonu, rol tabanli erisim kontrolu, hassas veri erisim gunlugu ve en az ayricalik ilkelerini uygular.',
            'Musteriler kullanici yapilandirmasi, ic politikalar ve yerel hukuki yukumluluklerden sorumludur.',
          ],
        },
        {
          title: 'Veri sorumlusu',
          body: [
            'Leopardo platformu yayınlar ve barındırır. Çalışanlarınızın verileri (yoklama, izin, bordro, İK belgeleri) için veri sorumlusu şirketinizdir; Leopardo talimatlarınız doğrultusunda veri işleyen olarak hareket eder.',
            'Site ve ticari ilişki verileri için veri sorumlusu Leopardo\'dur.',
          ],
        },
        {
          title: 'Hukuki dayanak',
          body: [
            'Her işleme belirli bir dayanağa oturur: sözleşmenin ifası (hizmetin sunulması), yasal yükümlülük (bordro ve sosyal belgelerin saklanması), meşru menfaat (güvenlik, dolandırıcılık önleme, destek) veya açık rıza (ölçümleme, pazarlama kişiselleştirme, biyometrik veriler).',
          ],
        },
        {
          title: 'Saklama süreleri',
          body: [
            'Hesap ve üretim verileri: sözleşme süresince, ardından 12 ay. Teknik kayıtlar: 12 ay. Bordro ve sosyal belgeler: ilgili yasal süreler. Ölçümleme: en fazla 14 ay. Çerez tercihi: 6 ay, sonra yeniden sorulur.',
          ],
        },
        {
          title: 'Alıcılar ve alt işleyenler',
          body: [
            'Dahili erişim, bilmesi gerekenler ilkesiyle destek, güvenlik ve mühendislik ekipleriyle sınırlıdır.',
            'Alt işleyenler: barındırma (Avrupa Birliği\'nde bulunan altyapı), uygulama hata izleme ve yalnızca açık onayınızdan sonra ölçümleme (Google Analytics 4, Mixpanel). Güncel liste talep üzerine paylaşılır.',
          ],
        },
        {
          title: 'Avrupa Birliği dışına aktarımlar',
          body: [
            'Üretim verileri Avrupa Birliği\'nde barındırılır. Bazı sağlayıcılar verileri AB dışında işleyebilir; bu aktarımlar uygun güvencelerle (standart sözleşme maddeleri) çerçevelenir. Aktarım içerebilen ölçümleme varsayılan olarak kapalıdır.',
          ],
        },
        {
          title: 'Çerezler ve ölçümleme',
          body: [
            'Gerekli çerezler (oturum, dil, güvenlik) her zaman açıktır. Ölçümleme ve pazarlama kişiselleştirme yalnızca onayınızdan sonra etkinleşir; tercihinizi sayfa altındaki “Çerezleri yönet” üzerinden istediğiniz zaman değiştirebilirsiniz. Tercih 6 ay saklanır.',
          ],
        },
        {
          title: 'Hakların kullanılması ve şikâyet',
          body: [
            'Haklarınızı (erişim, düzeltme, silme, kısıtlama, itiraz, taşınabilirlik) privacy@leopardo-rh.com adresine yazarak kullanabilirsiniz; bir ay içinde yanıt veririz. Yetkili veri koruma kurumuna da başvurabilirsiniz: Cezayir\'de ANPDP; Avrupa Birliği\'nde ikamet ettiğiniz ülkenin kurumu.',
          ],
        },
        {
          title: 'Veri ihlalleri',
          body: [
            'Haklarınız açısından yüksek risk doğurabilecek bir ihlal durumunda, etkilenen müşterileri ve mevzuatın gerektirdiği hâllerde yetkili kurumu öngörülen süreler içinde bilgilendiririz.',
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
        'Bu kosullar Leopardo\'nun sirketler, yoneticiler, mudurler, calisanlar, kiosklar ve yetkili entegratorler tarafindan kullanimini duzenler.',
      updatedAt: 'Son guncelleme: 14 Mayis 2026',
      backLabel: 'Ana sayfaya don',
      languageLabel: 'Belge dili',
      sections: [
        {
          title: 'Platform erisimi',
          body: [
            'Erisim, musteri sirket tarafindan veya platform yonetimi icin Leopardo tarafindan yetkilendirilen kullanicilarla sinirlidir.',
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
            'Leopardo guvenlik, performans ve urun degerini artirmak icin modulleri, API leri ve arayuzleri gelistirebilir.',
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
      legal: {
      eyebrow: 'Yasal bilgiler',
      title: 'Yasal bildirim',
      intro:
        'Bu sitenin yayıncısı, barındırılması ve içeriklerinin mülkiyeti hakkında bilgiler.',
      updatedAt: 'Son güncelleme: 16 Eylül 2026',
      backLabel: 'Ana sayfaya dön',
      languageLabel: 'Belge dili',
      sections: [
        {
          title: 'Site yayıncısı',
          body: [
            'Leopardo web sitesi ve platformu Leopardo (Cezayir, Cezayir) tarafından yayımlanır.',
            'İletişim: contact@leopardo-rh.com.',
          ],
        },
        {
          title: 'Yasal kimlik bilgileri',
          body: [
            'Yayıncının kimlik bilgileri (unvan, merkez adresi, sicil numarası, vergi numarası) ve barındırıcının adı, bu sayfada yayımlanana kadar contact@leopardo-rh.com adresinden talep üzerine iletilir.',
          ],
        },
        {
          title: 'Barındırma',
          body: [
            'Barındırma altyapısı Avrupa Birliği\'nde bulunur; üretim verileri AB dışında barındırılmaz.',
          ],
        },
        {
          title: 'Fikri mülkiyet',
          body: [
            'Marka, kod, arayüzler ve site içerikleri korunmaktadır. Önceden yazılı izin olmaksızın çoğaltma veya yeniden kullanım yasaktır.',
          ],
        },
        {
          title: 'Kişisel veriler',
          body: [
            'Kişisel verilerin işlenmesi gizlilik politikasında açıklanır: amaçlar, hukuki dayanaklar, saklama süreleri, alıcılar ve haklarınızı kullanma yolları.',
          ],
        },
      ],
      contact: {
        title: 'İletişim',
        body: 'Bu bildirimle ilgili her soru için bize yazın.',
        email: 'contact@leopardo-rh.com',
      },
    },
},
  ar: {
    privacy: {
      eyebrow: 'الامتثال وبيانات الموارد البشرية',
      title: 'سياسة الخصوصية',
      intro:
        'تعالج Leopardo بيانات موارد بشرية حساسة لمساعدة الشركات على إدارة الحضور والرواتب والإجازات وسير العمل الميداني. توضح هذه الصفحة نهجنا في الحماية والشفافية والتحكم.',
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
            'تطبق Leopardo عزل المستأجرين والتحكم في الوصول حسب الأدوار وتسجيل الوصول إلى البيانات الحساسة ومبدأ أقل صلاحية.',
            'يبقى العملاء مسؤولين عن إعداد المستخدمين والسياسات الداخلية والتحقق من الالتزامات القانونية المحلية.',
          ],
        },
        {
          title: 'المسؤول عن المعالجة',
          body: [
            'تنشر Leopardo المنصة وتستضيفها. بالنسبة لبيانات موظفيكم (الحضور والإجازات والرواتب ووثائق الموارد البشرية)، فإن شركتكم هي المسؤولة عن المعالجة، وتعمل Leopardo كمُعالج بناءً على تعليماتكم.',
            'أما بيانات الموقع والعلاقة التجارية فالمسؤولة عن معالجتها هي Leopardo.',
          ],
        },
        {
          title: 'الأساس القانوني',
          body: [
            'تستند كل معالجة إلى أساس محدد: تنفيذ العقد (تقديم الخدمة)، أو التزام قانوني (حفظ وثائق الرواتب والوثائق الاجتماعية)، أو مصلحة مشروعة (الأمان ومنع الاحتيال والدعم)، أو الموافقة (قياس الزيارات والتخصيص التسويقي والبيانات البيومترية).',
          ],
        },
        {
          title: 'مدد الحفظ',
          body: [
            'بيانات الحساب والإنتاج: طوال مدة العقد ثم 12 شهراً. السجلات التقنية: 12 شهراً. وثائق الرواتب والوثائق الاجتماعية: المدد القانونية المعمول بها. قياس الزيارات: 14 شهراً كحد أقصى. اختيار الموافقة: 6 أشهر ثم يُعاد السؤال.',
          ],
        },
        {
          title: 'الجهات المتلقية والمُعالجون الفرعيون',
          body: [
            'الوصول الداخلي مقصور على فرق الدعم والأمان والهندسة وفق مبدأ الحاجة إلى المعرفة.',
            'المُعالجون الفرعيون: الاستضافة (بنية تحتية داخل الاتحاد الأوروبي)، ومراقبة أخطاء التطبيق، وقياس الزيارات (Google Analytics 4 وMixpanel) بعد موافقتكم الصريحة فقط. القائمة المحدّثة متاحة عند الطلب.',
          ],
        },
        {
          title: 'النقل خارج الاتحاد الأوروبي',
          body: [
            'تُستضاف بيانات الإنتاج داخل الاتحاد الأوروبي. وقد يعالج بعض المزوّدين بيانات خارج الاتحاد؛ وتُؤطَّر هذه عمليات النقل بضمانات مناسبة (الشروط التعاقدية القياسية). وقياس الزيارات، الذي قد يتضمن مثل هذا النقل، معطّل افتراضياً.',
          ],
        },
        {
          title: 'ملفات تعريف الارتباط والقياس',
          body: [
            'الملفات الضرورية (الجلسة واللغة والأمان) مفعّلة دائماً. أما قياس الزيارات والتخصيص التسويقي فلا يُفعّلان إلا بموافقتكم، ويمكنكم تغيير اختياركم في أي وقت عبر «إدارة ملفات تعريف الارتباط» أسفل الصفحة. يُحفظ الاختيار ستة أشهر.',
          ],
        },
        {
          title: 'ممارسة الحقوق والشكاوى',
          body: [
            'يمكنكم ممارسة حقوقكم (الوصول والتصحيح والمحو والتقييد والاعتراض والنقل) بمراسلة privacy@leopardo-rh.com، ونرد خلال شهر واحد. كما يمكنكم اللجوء إلى هيئة حماية البيانات المختصة: في الجزائر السلطة الوطنية لحماية المعطيات الشخصية (ANPDP)، وفي الاتحاد الأوروبي هيئة بلد إقامتكم.',
          ],
        },
        {
          title: 'انتهاكات البيانات',
          body: [
            'في حال وقوع انتهاك قد يُلحق خطراً مرتفعاً بحقوقكم، نُبلغ العملاء المعنيين والهيئة المختصة عند اقتضاء القانون ذلك، خلال المدد المقررة.',
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
        'تنظم هذه الشروط استخدام Leopardo من قبل الشركات والمسؤولين والمديرين والموظفين وأجهزة الكشك والمكاملين المعتمدين.',
      updatedAt: 'آخر تحديث: 14 مايو 2026',
      backLabel: 'العودة إلى الصفحة الرئيسية',
      languageLabel: 'لغة الوثيقة',
      sections: [
        {
          title: 'الوصول إلى المنصة',
          body: [
            'يقتصر الوصول على المستخدمين المصرح لهم من شركة عميلة أو من Leopardo لإدارة المنصة.',
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
            'قد تطور Leopardo الوحدات وواجهات API والواجهات لتحسين الأمان والأداء وقيمة المنتج.',
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
      legal: {
      eyebrow: 'معلومات قانونية',
      title: 'الإشعارات القانونية',
      intro:
        'معلومات عن ناشر الموقع والاستضافة وملكية المحتوى.',
      updatedAt: 'آخر تحديث: 16 سبتمبر 2026',
      backLabel: 'العودة إلى الرئيسية',
      languageLabel: 'لغة المستند',
      sections: [
        {
          title: 'ناشر الموقع',
          body: [
            'يُنشر موقع ومنصة Leopardo بواسطة Leopardo (الجزائر العاصمة، الجزائر).',
            'التواصل: contact@leopardo-rh.com.',
          ],
        },
        {
          title: 'المعرّفات القانونية',
          body: [
            'تُقدَّم معرّفات الناشر (الاسم القانوني والعنوان ورقم التسجيل والمعرّف الضريبي) واسم المستضيف عند الطلب عبر contact@leopardo-rh.com، إلى حين نشرها في هذه الصفحة.',
          ],
        },
        {
          title: 'الاستضافة',
          body: [
            'تقع بنية الاستضافة داخل الاتحاد الأوروبي، ولا تُستضاف بيانات الإنتاج خارجه.',
          ],
        },
        {
          title: 'الملكية الفكرية',
          body: [
            'العلامة والشيفرة والواجهات ومحتويات الموقع محمية. ويُمنع أي نسخ أو إعادة استخدام دون إذن كتابي مسبق.',
          ],
        },
        {
          title: 'البيانات الشخصية',
          body: [
            'تُوضّح سياسة الخصوصية كيفية معالجة البيانات الشخصية: الأغراض والأسس القانونية ومدد الحفظ والجهات المتلقية وسبل ممارسة حقوقكم.',
          ],
        },
      ],
      contact: {
        title: 'التواصل',
        body: 'لأي سؤال حول هذا الإشعار، راسلونا.',
        email: 'contact@leopardo-rh.com',
      },
    },
},
}

export function getLegalPageCopy(locale: AppLocale, page: LegalPageKind): LegalPageCopy {
  return legalPages[locale][page] ?? legalPages.fr[page]
}
