import { z } from "zod";

/**
 * Validation schemas for all forms.
 */

import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Validation messages for the guided-trial signup form, localized.
 * The form is a 4-locale surface (FR/EN/TR/AR); the zod schema must
 * produce messages in the active locale (issue #2648).
 */
function buildSignupValidationMessages(locale: AppLocale) {
  return {
    emailInvalid: t(locale, 'signup.validation.emailInvalid'),
    emailTooShort: t(locale, 'signup.validation.emailTooShort'),
    emailTooLong: t(locale, 'signup.validation.emailTooLong'),
    companyTooShort: t(locale, 'signup.validation.companyTooShort'),
    companyTooLong: t(locale, 'signup.validation.companyTooLong'),
    roleRequired: t(locale, 'signup.validation.roleRequired'),
    employeesRequired: t(locale, 'signup.validation.employeesRequired'),
    countryRequired: t(locale, 'signup.validation.countryRequired'),
    phoneInvalid: t(locale, 'signup.validation.phoneInvalid'),
    agreeTerms: t(locale, 'signup.validation.agreeTerms'),
  };
}

export function signupFormSchema(locale: AppLocale) {
  const m = buildSignupValidationMessages(locale);

  return z.object({
    email: z
      .string()
      .email(m.emailInvalid)
      .min(5, m.emailTooShort)
      .max(255, m.emailTooLong),
    // #7853 — inscription par e-mail seul : le nom d'entreprise n'est plus
    // demandé dans le tunnel (il est dérivé de l'e-mail côté serveur puis
    // affiné dans l'entretien de préparation #7493). S'il est transmis par un
    // appelant historique, le contrat 2..120 reste appliqué.
    company: z
      .string()
      .min(2, m.companyTooShort)
      .max(120, m.companyTooLong)
      .optional()
      .or(z.literal('')),
    role: z
      .enum(['founder', 'manager', 'hr', 'operations', 'other'], {
        message: m.roleRequired,
      })
      .optional(),
    employees: z
      .enum(['1-10', '11-50', '51-200', '201-500', '500+'], {
        message: m.employeesRequired,
      })
      .optional(),
    // MULTI-PAYS (#1867/#4476) : le pays est obligatoire côté API
    // (required|size:2|SupportedCountry). Il n'est PLUS demandé à
    // l'utilisateur : il est résolu côté serveur par géolocalisation
    // (`request.geo`, Vercel) dans /api/forms/signup, et reste modifiable
    // ensuite dans les paramètres de l'entreprise.
    country: z
      .string()
      .length(2, { message: m.countryRequired })
      .refine((v) => v === v.toUpperCase(), { message: m.countryRequired })
      .optional()
      .or(z.literal('')),
    phone: z
      .string()
      .regex(
        /^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/,
        m.phoneInvalid
      )
      .optional()
      .or(z.literal('')),
    agreeToTerms: z.boolean().refine((val) => val === true, {
      message: m.agreeTerms,
    }),
    // #7235 — Profil d'activité : `company` (entreprise, défaut) ou `solo`
    // (indépendant). Le choix est imposé par le parcours (l'écran de profil
    // est obligatoire) ; le schéma reste permissif pour ne pas casser le
    // formulaire rapide du hero, qui ne collecte qu'un email.
    company_type: z.enum(['company', 'solo']).optional(),
    // #7238 — offre choisie à l'inscription (le compte est créé POUR cette offre).
    plan: z.enum(['free', 'pilot', 'operations', 'enterprise']).optional(),
    // #7235 — Outils horizontaux choisis (allowlist revalidée côté API).
    modules: z.array(z.string().max(40)).max(20).optional(),
    // #7235 — Métier vertical (code du catalogue de solutions).
    solutions: z.array(z.string().max(40)).max(20).optional(),
  });
}

export type SignupFormData = z.infer<ReturnType<typeof signupFormSchema>>;


// #7594 — les 3 schémas publics (demo/contact/newsletter) étaient des
// constantes aux messages français codés en dur, référencées UNIQUEMENT par
// leurs tests (schémas morts) : la validation client réelle se limitait au
// HTML5, dans la langue du NAVIGATEUR. Ils deviennent des fabriques par
// locale (même patron que signupFormSchema, #2648) : les messages viennent du
// catalogue partagé `forms.validation.*` (shared/i18n/locales, ×4 locales).
function buildFormsValidationMessages(locale: AppLocale) {
  return {
    nameTooShort: t(locale, 'forms.validation.nameTooShort'),
    nameTooLong: t(locale, 'forms.validation.nameTooLong'),
    emailInvalid: t(locale, 'forms.validation.emailInvalid'),
    emailTooShort: t(locale, 'forms.validation.emailTooShort'),
    emailTooLong: t(locale, 'forms.validation.emailTooLong'),
    companyTooShort: t(locale, 'forms.validation.companyTooShort'),
    companyTooLong: t(locale, 'forms.validation.companyTooLong'),
    subjectTooShort: t(locale, 'forms.validation.subjectTooShort'),
    subjectTooLong: t(locale, 'forms.validation.subjectTooLong'),
    messageTooShort: t(locale, 'forms.validation.messageTooShort'),
    messageTooLong: t(locale, 'forms.validation.messageTooLong'),
    phoneInvalid: t(locale, 'forms.validation.phoneInvalid'),
    employeesInvalid: t(locale, 'forms.validation.employeesInvalid'),
    dateFuture: t(locale, 'forms.validation.dateFuture'),
  };
}

const PHONE_REGEX =
  /^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/;

export function demoFormSchema(locale: AppLocale) {
  const m = buildFormsValidationMessages(locale);

  return z.object({
    name: z.string().min(2, m.nameTooShort).max(100, m.nameTooLong),
    email: z
      .string()
      .email(m.emailInvalid)
      .min(5, m.emailTooShort)
      .max(255, m.emailTooLong),
    company: z.string().min(2, m.companyTooShort).max(100, m.companyTooLong),
    phone: z
      .string()
      .regex(PHONE_REGEX, m.phoneInvalid)
      .optional()
      .or(z.literal('')),
    employees: z
      .enum(['1-10', '11-50', '51-200', '201-500', '500+'], {
        message: m.employeesInvalid,
      })
      .optional()
      .or(z.literal('')),
    preferredDate: z
      .string()
      .refine((date) => {
        const selectedDate = new Date(date);
        const today = new Date();
        return selectedDate > today;
      }, m.dateFuture)
      .optional()
      .or(z.literal('')),
    // Le formulaire /demo collecte un message optionnel : il doit passer la
    // validation client sans être tronqué par le schéma.
    message: z.string().max(5000, m.messageTooLong).optional().or(z.literal('')),
  });
}

export type DemoFormData = z.infer<ReturnType<typeof demoFormSchema>>;

export function contactFormSchema(locale: AppLocale) {
  const m = buildFormsValidationMessages(locale);

  return z.object({
    name: z.string().min(2, m.nameTooShort).max(100, m.nameTooLong),
    email: z
      .string()
      .email(m.emailInvalid)
      .min(5, m.emailTooShort)
      .max(255, m.emailTooLong),
    // #7594 — `company` est collecté par le formulaire de contact : il doit
    // exister ici aussi (le schéma serveur de /api/forms/contact le déclare
    // désormais — zod supprime les clés inconnues, la valeur était perdue).
    company: z
      .string()
      .max(100, m.companyTooLong)
      .optional()
      .or(z.literal('')),
    // min(2) aligné sur le schéma serveur : le sujet vient d'un <select> aux
    // valeurs localisées (« أخرى » = 4 caractères — min(5) cassait l'arabe).
    subject: z.string().min(2, m.subjectTooShort).max(200, m.subjectTooLong),
    message: z.string().min(10, m.messageTooShort).max(5000, m.messageTooLong),
    phone: z
      .string()
      .regex(PHONE_REGEX, m.phoneInvalid)
      .optional()
      .or(z.literal('')),
  });
}

export type ContactFormData = z.infer<ReturnType<typeof contactFormSchema>>;

export function newsletterFormSchema(locale: AppLocale) {
  const m = buildFormsValidationMessages(locale);

  return z.object({
    email: z
      .string()
      .email(m.emailInvalid)
      .min(5, m.emailTooShort)
      .max(255, m.emailTooLong),
  });
}

export type NewsletterFormData = z.infer<ReturnType<typeof newsletterFormSchema>>;

export function validateEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

export function validatePhoneNumber(phone: string): boolean {
  const digits = phone.replace(/\D/g, "");
  if (digits.length < 10 || digits.length > 15) {
    return false;
  }

  const phoneRegex = /^\+?[\d\s().-]+$/;
  return phoneRegex.test(phone);
}



export function sanitizeInput(input: string): string {
  return input
    .trim()
    .replace(/[<>]/g, "")
    .replace(/javascript:/gi, "")
    .replace(/on\w+\s*=/gi, "");
}

export function sanitizeEmail(email: string): string {
  return email.toLowerCase().trim();
}

/**
 * #7594 — Validation carte du checkout, côté client uniquement (le paiement
 * réel est délégué à Stripe Checkout : ces contrôles évitent un aller-retour
 * inutile, ils ne remplacent PAS la vérification du PSP).
 */

/** Algorithme de Luhn sur un numéro de carte (espaces tolérés). */
export function luhnCheck(cardNumber: string): boolean {
  const digits = cardNumber.replace(/[\s-]/g, '');
  if (!/^[0-9]{12,19}$/.test(digits)) return false;
  let sum = 0;
  let double = false;
  for (let i = digits.length - 1; i >= 0; i--) {
    let d = digits.charCodeAt(i) - 48;
    if (double) {
      d *= 2;
      if (d > 9) d -= 9;
    }
    sum += d;
    double = !double;
  }
  return sum % 10 === 0;
}

/**
 * Expiration `MM/AA` : format valide ET non expirée (une carte expirant le
 * mois courant reste valide jusqu'à la fin du mois).
 */
export function isCardExpiryValid(expiry: string, now: Date = new Date()): boolean {
  const match = /^(0[1-9]|1[0-2])\/([0-9]{2})$/.exec(expiry.trim());
  if (!match) return false;
  const month = Number(match[1]);
  const year = 2000 + Number(match[2]);
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth() + 1;
  return year > currentYear || (year === currentYear && month >= currentMonth);
}

/**
 * Cohérence CVC ↔ réseau de la carte : 4 chiffres pour American Express
 * (IIN 34/37), 3 chiffres pour les autres réseaux.
 */
export function isCardCvcValid(cvc: string, cardNumber: string): boolean {
  const digits = cardNumber.replace(/[\s-]/g, '');
  const isAmex = /^3[47]/.test(digits);
  return isAmex ? /^[0-9]{4}$/.test(cvc) : /^[0-9]{3}$/.test(cvc);
}

export class RateLimiter {
  private attempts: Map<string, number[]> = new Map();
  private maxAttempts: number;
  private windowMs: number;

  constructor(maxAttempts: number = 5, windowMs: number = 15 * 60 * 1000) {
    this.maxAttempts = maxAttempts;
    this.windowMs = windowMs;
  }

  isAllowed(identifier: string): boolean {
    const now = Date.now();
    const attempts = this.attempts.get(identifier) || [];
    const recentAttempts = attempts.filter((time) => now - time < this.windowMs);

    if (recentAttempts.length >= this.maxAttempts) {
      return false;
    }

    recentAttempts.push(now);
    this.attempts.set(identifier, recentAttempts);

    return true;
  }

  getRemainingAttempts(identifier: string): number {
    const now = Date.now();
    const attempts = this.attempts.get(identifier) || [];
    const recentAttempts = attempts.filter((time) => now - time < this.windowMs);
    return Math.max(0, this.maxAttempts - recentAttempts.length);
  }

  reset(identifier: string): void {
    this.attempts.delete(identifier);
  }
}

export interface FormError {
  field: string;
  message: string;
}

export function parseZodErrors(error: z.ZodError): FormError[] {
  return error.issues.map((err) => ({
    field: err.path.join("."),
    message: err.message,
  }));
}
