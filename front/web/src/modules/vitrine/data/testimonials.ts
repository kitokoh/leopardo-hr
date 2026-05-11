import type { AppLocale } from '@/lib/i18n'

export type Testimonial = {
  name: string
  role: string
  company: string
  avatar: string
  content: string
  rating: number
}

const testimonialsByLocale: Record<AppLocale, Testimonial[]> = {
  fr: [
    {
      name: 'Amina Diallo',
      role: 'DRH',
      company: 'TechAfrika',
      avatar: 'AD',
      content: 'Leopardo RH a transforme notre gestion du personnel. Le gain de temps est phenomenal, surtout sur la paie et le pointage.',
      rating: 5,
    },
    {
      name: 'Mehdi Benali',
      role: 'CEO',
      company: 'Atlas Digital',
      avatar: 'MB',
      content: "L'interface est intuitive et le support est excellent. Nos equipes l'ont adoptee tres vite.",
      rating: 5,
    },
    {
      name: 'Fatou Sow',
      role: 'Responsable RH',
      company: 'SenLogistics',
      avatar: 'FS',
      content: 'Le pointage biometrisque et les anomalies manager ont change notre discipline terrain.',
      rating: 5,
    },
    {
      name: 'Ibrahim Toure',
      role: 'Directeur Operations',
      company: 'BuildAfrica',
      avatar: 'IT',
      content: 'Le mode hors ligne est crucial pour nos sites. La synchronisation automatique nous rassure enormement.',
      rating: 5,
    },
  ],
  en: [
    {
      name: 'Amina Diallo',
      role: 'HR Director',
      company: 'TechAfrika',
      avatar: 'AD',
      content: 'Leopardo RH transformed our people operations. The time savings on payroll and attendance are dramatic.',
      rating: 5,
    },
    {
      name: 'Mehdi Benali',
      role: 'CEO',
      company: 'Atlas Digital',
      avatar: 'MB',
      content: 'The product is intuitive and the support team is sharp. Adoption across the company was extremely fast.',
      rating: 5,
    },
    {
      name: 'Fatou Sow',
      role: 'HR Manager',
      company: 'SenLogistics',
      avatar: 'FS',
      content: 'Biometric attendance and anomaly tracking gave our managers the field visibility they were missing.',
      rating: 5,
    },
    {
      name: 'Ibrahim Toure',
      role: 'Operations Director',
      company: 'BuildAfrica',
      avatar: 'IT',
      content: 'Offline mode is essential for our sites. Automatic sync removed a lot of operational stress.',
      rating: 5,
    },
  ],
  tr: [
    {
      name: 'Amina Diallo',
      role: 'IK Direktoru',
      company: 'TechAfrika',
      avatar: 'AD',
      content: 'Leopardo RH, personel operasyonlarimizi donusturdu. Bordro ve devam takibinde buyuk zaman kazandik.',
      rating: 5,
    },
    {
      name: 'Mehdi Benali',
      role: 'CEO',
      company: 'Atlas Digital',
      avatar: 'MB',
      content: 'Arayuz cok sezgisel ve destek ekibi cok guclu. Sirket genelinde benimsenmesi hizli oldu.',
      rating: 5,
    },
    {
      name: 'Fatou Sow',
      role: 'IK Muduru',
      company: 'SenLogistics',
      avatar: 'FS',
      content: 'Biyometrik takip ve anomali gorunurlugu saha disiplinimizi ciddi bicimde iyilestirdi.',
      rating: 5,
    },
    {
      name: 'Ibrahim Toure',
      role: 'Operasyon Direktoru',
      company: 'BuildAfrica',
      avatar: 'IT',
      content: 'Cevrimdisi mod sahalarimiz icin kritik. Otomatik esitleme buyuk rahatlik sagliyor.',
      rating: 5,
    },
  ],
  ar: [
    {
      name: 'Amina Diallo',
      role: 'مديرة الموارد البشرية',
      company: 'TechAfrika',
      avatar: 'AD',
      content: 'Leopardo RH غير طريقة ادارتنا للموظفين، خاصة في الرواتب والحضور.',
      rating: 5,
    },
    {
      name: 'Mehdi Benali',
      role: 'الرئيس التنفيذي',
      company: 'Atlas Digital',
      avatar: 'MB',
      content: 'الواجهة واضحة جدا وفريق الدعم ممتاز، وتم اعتماد النظام بسرعة داخل الشركة.',
      rating: 5,
    },
    {
      name: 'Fatou Sow',
      role: 'مسؤولة الموارد البشرية',
      company: 'SenLogistics',
      avatar: 'FS',
      content: 'الحضور البيومتري ومتابعة الانحرافات اعطت المدراء رؤية ميدانية فورية.',
      rating: 5,
    },
    {
      name: 'Ibrahim Toure',
      role: 'مدير العمليات',
      company: 'BuildAfrica',
      avatar: 'IT',
      content: 'الوضع دون اتصال ضروري لمواقعنا، والمزامنة التلقائية خففت الضغط التشغيلي كثيرا.',
      rating: 5,
    },
  ],
}

export function getTestimonials(locale: AppLocale): Testimonial[] {
  return testimonialsByLocale[locale] ?? testimonialsByLocale.fr
}
