import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const safeLog = (..._args: unknown[]) => {};

// Rate limiter instance (in production, use Redis)
const rateLimiter = new RateLimiter(5, 15 * 60 * 1000); // 5 attempts per 15 minutes

// Contact schema
const contactSchema = z.object({
  name: z.string().min(2),
  email: z.string().email(),
  subject: z.string().min(5),
  message: z.string().min(10),
  phone: z.string().optional(),
  page: z.string().optional(),
  timestamp: z.string().optional(),
});

/**
 * POST /api/forms/contact
 * Handle contact form submission
 */
export async function POST(request: NextRequest) {
  try {
    // Get client IP for rate limiting
    const ip =
      request.headers.get('x-forwarded-for') ||
      request.headers.get('x-real-ip') ||
      'unknown';

    // Check rate limit
    if (!rateLimiter.isAllowed(ip)) {
      return NextResponse.json(
        {
          success: false,
          message: 'Trop de tentatives. Veuillez réessayer plus tard.',
          error: 'RATE_LIMIT_EXCEEDED',
        },
        { status: 429 }
      );
    }

    // Parse request body
    const body = await request.json();

    // Validate input
    const validatedData = contactSchema.parse(body);

    // Sanitize inputs
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      subject: sanitizeInput(validatedData.subject),
      message: sanitizeInput(validatedData.message),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
    };

    // TODO: In production, implement:
    // 1. Save contact message to database
    // 2. Send confirmation email to user
    // 3. Send notification email to support team
    // 4. Create support ticket
    // 5. Log event to analytics

    safeLog('Contact message:', {
      ...sanitizedData,
      page: validatedData.page,
      timestamp: validatedData.timestamp,
      ip,
    });

    // Send confirmation email to user
    const userEmailSent = await sendContactConfirmationEmail(
      sanitizedData.email
    );

    // Send notification email to support team
    const supportEmailSent = await sendContactNotificationEmail(sanitizedData);

    if (!userEmailSent || !supportEmailSent) {
      safeLog('Email sending failed for contact message');
      // Don't fail the request, just log the warning
    }

    // Return success response
    return NextResponse.json(
      {
        success: true,
        message: 'Message envoyé! Nous vous répondrons bientôt.',
        data: {
          email: sanitizedData.email,
          name: sanitizedData.name,
          subject: sanitizedData.subject,
          confirmationSent: true,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    safeLog('Contact form error:', error);

    if (error instanceof z.ZodError) {
      return NextResponse.json(
        {
          success: false,
          message: 'Données invalides',
          error: 'VALIDATION_ERROR',
          details: error.issues,
        },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: 'Erreur lors de l\'envoi du message',
        error: 'INTERNAL_SERVER_ERROR',
      },

      { status: 500 }
    );
  }
}

/**
 * Send confirmation email to user
 */
async function sendContactConfirmationEmail(email: string): Promise<boolean> {
  try {
    // TODO: Implement actual email sending
    safeLog('Contact confirmation email would be sent to:', email);
    return true;
  } catch (error) {
    safeLog('Email sending error:', error);
    return false;
  }
}

/**
 * Send notification email to support team
 */
async function sendContactNotificationEmail(data: Record<string, unknown>): Promise<boolean> {
  try {
    // TODO: Implement actual email sending to support team
    safeLog('Contact notification email would be sent to support team:', data);
    return true;
  } catch (error) {
    safeLog('Email sending error:', error);
    return false;
  }
}