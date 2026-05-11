# Forms Implementation Guide

## Quick Start

### 1. Import Forms

```typescript
import {
  SignupForm,
  DemoForm,
  ContactForm,
  NewsletterForm,
} from '@/modules/vitrine/components/forms';
```

### 2. Use in Pages

```typescript
'use client';

import { SignupForm } from '@/modules/vitrine/components/forms';

export default function SignupPage() {
  return (
    <div className="container mx-auto px-4 py-12">
      <SignupForm page="/signup" />
    </div>
  );
}
```

### 3. Configure Email Service

Choose one email service and add credentials to `.env.local`:

```env
# SendGrid
SENDGRID_API_KEY=your_api_key

# or Mailgun
MAILGUN_API_KEY=your_api_key
MAILGUN_DOMAIN=mg.leopardo.com

# or AWS SES
AWS_SES_REGION=eu-west-1

# or Mailchimp
MAILCHIMP_API_KEY=your_api_key
MAILCHIMP_LIST_ID=your_list_id
```

---

## Form Components

### SignupForm

**Purpose:** User registration with email and password

**Props:**
```typescript
interface SignupFormProps {
  page?: string;                    // Current page for analytics
  onSuccess?: (data: SignupFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}
```

**Example:**
```typescript
<SignupForm
  page="/signup"
  onSuccess={(data) => {
    console.log('User signed up:', data.email);
    // Redirect to dashboard
  }}
  onError={(error) => {
    console.error('Signup failed:', error);
  }}
/>
```

**Validation:**
- Email: Valid email format
- Password: 8+ chars, uppercase, number, special char
- Confirm Password: Must match password
- Terms: Must be accepted

**Fields:**
- Email
- Password (with show/hide toggle)
- Confirm Password (with show/hide toggle)
- Accept Terms checkbox

---

### DemoForm

**Purpose:** Request a product demo

**Props:**
```typescript
interface DemoFormProps {
  page?: string;
  onSuccess?: (data: DemoFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}
```

**Example:**
```typescript
<DemoForm
  page="/demo"
  onSuccess={(data) => {
    console.log('Demo requested:', data.company);
    // Show confirmation message
  }}
/>
```

**Validation:**
- Name: 2-100 characters
- Email: Valid email format
- Company: 2-100 characters
- Phone: Valid phone format (optional)
- Employees: One of predefined ranges (optional)
- Preferred Date: Future date only (optional)

**Fields:**
- Name
- Email
- Company
- Phone (optional)
- Employee Count (optional)
- Preferred Date (optional, calendar picker)

**Features:**
- Calendar shows next 30 days
- Excludes weekends
- Sends confirmation to user
- Sends notification to sales team

---

### ContactForm

**Purpose:** Send a contact message

**Props:**
```typescript
interface ContactFormProps {
  page?: string;
  onSuccess?: (data: ContactFormData) => void;
  onError?: (error: string) => void;
  className?: string;
}
```

**Example:**
```typescript
<ContactForm
  page="/contact"
  onSuccess={(data) => {
    console.log('Message sent:', data.subject);
    // Show confirmation
  }}
/>
```

**Validation:**
- Name: 2-100 characters
- Email: Valid email format
- Subject: 5-200 characters
- Message: 10-5000 characters
- Phone: Valid phone format (optional)

**Fields:**
- Name
- Email
- Phone (optional)
- Subject
- Message (auto-resizing textarea)

**Features:**
- Auto-resizing textarea
- Sends confirmation to user
- Sends notification to support team

---

### NewsletterForm

**Purpose:** Subscribe to newsletter

**Props:**
```typescript
interface NewsletterFormProps {
  page?: string;
  onSuccess?: (data: NewsletterFormData) => void;
  onError?: (error: string) => void;
  className?: string;
  variant?: 'default' | 'compact' | 'inline';
  title?: string;
  description?: string;
}
```

**Variants:**

#### Default (Card Layout)
```typescript
<NewsletterForm
  variant="default"
  title="Restez informé"
  description="Recevez nos conseils directement dans votre boîte mail"
/>
```

#### Compact (Inline Form)
```typescript
<NewsletterForm
  variant="compact"
  className="w-full"
/>
```

#### Inline (Horizontal Layout)
```typescript
<NewsletterForm
  variant="inline"
  title="Newsletter"
  description="Recevez nos conseils"
/>
```

**Validation:**
- Email: Valid email format

**Fields:**
- Email only

**Features:**
- Multiple layout variants
- Customizable title and description
- Lightweight and flexible

---

## API Routes

### POST /api/forms/signup

**Request:**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "page": "/signup",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Inscription réussie! Vérifiez votre email.",
  "data": {
    "email": "user@example.com",
    "confirmationSent": true
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Erreur lors de l'inscription",
  "error": "VALIDATION_ERROR"
}
```

---

### POST /api/forms/demo

**Request:**
```json
{
  "name": "Jean Dupont",
  "email": "jean@example.com",
  "company": "Acme Corp",
  "phone": "+33123456789",
  "employees": "51-200",
  "preferredDate": "2024-01-15",
  "page": "/demo",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Demande de démo envoyée! Nous vous contacterons bientôt.",
  "data": {
    "email": "jean@example.com",
    "name": "Jean Dupont",
    "company": "Acme Corp",
    "confirmationSent": true
  }
}
```

---

### POST /api/forms/contact

**Request:**
```json
{
  "name": "Jean Dupont",
  "email": "jean@example.com",
  "subject": "Question about pricing",
  "message": "I would like to know more about your pricing plans...",
  "phone": "+33123456789",
  "page": "/contact",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Message envoyé! Nous vous répondrons bientôt.",
  "data": {
    "email": "jean@example.com",
    "name": "Jean Dupont",
    "subject": "Question about pricing",
    "confirmationSent": true
  }
}
```

---

### POST /api/forms/newsletter

**Request:**
```json
{
  "email": "user@example.com",
  "page": "/newsletter",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Inscription à la newsletter réussie!",
  "data": {
    "email": "user@example.com",
    "confirmationSent": true
  }
}
```

---

## Email Service Integration

### SendGrid

**Installation:**
```bash
npm install @sendgrid/mail
```

**Implementation:**
```typescript
// web/src/app/api/forms/signup/route.ts

import sgMail from '@sendgrid/mail';

sgMail.setApiKey(process.env.SENDGRID_API_KEY!);

async function sendConfirmationEmail(email: string): Promise<boolean> {
  try {
    await sgMail.send({
      to: email,
      from: process.env.SENDGRID_FROM_EMAIL!,
      subject: 'Confirmez votre email',
      html: `
        <h1>Bienvenue!</h1>
        <p>Cliquez sur le lien ci-dessous pour confirmer votre email:</p>
        <a href="https://leopardo.com/confirm?email=${email}">Confirmer</a>
      `,
    });
    return true;
  } catch (error) {
    console.error('SendGrid error:', error);
    return false;
  }
}
```

### Mailgun

**Installation:**
```bash
npm install mailgun.js
```

**Implementation:**
```typescript
import mailgun from 'mailgun.js';
import FormData from 'form-data';

const mg = mailgun.client({
  username: 'api',
  key: process.env.MAILGUN_API_KEY!,
});

async function sendConfirmationEmail(email: string): Promise<boolean> {
  try {
    await mg.messages.create(process.env.MAILGUN_DOMAIN!, {
      from: `Leopardo <noreply@${process.env.MAILGUN_DOMAIN}>`,
      to: email,
      subject: 'Confirmez votre email',
      html: `<h1>Bienvenue!</h1>...`,
    });
    return true;
  } catch (error) {
    console.error('Mailgun error:', error);
    return false;
  }
}
```

### AWS SES

**Installation:**
```bash
npm install @aws-sdk/client-ses
```

**Implementation:**
```typescript
import { SESClient, SendEmailCommand } from '@aws-sdk/client-ses';

const sesClient = new SESClient({ region: process.env.AWS_SES_REGION });

async function sendConfirmationEmail(email: string): Promise<boolean> {
  try {
    await sesClient.send(
      new SendEmailCommand({
        Source: process.env.AWS_SES_FROM_EMAIL!,
        Destination: { ToAddresses: [email] },
        Message: {
          Subject: { Data: 'Confirmez votre email' },
          Body: { Html: { Data: '<h1>Bienvenue!</h1>...' } },
        },
      })
    );
    return true;
  } catch (error) {
    console.error('SES error:', error);
    return false;
  }
}
```

### Mailchimp

**Installation:**
```bash
npm install @mailchimp/mailchimp_marketing
```

**Implementation:**
```typescript
import mailchimp from '@mailchimp/mailchimp_marketing';

mailchimp.setConfig({
  apiKey: process.env.MAILCHIMP_API_KEY,
  server: process.env.MAILCHIMP_SERVER_PREFIX,
});

async function subscribeToNewsletter(email: string): Promise<boolean> {
  try {
    await mailchimp.lists.addListMember(process.env.MAILCHIMP_LIST_ID!, {
      email_address: email,
      status: 'pending',
    });
    return true;
  } catch (error) {
    console.error('Mailchimp error:', error);
    return false;
  }
}
```

---

## Analytics Integration

### Tracking Events

All forms automatically track events to GA4 and Mixpanel:

**Signup:**
```
Event: Signup
Properties: email, source, page, name
```

**Demo Request:**
```
Event: Demo Request
Properties: email, company, source, page, name, phone, employees, preferredDate
```

**Contact:**
```
Event: Contact
Properties: email, subject, source, page, name, phone, message
```

**Newsletter:**
```
Event: Newsletter Signup
Properties: email, source, page, variant
```

### Custom Tracking

```typescript
import { useAnalyticsForm } from '@/modules/vitrine/hooks/useAnalytics';

export function MyForm() {
  const { trackSignup, trackDemoRequest } = useAnalyticsForm();

  const handleSignup = (email: string) => {
    trackSignup(email, { custom_prop: 'value' });
  };

  return <button onClick={() => handleSignup('user@example.com')}>Sign Up</button>;
}
```

---

## Validation

### Client-side Validation

Validation happens automatically with React Hook Form and Zod:

```typescript
// Signup validation
const schema = z.object({
  email: z.string().email('Email invalide'),
  password: z.string().min(8, 'Minimum 8 characters'),
  // ...
});

// Form automatically validates on blur
<Input {...register('email')} />
```

### Server-side Validation

All API routes validate input with Zod:

```typescript
const signupSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
});

const validatedData = signupSchema.parse(body);
```

---

## Error Handling

### Client-side Errors

Errors display inline with the form field:

```typescript
{errors.email && (
  <p className="text-red-600">{errors.email.message}</p>
)}
```

### Server-side Errors

Errors return appropriate HTTP status codes:

```
400 - Validation Error
429 - Rate Limit Exceeded
500 - Internal Server Error
```

### User-friendly Messages

All error messages are in French:

```
"Email invalide"
"Le mot de passe doit contenir au moins 8 caractères"
"Trop de tentatives. Veuillez réessayer plus tard."
```

---

## Security

### Rate Limiting

Each form has rate limiting to prevent spam:

- Signup: 5 attempts per 15 minutes
- Demo: 5 attempts per 15 minutes
- Contact: 5 attempts per 15 minutes
- Newsletter: 10 attempts per 15 minutes

### Input Sanitization

All inputs are sanitized:

```typescript
// Email sanitization
const sanitizedEmail = sanitizeEmail(data.email);
// Result: lowercase, trimmed

// Text sanitization
const sanitizedText = sanitizeInput(data.name);
// Result: HTML removed, scripts removed
```

### CSRF Protection

Ready for implementation:

```typescript
// Get CSRF token
const token = await getCSRFToken();

// Include in form
<input type="hidden" name="csrf_token" value={token} />
```

---

## Styling

### Tailwind CSS Classes

Forms use Tailwind CSS for styling:

```typescript
// Primary button
<Button variant="primary" size="lg" fullWidth>
  Submit
</Button>

// Input with error
<Input
  error={errors.email?.message}
  {...register('email')}
/>

// Card container
<Card className="p-6 md:p-8">
  {/* Form content */}
</Card>
```

### Dark Mode

All forms support dark mode:

```typescript
// Automatic dark mode support
<div className="dark:bg-slate-900 dark:text-white">
  <SignupForm />
</div>
```

### Responsive Design

Forms are mobile-first and responsive:

```typescript
// Mobile: Single column
// Tablet: Optimized spacing
// Desktop: Full layout

<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
  {/* Form fields */}
</div>
```

---

## Testing

### Manual Testing

1. Fill out form with valid data
2. Submit form
3. Check success message
4. Verify email sent
5. Check analytics event

### Automated Testing

```typescript
import { render, screen, fireEvent } from '@testing-library/react';
import { SignupForm } from '@/modules/vitrine/components/forms';

test('SignupForm submits successfully', async () => {
  render(<SignupForm />);
  
  fireEvent.change(screen.getByLabelText('Email'), {
    target: { value: 'test@example.com' },
  });
  
  fireEvent.click(screen.getByText('Créer mon compte'));
  
  expect(await screen.findByText(/réussie/i)).toBeInTheDocument();
});
```

---

## Troubleshooting

### Form not submitting

1. Check browser console for errors
2. Verify API route exists
3. Check network tab for failed requests
4. Verify environment variables

### Email not sending

1. Check email service credentials
2. Verify email service is configured
3. Check email service logs
4. Verify sender email is verified

### Rate limiting too strict

Adjust in API route:

```typescript
const rateLimiter = new RateLimiter(
  10,                    // attempts
  15 * 60 * 1000        // window (ms)
);
```

### Validation errors

Check Zod schema in `lib/validation.ts`:

```typescript
export const signupFormSchema = z.object({
  email: z.string().email('Email invalide'),
  // ...
});
```

---

## Performance

### Bundle Size

- Form components: ~15KB (gzipped)
- API routes: ~5KB each
- Validation library: ~10KB
- Total: ~50KB

### Optimization Tips

1. Use dynamic imports for forms
2. Lazy load forms below fold
3. Minimize re-renders with React.memo
4. Use server-side validation only

---

## Accessibility

### WCAG 2.1 AA Compliance

- ✅ Keyboard navigation
- ✅ Screen reader support
- ✅ Color contrast > 4.5:1
- ✅ Focus indicators visible
- ✅ Error messages associated with fields

### Testing

```bash
# Run accessibility tests
npm run test:a11y

# Check with axe DevTools
# Check with WAVE
# Test with screen reader
```

---

## Support

For issues or questions:

1. Check this guide
2. Check component documentation
3. Check API route documentation
4. Check analytics documentation
5. Open an issue on GitHub

---

## Resources

- [React Hook Form](https://react-hook-form.com/)
- [Zod](https://zod.dev/)
- [Next.js API Routes](https://nextjs.org/docs/app/building-your-application/routing/route-handlers)
- [Tailwind CSS](https://tailwindcss.com/)
- [Framer Motion](https://www.framer.com/motion/)

