export type FaqItem = {
  question: string;
  answer: string;
};

export const faqItems: FaqItem[] = [
  {
    question: 'Combien de temps faut-il pour deployer Leopardo RH ?',
    answer: 'Le deploiement standard prend moins de 24 heures. Votre equipe peut commencer a utiliser la plateforme des le premier jour avec notre onboarding guide.',
  },
  {
    question: 'Est-ce que Leopardo RH fonctionne hors-ligne ?',
    answer: 'Oui, notre application mobile dispose d\'un mode offline complet. Les pointages, demandes d\'absence et consultations fonctionnent sans connexion. Les donnees se synchronisent automatiquement.',
  },
  {
    question: 'Quelles methodes de pointage sont supportees ?',
    answer: 'Nous supportons le pointage par biometrie faciale, empreinte digitale, NFC, QR code, geolocalisation, et les bornes ZKTeco. Tous les modes sont combinables.',
  },
  {
    question: 'Mes donnees sont-elles securisees ?',
    answer: 'Absolument. Chiffrement AES-256 au repos et TLS 1.3 en transit, authentification 2FA, audit trail complet, hebergement certifie SOC2, et conformite RGPD.',
  },
  {
    question: 'Puis-je migrer depuis un autre outil RH ?',
    answer: 'Oui, nous proposons un service de migration gratuit pour les plans Business et Enterprise. Notre equipe vous accompagne pour importer vos donnees existantes sans interruption.',
  },
  {
    question: 'Y a-t-il un engagement minimum ?',
    answer: 'Non, tous nos plans sont sans engagement. Vous pouvez annuler a tout moment. Le plan Enterprise peut inclure un engagement annuel avec une remise significative.',
  },
];
