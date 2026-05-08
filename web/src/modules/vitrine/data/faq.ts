import type { AppLocale } from '@/lib/i18n'

export type FaqItem = {
  question: string
  answer: string
}

const faqByLocale: Record<AppLocale, FaqItem[]> = {
  fr: [
    {
      question: 'Combien de temps faut-il pour deployer Leopardo RH ?',
      answer: "Le deploiement standard prend moins de 24 heures. Votre equipe peut commencer a utiliser la plateforme des le premier jour avec notre onboarding guide.",
    },
    {
      question: 'Est-ce que Leopardo RH fonctionne hors ligne ?',
      answer: "Oui, l'application mobile dispose d'un mode offline complet. Les pointages et demandes se synchronisent automatiquement des que la connexion revient.",
    },
    {
      question: 'Quelles methodes de pointage sont supportees ?',
      answer: 'Nous supportons la biometrie faciale, les empreintes, NFC, QR code, geolocalisation et les bornes ZKTeco.',
    },
    {
      question: 'Mes donnees sont-elles securisees ?',
      answer: 'Oui. Chiffrement AES-256 au repos, TLS 1.3 en transit, 2FA, audit trail complet et hebergement conforme.',
    },
    {
      question: 'Puis-je migrer depuis un autre outil RH ?',
      answer: 'Oui, nous proposons un accompagnement de migration pour importer vos donnees sans interrompre vos operations.',
    },
    {
      question: 'Y a-t-il un engagement minimum ?',
      answer: 'Non, les plans restent flexibles. Un engagement annuel peut etre propose pour les contrats enterprise.',
    },
  ],
  en: [
    {
      question: 'How long does Leopardo RH take to deploy?',
      answer: 'A standard rollout takes less than 24 hours. Most teams start operating on day one with guided onboarding.',
    },
    {
      question: 'Does Leopardo RH work offline?',
      answer: 'Yes. Mobile attendance and requests keep working offline and synchronize automatically when connectivity returns.',
    },
    {
      question: 'Which attendance methods are supported?',
      answer: 'We support facial biometrics, fingerprint, NFC, QR code, geolocation, and ZKTeco devices.',
    },
    {
      question: 'Is our data secure?',
      answer: 'Yes. AES-256 at rest, TLS 1.3 in transit, 2FA, full audit trails, and hardened hosting are part of the baseline.',
    },
    {
      question: 'Can we migrate from another HR tool?',
      answer: 'Yes. Our onboarding team can help import existing employee, attendance, and payroll data safely.',
    },
    {
      question: 'Is there a minimum commitment?',
      answer: 'No mandatory lock-in for standard plans. Annual enterprise contracts remain available where needed.',
    },
  ],
  tr: [
    {
      question: 'Leopardo RH ne kadar hizli devreye alinir?',
      answer: 'Standart kurulum 24 saatten kisa surer. Cogu ekip ilk gunden itibaren kullanima baslar.',
    },
    {
      question: 'Leopardo RH cevrimdisi calisir mi?',
      answer: 'Evet. Mobil takip ve talepler cevrimdisi devam eder, baglanti gelince otomatik eslesir.',
    },
    {
      question: 'Hangi devam takip yontemleri destekleniyor?',
      answer: 'Yuz biyometrisi, parmak izi, NFC, QR kod, konum ve ZKTeco cihazlari desteklenir.',
    },
    {
      question: 'Verilerimiz guvende mi?',
      answer: 'Evet. AES-256 sifreleme, TLS 1.3, 2FA, denetim kayitlari ve guclu barindirma temel standarttir.',
    },
    {
      question: 'Baska IK araclarindan gecis yapabilir miyiz?',
      answer: 'Evet. Mevcut personel, devam ve bordro verilerini tasimak icin destek veriyoruz.',
    },
    {
      question: 'Asgari taahhut var mi?',
      answer: 'Standart planlarda zorunlu taahhut yoktur. Gerektiginde yillik enterprise sozlesmesi yapilabilir.',
    },
  ],
  ar: [
    {
      question: 'كم يستغرق تشغيل Leopardo RH؟',
      answer: 'النشر القياسي يستغرق اقل من 24 ساعة، ويمكن للفريق البدء في الاستخدام من اليوم الاول.',
    },
    {
      question: 'هل يعمل Leopardo RH دون اتصال؟',
      answer: 'نعم. الحضور والطلبات عبر الجوال تستمر دون اتصال ثم تتزامن تلقائيا عند عودة الشبكة.',
    },
    {
      question: 'ما طرق الحضور المدعومة؟',
      answer: 'ندعم القياسات الحيوية للوجه والبصمة و NFC و QR وتحديد الموقع واجهزة ZKTeco.',
    },
    {
      question: 'هل بياناتنا آمنة؟',
      answer: 'نعم. تشفير AES-256 و TLS 1.3 و 2FA وسجل تدقيق كامل واستضافة قوية.',
    },
    {
      question: 'هل يمكننا الانتقال من نظام موارد بشرية اخر؟',
      answer: 'نعم. فريقنا يساعد في ترحيل بيانات الموظفين والحضور والرواتب بشكل آمن.',
    },
    {
      question: 'هل يوجد التزام ادنى؟',
      answer: 'لا يوجد التزام اجباري في الخطط القياسية، مع امكانية العقود السنوية للحسابات المؤسسية.',
    },
  ],
}

export function getFaqItems(locale: AppLocale): FaqItem[] {
  return faqByLocale[locale] ?? faqByLocale.fr
}
