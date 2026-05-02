export type Testimonial = {
  name: string;
  role: string;
  company: string;
  avatar: string;
  content: string;
  rating: number;
};

export const testimonials: Testimonial[] = [
  {
    name: 'Amina Diallo',
    role: 'DRH',
    company: 'TechAfrika',
    avatar: 'AD',
    content: 'Leopardo RH a transforme notre gestion du personnel. Le gain de temps est phenomenal, surtout avec la paie automatisee.',
    rating: 5,
  },
  {
    name: 'Mehdi Benali',
    role: 'CEO',
    company: 'Atlas Digital',
    avatar: 'MB',
    content: 'L\'interface est intuitive et le support est exceptionnel. Nos 200 employes l\'ont adopte en moins d\'une semaine.',
    rating: 5,
  },
  {
    name: 'Fatou Sow',
    role: 'Responsable RH',
    company: 'SenLogistics',
    avatar: 'FS',
    content: 'La fonctionnalite de pointage biometrique est un game changer. Plus aucune fraude possible et nos employes adorent.',
    rating: 5,
  },
  {
    name: 'Ibrahim Toure',
    role: 'Directeur Operations',
    company: 'BuildAfrica',
    avatar: 'IT',
    content: 'Le mode hors-ligne est crucial pour nos sites de construction. Leopardo synchronise tout automatiquement des que la connexion revient.',
    rating: 5,
  },
];
