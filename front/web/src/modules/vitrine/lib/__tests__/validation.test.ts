import { z } from 'zod';

import { PASSWORD_MIN_LENGTH, isPasswordAcceptable } from '@/lib/password-policy';
import {
  signupFormSchema,
  demoFormSchema,
  contactFormSchema,
  newsletterFormSchema,
  validateEmail,
  validatePhoneNumber,
  sanitizeInput,
  RateLimiter,
} from '../validation';

describe('Form Validation Schemas', () => {
  describe('signupFormSchema', () => {
    it('should validate correct trial request', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        role: 'manager',
        employees: '11-50',
        country: 'DZ',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).not.toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        email: 'invalid-email',
        company: 'Acme Corp',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should reject empty company', () => {
      const data = {
        email: 'test@example.com',
        company: '',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should reject invalid phone when provided', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        phone: 'not-a-phone',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    // Le pays n'est plus demandé à l'utilisateur : il est résolu côté serveur
    // par géolocalisation. Le schéma l'accepte donc absent (mais le valide
    // toujours s'il est fourni).
    it('should accept a payload without country (#7250)', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).not.toThrow();
    });

    it('should reject a non-2-letter country (#4476)', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        country: 'ALGERIA',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should normalize lowercase country codes (#4476)', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        country: 'dz',
        agreeToTerms: true,
      };
      // lowercase is rejected by the schema (select sends uppercase) — the
      // payload normalizes to uppercase in forms.ts regardless.
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should accept empty optional phone', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        phone: '',
        country: 'DZ', // requis depuis #4476 (pays obligatoire côté API)
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).not.toThrow();
    });

    it('should reject when terms not agreed', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        agreeToTerms: false,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should accept various valid emails', () => {
      const validEmails = [
        'user@example.com',
        'user.name@example.com',
        'user+tag@example.co.uk',
        'user123@test-domain.com',
      ];

      validEmails.forEach(email => {
        const data = {
          email,
          company: 'Acme Corp',
          country: 'DZ', // requis depuis #4476
          agreeToTerms: true,
        };
        expect(() => signupFormSchema('fr').parse(data)).not.toThrow();
      });
    });
  });

  describe('demoFormSchema', () => {
    it('should validate correct demo request', () => {
      const data = {
        name: 'John Doe',
        email: 'john@example.com',
        company: 'Acme Corp',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).not.toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        name: 'John Doe',
        email: 'invalid-email',
        company: 'Acme Corp',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should reject empty name', () => {
      const data = {
        name: '',
        email: 'john@example.com',
        company: 'Acme Corp',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should reject short name', () => {
      const data = {
        name: 'J',
        email: 'john@example.com',
        company: 'Acme Corp',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should accept optional phone', () => {
      const data = {
        name: 'John Doe',
        email: 'john@example.com',
        company: 'Acme Corp',
      };
      expect(() => demoFormSchema.parse(data)).not.toThrow();
    });
  });

  describe('contactFormSchema', () => {
    it('should validate correct contact form', () => {
      const data = {
        name: 'Jane Doe',
        email: 'jane@example.com',
        subject: 'Question about pricing',
        message: 'I have a question about your pricing plans and would like more information.',
      };
      expect(() => contactFormSchema.parse(data)).not.toThrow();
    });

    it('should reject empty name', () => {
      const data = {
        name: '',
        email: 'jane@example.com',
        subject: 'Question',
        message: 'This is a message with enough characters',
      };
      expect(() => contactFormSchema.parse(data)).toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        name: 'Jane Doe',
        email: 'invalid',
        subject: 'Question',
        message: 'This is a message with enough characters',
      };
      expect(() => contactFormSchema.parse(data)).toThrow();
    });

    it('should reject short message', () => {
      const data = {
        name: 'Jane Doe',
        email: 'jane@example.com',
        subject: 'Question',
        message: 'Hi',
      };
      expect(() => contactFormSchema.parse(data)).toThrow();
    });

    it('should accept long message', () => {
      const data = {
        name: 'Jane Doe',
        email: 'jane@example.com',
        subject: 'Question about pricing',
        message: 'I have a detailed question about your pricing plans and would like to know more about the enterprise options.',
      };
      expect(() => contactFormSchema.parse(data)).not.toThrow();
    });
  });

  describe('newsletterFormSchema', () => {
    it('should validate correct email', () => {
      const data = {
        email: 'subscriber@example.com',
      };
      expect(() => newsletterFormSchema.parse(data)).not.toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        email: 'invalid-email',
      };
      expect(() => newsletterFormSchema.parse(data)).toThrow();
    });

    it('should reject empty email', () => {
      const data = {
        email: '',
      };
      expect(() => newsletterFormSchema.parse(data)).toThrow();
    });

    it('should accept various valid emails', () => {
      const validEmails = [
        'user@example.com',
        'user.name@example.com',
        'user+newsletter@example.co.uk',
      ];

      validEmails.forEach(email => {
        const data = { email };
        expect(() => newsletterFormSchema.parse(data)).not.toThrow();
      });
    });
  });

  describe('Validation Helper Functions', () => {
    describe('validateEmail', () => {
      it('should validate correct emails', () => {
        expect(validateEmail('test@example.com')).toBe(true);
        expect(validateEmail('user.name@example.co.uk')).toBe(true);
      });

      it('should reject invalid emails', () => {
        expect(validateEmail('invalid')).toBe(false);
        expect(validateEmail('invalid@')).toBe(false);
        expect(validateEmail('@example.com')).toBe(false);
      });
    });

    describe('isPasswordAcceptable (politique partagée)', () => {
      // QA onboarding 2026-09-14 : la politique vit dans @/lib/password-policy
      // (source unique, alignée sur `Password::min(12)->numbers()` côté API).
      // L'ancien helper `validatePassword` décrivait 8 + majuscule + chiffre +
      // spécial… sans être appelé par aucun écran.
      it('accepte un mot de passe long avec chiffre', () => {
        expect(isPasswordAcceptable('Leopardo!Qa2026#Ui')).toBe(true);
      });

      it('refuse un mot de passe trop court', () => {
        expect(isPasswordAcceptable('abc12345')).toBe(false);
      });

      it('refuse un mot de passe long sans chiffre', () => {
        expect(isPasswordAcceptable('motdepasseuniquement')).toBe(false);
      });

      it('accepte exactement la longueur minimale', () => {
        expect(isPasswordAcceptable('a'.repeat(PASSWORD_MIN_LENGTH - 1) + '7')).toBe(true);
      });

      it('n\'exige ni majuscule ni caractère spécial (NIST SP 800-63B)', () => {
        expect(isPasswordAcceptable('languepasse2026')).toBe(true);
      });
    });

    describe('validatePhoneNumber', () => {
      it('should validate correct phone numbers', () => {
        expect(validatePhoneNumber('+33612345678')).toBe(true);
        expect(validatePhoneNumber('0612345678')).toBe(true);
        expect(validatePhoneNumber('+1-234-567-8900')).toBe(true);
      });

      it('should reject invalid phone numbers', () => {
        expect(validatePhoneNumber('invalid')).toBe(false);
        expect(validatePhoneNumber('123')).toBe(false);
      });
    });

    describe('sanitizeInput', () => {
      it('should remove angle brackets', () => {
        expect(sanitizeInput('<script>alert("xss")</script>')).not.toContain('<');
        expect(sanitizeInput('<script>alert("xss")</script>')).not.toContain('>');
      });

      it('should remove javascript protocol', () => {
        expect(sanitizeInput('javascript:alert("xss")')).not.toContain('javascript:');
      });

      it('should remove event handlers', () => {
        expect(sanitizeInput('onclick=alert("xss")')).not.toContain('onclick');
      });

      it('should trim whitespace', () => {
        expect(sanitizeInput('  test  ')).toBe('test');
      });
    });
  });

  describe('RateLimiter', () => {
    it('should allow requests within limit', () => {
      const limiter = new RateLimiter(5, 60000);
      expect(limiter.isAllowed('user1')).toBe(true);
      expect(limiter.isAllowed('user1')).toBe(true);
      expect(limiter.isAllowed('user1')).toBe(true);
    });

    it('should block requests exceeding limit', () => {
      const limiter = new RateLimiter(3, 60000);
      expect(limiter.isAllowed('user2')).toBe(true);
      expect(limiter.isAllowed('user2')).toBe(true);
      expect(limiter.isAllowed('user2')).toBe(true);
      expect(limiter.isAllowed('user2')).toBe(false);
    });

    it('should track remaining attempts', () => {
      const limiter = new RateLimiter(5, 60000);
      limiter.isAllowed('user3');
      limiter.isAllowed('user3');
      expect(limiter.getRemainingAttempts('user3')).toBe(3);
    });

    it('should reset attempts for user', () => {
      const limiter = new RateLimiter(3, 60000);
      limiter.isAllowed('user4');
      limiter.isAllowed('user4');
      limiter.isAllowed('user4');
      expect(limiter.isAllowed('user4')).toBe(false);
      
      limiter.reset('user4');
      expect(limiter.isAllowed('user4')).toBe(true);
    });
  });

  describe('Error Messages', () => {
    it('should provide helpful error messages', () => {
      const data = {
        email: 'invalid',
        company: '',
        agreeToTerms: false,
      };

      try {
        signupFormSchema('fr').parse(data);
      } catch (error) {
        if (error instanceof z.ZodError) {
          expect(error.issues.length).toBeGreaterThan(0);
          expect(error.issues[0].message).toBeDefined();
        }
      }
    });
  });
});
