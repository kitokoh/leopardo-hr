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
    company: z
      .string()
      .min(2, m.companyTooShort)
      .max(120, m.companyTooLong),
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
    // (required|size:2|SupportedCountry) — le formulaire doit l'envoyer,
    // sinon le tunnel se dégrade silencieusement en capture de lead.
    country: z
      .string()
      .length(2, { message: m.countryRequired })
      .refine((v) => v === v.toUpperCase(), { message: m.countryRequired }),
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
  });
}

export type SignupFormData = z.infer<ReturnType<typeof signupFormSchema>>;


export const demoFormSchema = z.object({
  name: z
    .string()
    .min(2, "Le nom doit contenir au moins 2 caracteres")
    .max(100, "Le nom est trop long"),
  email: z
    .string()
    .email("Email invalide")
    .min(5, "Email trop court")
    .max(255, "Email trop long"),
  company: z
    .string()
    .min(2, "Le nom de l'entreprise doit contenir au moins 2 caracteres")
    .max(100, "Le nom de l'entreprise est trop long"),
  phone: z
    .string()
    .regex(
      /^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/,
      "Numero de telephone invalide"
    )
    .optional()
    .or(z.literal("")),
  employees: z
    .enum(["1-10", "11-50", "51-200", "201-500", "500+"], {
      message: "Selectionnez une plage d'employes",
    })
    .optional(),
  preferredDate: z
    .string()
    .refine((date) => {
      const selectedDate = new Date(date);
      const today = new Date();
      return selectedDate > today;
    }, "La date doit être dans le futur")
    .optional()
    .or(z.literal("")),
});

export type DemoFormData = z.infer<typeof demoFormSchema>;

export const contactFormSchema = z.object({
  name: z
    .string()
    .min(2, "Le nom doit contenir au moins 2 caracteres")
    .max(100, "Le nom est trop long"),
  email: z
    .string()
    .email("Email invalide")
    .min(5, "Email trop court")
    .max(255, "Email trop long"),
  subject: z
    .string()
    .min(5, "Le sujet doit contenir au moins 5 caracteres")
    .max(200, "Le sujet est trop long"),
  message: z
    .string()
    .min(10, "Le message doit contenir au moins 10 caracteres")
    .max(5000, "Le message est trop long"),
  phone: z
    .string()
    .regex(
      /^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/,
      "Numero de telephone invalide"
    )
    .optional()
    .or(z.literal("")),
});

export type ContactFormData = z.infer<typeof contactFormSchema>;

export const newsletterFormSchema = z.object({
  email: z
    .string()
    .email("Email invalide")
    .min(5, "Email trop court")
    .max(255, "Email trop long"),
});

export type NewsletterFormData = z.infer<typeof newsletterFormSchema>;

export function validateEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

export function validatePassword(password: string): {
  isValid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  if (password.length < 8) {
    errors.push("Le mot de passe doit contenir au moins 8 caracteres");
  }
  if (!/[A-Z]/.test(password)) {
    errors.push("Le mot de passe doit contenir au moins une majuscule");
  }
  if (!/[0-9]/.test(password)) {
    errors.push("Le mot de passe doit contenir au moins un chiffre");
  }
  if (!/[^A-Za-z0-9]/.test(password)) {
    errors.push("Le mot de passe doit contenir au moins un caractère spécial");
  }

  return {
    isValid: errors.length === 0,
    errors,
  };
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
