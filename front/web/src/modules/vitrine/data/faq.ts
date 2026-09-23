import type { AppLocale } from '@/lib/i18n'

export type FaqItem = {
  question: string
  answer: string
}

const faqByLocale: Record<AppLocale, FaqItem[]> = {
  fr: [
    {
      question: 'Combien de temps faut-il pour déployer Leopardo ?',
      answer: "Le déploiement standard prend moins de 24 heures. Votre équipe peut commencer à utiliser la plateforme dès le premier jour avec notre guide de prise en main.",
    },
    {
      question: 'Est-ce que Leopardo fonctionne hors ligne ?',
      answer: "Oui, l'application mobile dispose d'un mode hors ligne complet. Les pointages et demandes se synchronisent automatiquement dès que la connexion revient.",
    },
    {
      question: 'Quelles méthodes de pointage sont supportées ?',
      answer: 'Nous supportons la biométrie faciale, les empreintes, NFC, QR code, géolocalisation et les bornes ZKTeco.',
    },
    {
      // #8076 — reformulée : la question hébergement arrive dans toutes les
      // objections d'une PME non technique (benchmark Frappe/BambooHR).
      question: 'Mes données sont-elles en sécurité ? Où sont-elles hébergées ?',
      answer: "Oui. Chiffrement AES-256 au repos, TLS 1.3 en transit, 2FA et journal d'audit complet. En cloud, vos données sont hébergées en Europe ; en auto-hébergement, elles restent sur votre propre serveur, sous votre contrôle total.",
    },
    {
      question: 'Puis-je migrer depuis un autre outil RH ?',
      answer: 'Oui, nous proposons un accompagnement de migration pour importer vos données sans interrompre vos opérations.',
    },
    {
      question: 'Y a-t-il un engagement minimum ?',
      answer: 'Non, les plans restent flexibles. Un engagement annuel peut être proposé pour les contrats enterprise.',
    },
    // #8076 — FAQ de réassurance self-host / PME non techniques (benchmark :
    // Frappe sur coût vs propriétaire, PayFit « pas de mauvaises questions »).
    {
      question: 'Faut-il une équipe IT pour installer Leopardo ?',
      answer: "Non. En cloud, il n'y a rien à installer : votre espace est prêt en quelques minutes et un guide de prise en main vous accompagne. En auto-hébergement, le déploiement se fait avec Docker (docker-compose) en suivant la documentation publique — des compétences de base en administration serveur suffisent.",
    },
    {
      question: 'Cloud ou mon propre serveur — que choisir ?',
      answer: "Le cloud si vous voulez zéro maintenance : mises à jour, sauvegardes et sécurité sont gérées pour vous. L'auto-hébergement si vous voulez garder vos données chez vous : Leopardo est 100 % open source (licence MIT), l'installation est gratuite et vous gardez le contrôle total — en échange, vous administrez sauvegardes et mises à jour.",
    },
    {
      question: 'Puis-je migrer depuis Excel ou Sage ?',
      answer: "Oui. Employés, clients et données de paie s'importent par CSV depuis Excel ou l'export de votre outil actuel, et un accompagnement de migration est proposé pour ne pas interrompre vos opérations.",
    },
    {
      question: "Que se passe-t-il si j'arrête ?",
      answer: "Vous repartez avec vos données : elles sont exportables en CSV à tout moment, sans enfermement. Leopardo étant open source, vous pouvez même poursuivre en auto-hébergement après un abonnement cloud.",
    },
    {
      question: 'Combien coûte vraiment Leopardo (cloud ou auto-hébergement) ?',
      answer: "En cloud : le plan Free est gratuit (5 employés), Pilot est à 29 €/mois (290 €/an en facturation annuelle) et Operations à 79 €/mois (790 €/an), sans frais cachés. En auto-hébergement : 0 € de licence — vous ne payez que votre serveur.",
    },
    {
      // Intention paie locale / mobile money AFFICHÉE mais honnête (#8076) :
      // « bientôt » explicite, aucune capacité inexistante présentée comme dispo.
      question: 'La paie locale et le mobile money sont-ils gérés ?',
      answer: "Le catalogue de paie couvre 21 pays (Afrique de l'Ouest et centrale, Europe, Turquie, Amérique du Nord). Les paiements par mobile money (Orange Money, Wave, MTN MoMo) ne sont pas encore disponibles : l'intégration est à l'étude dans la roadmap publique sur GitHub, sans date promise.",
    },
  ],
  en: [
    {
      question: 'How long does Leopardo take to deploy?',
      answer: 'A standard rollout takes less than 24 hours. Most teams start operating on day one with guided onboarding.',
    },
    {
      question: 'Does Leopardo work offline?',
      answer: 'Yes. Mobile attendance and requests keep working offline and synchronize automatically when connectivity returns.',
    },
    {
      question: 'Which attendance methods are supported?',
      answer: 'We support facial biometrics, fingerprint, NFC, QR code, geolocation, and ZKTeco devices.',
    },
    {
      question: 'Is our data secure? Where is it hosted?',
      answer: 'Yes. AES-256 at rest, TLS 1.3 in transit, 2FA and a full audit trail. In the cloud, your data is hosted in Europe; when self-hosting, it stays on your own server, under your full control.',
    },
    {
      question: 'Can we migrate from another HR tool?',
      answer: 'Yes. Our onboarding team can help import existing employee, attendance, and payroll data safely.',
    },
    {
      question: 'Is there a minimum commitment?',
      answer: 'No mandatory lock-in for standard plans. Annual enterprise contracts remain available where needed.',
    },
    {
      question: 'Do we need an IT team to install Leopardo?',
      answer: 'No. In the cloud there is nothing to install: your workspace is ready in minutes with guided onboarding. When self-hosting, deployment uses Docker (docker-compose) following the public documentation — basic server administration skills are enough.',
    },
    {
      question: 'Cloud or my own server — which should I choose?',
      answer: 'Choose the cloud for zero maintenance: updates, backups and security are handled for you. Choose self-hosting to keep your data in-house: Leopardo is 100% open source (MIT license), installation is free and you keep full control — in exchange, you run backups and updates yourself.',
    },
    {
      question: 'Can I migrate from Excel or Sage?',
      answer: 'Yes. Employees, customers and payroll data can be imported from CSV files exported by Excel or your current tool, and migration support is available so operations are not interrupted.',
    },
    {
      question: 'What happens if I cancel?',
      answer: 'You leave with your data: it can be exported to CSV at any time, with no lock-in. Because Leopardo is open source, you can even keep running it self-hosted after a cloud subscription.',
    },
    {
      question: 'What does Leopardo really cost (cloud vs self-hosting)?',
      answer: 'In the cloud: the Free plan is free (5 employees), Pilot is €29/month (€290/year with annual billing) and Operations is €79/month (€790/year), with no hidden fees. Self-hosted: €0 of license fees — you only pay for your server.',
    },
    {
      question: 'Are local payroll and mobile money supported?',
      answer: 'The payroll catalog covers 21 countries (West and Central Africa, Europe, Turkey, North America). Mobile money payouts (Orange Money, Wave, MTN MoMo) are not available yet: integration is under consideration on the public GitHub roadmap, with no promised date.',
    },
  ],
  tr: [
    {
      question: 'Leopardo ne kadar hizli devreye alinir?',
      answer: 'Standart kurulum 24 saatten kisa surer. Cogu ekip ilk gunden itibaren kullanima baslar.',
    },
    {
      question: 'Leopardo cevrimdisi calisir mi?',
      answer: 'Evet. Mobil takip ve talepler cevrimdisi devam eder, baglanti gelince otomatik eslesir.',
    },
    {
      question: 'Hangi devam takip yontemleri destekleniyor?',
      answer: 'Yuz biyometrisi, parmak izi, NFC, QR kod, konum ve ZKTeco cihazlari desteklenir.',
    },
    {
      question: 'Verilerimiz guvende mi? Nerede barindiriliyor?',
      answer: 'Evet. Beklemede AES-256, aktarimda TLS 1.3, 2FA ve tam denetim kaydi. Bulutta verileriniz Avrupada barindirilir; kendi sunucunuzda kurulumda ise veriler tamamen sizin kontrolunuzde kalir.',
    },
    {
      question: 'Baska IK araclarindan gecis yapabilir miyiz?',
      answer: 'Evet. Mevcut personel, devam ve bordro verilerini tasimak icin destek veriyoruz.',
    },
    {
      question: 'Asgari taahhut var mi?',
      answer: 'Standart planlarda zorunlu taahhut yoktur. Gerektiginde yillik enterprise sozlesmesi yapilabilir.',
    },
    {
      question: 'Leopardo kurulumu icin BT ekibi gerekir mi?',
      answer: 'Hayir. Bulutta kurulum gerekmez: calisma alaniniz dakikalar icinde hazirdir ve rehberli baslangic size eslik eder. Kendi sunucunuzda kurulum, herkese acik dokumantasyon izlenerek Docker (docker-compose) ile yapilir — temel sunucu yonetimi bilgisi yeterlidir.',
    },
    {
      question: 'Bulut mu, kendi sunucum mu — hangisini secmeliyim?',
      answer: 'Sifir bakim istiyorsaniz bulutu secin: guncellemeler, yedekler ve guvenlik sizin icin yonetilir. Verilerinizi kendi bunyesinde tutmak istiyorsaniz kendi sunucunuzu secin: Leopardo %100 acik kaynak (MIT lisansi), kurulum ucretsizdir ve tam kontrol sizde kalir — karsiliginda yedek ve guncellemeleri siz yonetirsiniz.',
    },
    {
      question: 'Excel veya Sage uzerinden gecebilir miyim?',
      answer: 'Evet. Calisanlar, musteriler ve bordro verileri Excel veya mevcut aracinizin disa aktarimi ile CSV uzerinden ice aktarilir; operasyonlariniz aksamadan gecis destegi sunulur.',
    },
    {
      question: 'Vazgecersem ne olur?',
      answer: 'Verileriniz sizinle gider: istediginiz an CSV olarak disa aktarilabilir, kilitlenme yoktur. Leopardo acik kaynak oldugu icin bulut aboneliginden sonra kendi sunucunuzda bile calistirmaya devam edebilirsiniz.',
    },
    {
      question: 'Leopardo gercekte kaca mal olur (bulut / kendi sunucum)?',
      answer: 'Bulutta: Free plani ucretsizdir (5 calisan), Pilot 29 €/ay (yillik faturalamada 290 €/yil) ve Operations 79 €/ay (790 €/yil), gizli ucret yoktur. Kendi sunucunuzda: 0 € lisans — yalnizca sunucu maliyetiniz vardir.',
    },
    {
      question: 'Yerel bordro ve mobil para destekleniyor mu?',
      answer: 'Bordro katalogu 21 ulkeyi kapsar (Bati ve Orta Afrika, Avrupa, Turkiye, Kuzey Amerika). Mobil para ile odeme (Orange Money, Wave, MTN MoMo) henuz mevcut degil: entegrasyon GitHub uzerindeki herkese acik yol haritasinda degerlendiriliyor, soz verilen bir tarih yok.',
    },
  ],
  ar: [
    {
      question: 'كم يستغرق تشغيل Leopardo؟',
      answer: 'النشر القياسي يستغرق اقل من 24 ساعة، ويمكن للفريق البدء في الاستخدام من اليوم الاول.',
    },
    {
      question: 'هل يعمل Leopardo دون اتصال؟',
      answer: 'نعم. الحضور والطلبات عبر الجوال تستمر دون اتصال ثم تتزامن تلقائيا عند عودة الشبكة.',
    },
    {
      question: 'ما طرق الحضور المدعومة؟',
      answer: 'ندعم القياسات الحيوية للوجه والبصمة و NFC و QR وتحديد الموقع واجهزة ZKTeco.',
    },
    {
      question: 'هل بياناتنا آمنة؟ وأين تُستضاف؟',
      answer: 'نعم. تشفير AES-256 عند التخزين و TLS 1.3 عند النقل و 2FA وسجل تدقيق كامل. في السحابة تُستضاف بياناتكم في أوروبا؛ وعند الاستضافة الذاتية تبقى على خادمكم الخاص تحت سيطرتكم الكاملة.',
    },
    {
      question: 'هل يمكننا الانتقال من نظام موارد بشرية اخر؟',
      answer: 'نعم. فريقنا يساعد في ترحيل بيانات الموظفين والحضور والرواتب بشكل آمن.',
    },
    {
      question: 'هل يوجد التزام ادنى؟',
      answer: 'لا يوجد التزام اجباري في الخطط القياسية، مع امكانية العقود السنوية للحسابات المؤسسية.',
    },
    {
      question: 'هل نحتاج فريق تقنية معلومات لتثبيت Leopardo؟',
      answer: 'لا. في السحابة لا يوجد ما يُثبَّت: مساحة عملكم جاهزة في دقائق مع دليل بدء مصاحب. وعند الاستضافة الذاتية يتم النشر عبر Docker (docker-compose) باتباع التوثيق العام — تكفي مهارات أساسية في إدارة الخوادم.',
    },
    {
      question: 'السحابة أم خادمي الخاص — ماذا أختار؟',
      answer: 'اختاروا السحابة لصيانة صفرية: التحديثات والنسخ الاحتياطية والأمن مُدارة لصالحكم. واختاروا الاستضافة الذاتية للاحتفاظ ببياناتكم لديكم: Leopardo مفتوح المصدر 100% (رخصة MIT)، التثبيت مجاني وتحتفظون بالسيطرة الكاملة — مقابل إدارتكم للنسخ والتحديثات.',
    },
    {
      question: 'هل يمكنني الانتقال من Excel أو Sage؟',
      answer: 'نعم. يمكن استيراد الموظفين والعملاء وبيانات الرواتب عبر CSV من Excel أو من تصدير أداتكم الحالية، مع دعم للترحيل حتى لا تتعطل عملياتكم.',
    },
    {
      question: 'ماذا يحدث إذا توقفت عن الاشتراك؟',
      answer: 'تغادرون وبياناتكم معكم: يمكن تصديرها إلى CSV في أي وقت ودون أي احتجاز. ولأن Leopardo مفتوح المصدر، يمكنكم حتى مواصلة تشغيله ذاتياً بعد اشتراك سحابي.',
    },
    {
      question: 'كم تبلغ التكلفة الحقيقية لـ Leopardo (السحابة أم الاستضافة الذاتية)؟',
      answer: 'في السحابة: خطة Free مجانية (5 موظفين)، و Pilot بـ 29 €/شهر (290 €/سنة عند الفوترة السنوية) و Operations بـ 79 €/شهر (790 €/سنة)، دون رسوم خفية. في الاستضافة الذاتية: 0 € ترخيصاً — تدفعون فقط تكلفة الخادم.',
    },
    {
      question: 'هل تُدار الرواتب المحلية والأموال عبر الجوال؟',
      answer: 'يغطي كتالوج الرواتب 21 دولة (غرب ووسط أفريقيا وأوروبا وتركيا وأمريكا الشمالية). مدفوعات الأموال عبر الجوال (Orange Money و Wave و MTN MoMo) غير متاحة بعد: التكامل قيد الدراسة في خارطة الطريق العامة على GitHub دون وعد بتاريخ.',
    },
  ],
}

export function getFaqItems(locale: AppLocale): FaqItem[] {
  return faqByLocale[locale] ?? faqByLocale.fr
}
