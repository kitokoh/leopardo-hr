import { z } from "zod";

/**
 * Validation schemas for all forms.
 */

import type { AppLocale } from '@/lib/i18n';

/**
 * Validation messages for the guided-trial signup form, localized.
 * The form is a 4-locale surface (FR/EN/TR/AR); the zod schema must
 * produce messages in the active locale (issue #2648).
 */
export const signupValidationMessages: Record<AppLocale, {
  emailInvalid: string;
  emailTooShort: string;
  emailTooLong: string;
  companyTooShort: string;
  companyTooLong: string;
  roleRequired: string;
  employeesRequired: string;
  phoneInvalid: string;
  agreeTerms: string;
}> = {
  fr: {
    emailInvalid: 'Email invalide',
    emailTooShort: 'Email trop court',
    emailTooLong: 'Email trop long',
    companyTooShort: "Le nom de l'entreprise doit contenir au moins 2 caractères",
    companyTooLong: "Le nom de l'entreprise est trop long",
    roleRequired: 'Sélectionnez votre rôle',
    employeesRequired: "Sélectionnez une taille d'équipe",
    phoneInvalid: 'Numéro de téléphone invalide',
    agreeTerms: "Vous devez accepter les conditions d'utilisation",
  },
  en: {
    emailInvalid: 'Invalid email',
    emailTooShort: 'Email too short',
    emailTooLong: 'Email too long',
    companyTooShort: 'Company name must be at least 2 characters',
    companyTooLong: 'Company name is too long',
    roleRequired: 'Select your role',
    employeesRequired: 'Select a team size',
    phoneInvalid: 'Invalid phone number',
    agreeTerms: 'You must accept the terms of use',
  },
  tr: {
    emailInvalid: 'Geçersiz e-posta',
    emailTooShort: 'E-posta çok kısa',
    emailTooLong: 'E-posta çok uzun',
    companyTooShort: 'Şirket adı en az 2 karakter olmalıdır',
    companyTooLong: 'Şirket adı çok uzun',
    roleRequired: 'Rolünüzü seçin',
    employeesRequired: 'Bir ekip boyutu seçin',
    phoneInvalid: 'Geçersiz telefon numarası',
    agreeTerms: 'Kullanım koşullarını kabul etmelisiniz',
  },
  ar: {
    emailInvalid: 'البريد الإلكتروني غير صالح',
    emailTooShort: 'البريد الإلكتروني قصير جدًا',
    emailTooLong: 'البريد الإلكتروني طويل جدًا',
    companyTooShort: 'يجب أن يتكون اسم الشركة من حرفين على الأقل',
    companyTooLong: 'اسم الشركة طويل جدًا',
    roleRequired: 'اختر دورك',
    employeesRequired: 'اختر حجم الفريق',
    phoneInvalid: 'رقم الهاتف غير صالح',
    agreeTerms: 'يجب عليك قبول شروط الاستخدام',
  },
};

export function signupFormSchema(locale: AppLocale) {
  const m = signupValidationMessages[locale] ?? signupValidationMessages.fr;

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
    }, "La date doit etre dans le futur")
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

export function validateCompanyName(name: string): boolean {
  return name.length >= 2 && name.length <= 100;
}

export function validateMessage(message: string): boolean {
  return message.length >= 10 && message.length <= 5000;
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
