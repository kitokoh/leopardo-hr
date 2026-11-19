import {
  employmentType,
  isContractType,
  isFullyRemote,
  isOpen,
  isRemotePolicy,
  salaryLabel,
  type JobOffer,
} from '../careers/domain/job-offer';
import {
  isAcceptableResume,
  isActiveApplication,
  isApplicationStatus,
  isValidEmail,
  validateApplicationDraft,
  type ApplicationDraft,
} from '../careers/domain/application';
import { toJobOffer } from '../careers/infrastructure/careers-gateway';
import { jobPostingJsonLd } from '../careers/application/load-job-detail';

/**
 * #9629 — verticale Emploi.
 *
 * Ces tests verrouillent ce qui décide de ce qu'un candidat voit et peut faire :
 * une offre sans salaire n'affiche pas « 0 € », une offre agrégée ne se
 * candidate pas sur place, une candidature sans consentement est refusée avant
 * l'envoi, et le JSON-LD porte les valeurs schema.org attendues par Google Jobs.
 */

function offer(overrides: Partial<JobOffer> = {}): JobOffer {
  return {
    id: 1,
    title: 'Développeur',
    description: 'Un poste',
    companySlug: 'acme',
    companyName: 'ACME',
    location: 'Lyon',
    contractType: 'cdi',
    remotePolicy: 'hybrid',
    salaryMin: 40000,
    salaryMax: 50000,
    currency: 'EUR',
    publishedAt: '2026-11-01',
    closesAt: null,
    external: false,
    applyUrl: null,
    ...overrides,
  };
}

describe('offre d’emploi (#9629)', () => {
  it('reconnaît les codes de contrat et de télétravail servis par l’API', () => {
    expect(isContractType('cdi')).toBe(true);
    expect(isContractType('CDI')).toBe(false);
    expect(isRemotePolicy('remote')).toBe(true);
    expect(isRemotePolicy('full')).toBe(false);
  });

  it('une offre sans échéance est ouverte ; une offre échue ne l’est plus', () => {
    const now = new Date('2026-11-19T00:00:00Z');

    expect(isOpen(offer({ closesAt: null }), now)).toBe(true);
    expect(isOpen(offer({ closesAt: '2026-12-01' }), now)).toBe(true);
    expect(isOpen(offer({ closesAt: '2026-11-01' }), now)).toBe(false);
  });

  it('n’affiche pas « 0 € » quand le salaire est inconnu', () => {
    expect(salaryLabel(offer({ salaryMin: null, salaryMax: null }))).toBeNull();
    expect(salaryLabel(offer({ salaryMin: 40000, salaryMax: null }))).toContain('40');
    expect(salaryLabel(offer())).toContain('50');
  });

  it('traduit les codes internes en types schema.org', () => {
    expect(employmentType('cdi')).toBe('FULL_TIME');
    expect(employmentType('cdd')).toBe('CONTRACTOR');
    expect(employmentType('stage')).toBe('INTERN');
    expect(employmentType('freelance')).toBe('TEMPORARY');
    expect(employmentType(null)).toBe('FULL_TIME');
  });

  it('détecte le télétravail total', () => {
    expect(isFullyRemote(offer({ remotePolicy: 'remote' }))).toBe(true);
    expect(isFullyRemote(offer({ remotePolicy: 'hybrid' }))).toBe(false);
  });
});

describe('mapping du contrat public (#9629)', () => {
  it('marque une offre agrégée par la présence d’une URL de candidature', () => {
    const internal = toJobOffer({ id: 1, title: 'A' }, 'acme', 'ACME');
    const external = toJobOffer(
      { id: 2, title: 'B', apply_url: 'https://partner.test/job/2' },
      'acme',
      'ACME',
    );

    expect(internal.external).toBe(false);
    expect(internal.applyUrl).toBeNull();
    expect(external.external).toBe(true);
    expect(external.applyUrl).toBe('https://partner.test/job/2');
  });

  it('lit les deux familles de champs de salaire', () => {
    expect(toJobOffer({ salary_range_min: 100, salary_range_max: 200 }, 'a', 'A').salaryMin).toBe(100);
    expect(toJobOffer({ salary_min: 300 }, 'a', 'A').salaryMin).toBe(300);
  });

  it('écarte un code de contrat inconnu plutôt que de l’afficher brut', () => {
    expect(toJobOffer({ contract_type: 'weird' }, 'a', 'A').contractType).toBeNull();
  });
});

describe('candidature (#9629)', () => {
  const draft: ApplicationDraft = {
    firstName: 'Ada',
    lastName: 'Lovelace',
    email: 'ada@example.test',
    phone: '',
    coverLetter: '',
    consent: true,
  };

  it('exige le consentement RGPD', () => {
    expect(validateApplicationDraft({ ...draft, consent: false })).toHaveProperty('consent');
    expect(validateApplicationDraft(draft)).toEqual({});
  });

  it('valide les champs obligatoires et l’email', () => {
    const errors = validateApplicationDraft({ ...draft, firstName: ' ', email: 'nope' });

    expect(errors).toHaveProperty('firstName');
    expect(errors).toHaveProperty('email');
  });

  it('accepte une adresse email raisonnable', () => {
    expect(isValidEmail('a.b+c@sub.example.co')).toBe(true);
    expect(isValidEmail('a@b')).toBe(false);
  });

  it('refuse un CV trop lourd ou d’une mauvaise extension', () => {
    expect(isAcceptableResume({ name: 'cv.pdf', size: 1024 })).toBe(true);
    expect(isAcceptableResume({ name: 'cv.exe', size: 1024 })).toBe(false);
    expect(isAcceptableResume({ name: 'cv.pdf', size: 6 * 1024 * 1024 })).toBe(false);
    expect(isAcceptableResume({ name: 'cv.pdf', size: 0 })).toBe(false);
  });

  it('distingue un dossier actif d’un dossier terminal', () => {
    expect(isApplicationStatus('interview')).toBe(true);
    expect(isActiveApplication('interview')).toBe(true);
    expect(isActiveApplication('rejected')).toBe(false);
    expect(isActiveApplication('hired')).toBe(false);
  });
});

describe('JSON-LD JobPosting (#9629)', () => {
  it('déclare une candidature directe pour une offre du tenant', () => {
    const data = jobPostingJsonLd(offer(), 'https://emploi.test/emploi/acme/offres/1');

    expect(data['@type']).toBe('JobPosting');
    expect(data.employmentType).toBe('FULL_TIME');
    expect(data.directApply).toBe(true);
    expect(data.url).toBe('https://emploi.test/emploi/acme/offres/1');
  });

  it('ne prétend pas une candidature directe pour une offre agrégée', () => {
    const data = jobPostingJsonLd(
      offer({ external: true, applyUrl: 'https://partner.test/job/1' }),
      'https://emploi.test/emploi/acme/offres/1',
    );

    expect(data.directApply).toBe(false);
    expect(data.url).toBe('https://partner.test/job/1');
  });

  it('marque le télétravail total et omet le salaire quand il est inconnu', () => {
    const remote = jobPostingJsonLd(offer({ remotePolicy: 'remote' }), 'https://x.test/1');
    const noSalary = jobPostingJsonLd(
      offer({ salaryMin: null, salaryMax: null }),
      'https://x.test/1',
    );

    expect(remote.jobLocationType).toBe('TELECOMMUTE');
    expect(noSalary.baseSalary).toBeUndefined();
  });
});
