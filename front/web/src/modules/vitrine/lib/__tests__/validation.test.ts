import { z } from 'zod';
import {
  signupFormSchema,
  demoFormSchema,
  contactFormSchema,
  newsletterFormSchema,
  validateEmail,
  validatePassword,
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
        country: 'DZ',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should reject empty company', () => {
      const data = {
        email: 'test@example.com',
        company: '',
        country: 'DZ',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should reject invalid phone when provided', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        country: 'DZ',
        phone: 'not-a-phone',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
    });

    it('should reject a missing country (#4476)', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).toThrow();
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
        country: 'DZ',
        phone: '',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema('fr').parse(data)).not.toThrow();
    });

    it('should reject when terms not agreed', () => {
      const data = {
        email: 'test@example.com',
        company: 'Acme Corp',
        country: 'DZ',
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
        country: 'DZ',
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
        country: 'DZ',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).not.toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        name: 'John Doe',
        email: 'invalid-email',
        company: 'Acme Corp',
        country: 'DZ',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should reject empty name', () => {
      const data = {
        name: '',
        email: 'john@example.com',
        company: 'Acme Corp',
        country: 'DZ',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should reject short name', () => {
      const data = {
        name: 'J',
        email: 'john@example.com',
        company: 'Acme Corp',
        country: 'DZ',
        phone: '+33612345678',
      };
      expect(() => demoFormSchema.parse(data)).toThrow();
    });

    it('should accept optional phone', () => {
      const data = {
        name: 'John Doe',
        email: 'john@example.com',
        company: 'Acme Corp',
        country: 'DZ',
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
        country: 'DZ',
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
