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
    it('should validate correct email and password', () => {
      const data = {
        email: 'test@example.com',
        password: 'ValidPassword123!',
        confirmPassword: 'ValidPassword123!',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).not.toThrow();
    });

    it('should reject invalid email', () => {
      const data = {
        email: 'invalid-email',
        password: 'ValidPassword123!',
        confirmPassword: 'ValidPassword123!',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
    });

    it('should reject short password', () => {
      const data = {
        email: 'test@example.com',
        password: 'short',
        confirmPassword: 'short',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
    });

    it('should reject password without uppercase', () => {
      const data = {
        email: 'test@example.com',
        password: 'validpassword123!',
        confirmPassword: 'validpassword123!',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
    });

    it('should reject password without number', () => {
      const data = {
        email: 'test@example.com',
        password: 'ValidPassword!',
        confirmPassword: 'ValidPassword!',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
    });

    it('should reject password without special character', () => {
      const data = {
        email: 'test@example.com',
        password: 'ValidPassword123',
        confirmPassword: 'ValidPassword123',
        agreeToTerms: true,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
    });

    it('should reject when terms not agreed', () => {
      const data = {
        email: 'test@example.com',
        password: 'ValidPassword123!',
        confirmPassword: 'ValidPassword123!',
        agreeToTerms: false,
      };
      expect(() => signupFormSchema.parse(data)).toThrow();
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
          password: 'ValidPassword123!',
          confirmPassword: 'ValidPassword123!',
          agreeToTerms: true,
        };
        expect(() => signupFormSchema.parse(data)).not.toThrow();
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

    describe('validatePassword', () => {
      it('should validate strong passwords', () => {
        const result = validatePassword('ValidPassword123!');
        expect(result.isValid).toBe(true);
        expect(result.errors.length).toBe(0);
      });

      it('should reject weak passwords', () => {
        const result = validatePassword('weak');
        expect(result.isValid).toBe(false);
        expect(result.errors.length).toBeGreaterThan(0);
      });

      it('should identify missing uppercase', () => {
        const result = validatePassword('validpassword123!');
        expect(result.errors.some(e => e.includes('majuscule'))).toBe(true);
      });

      it('should identify missing number', () => {
        const result = validatePassword('ValidPassword!');
        expect(result.errors.some(e => e.includes('chiffre'))).toBe(true);
      });

      it('should identify missing special character', () => {
        const result = validatePassword('ValidPassword123');
        expect(result.errors.some(e => e.includes('spécial'))).toBe(true);
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
        password: 'short',
        confirmPassword: 'short',
        agreeToTerms: false,
      };

      try {
        signupFormSchema.parse(data);
      } catch (error) {
        if (error instanceof z.ZodError) {
          expect(error.issues.length).toBeGreaterThan(0);
          expect(error.issues[0].message).toBeDefined();
        }
      }
    });
  });
});
