import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail } from '@/modules/vitrine/lib/validation';

// Rate limiter instance (in production, use Redis)
const rateLimiter = new RateLimiter(5, 15 * 60 * 1000); // 5 attempts per 15 minutes

// Signup schema
const signupSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
  page: z.string().optional(),
  timestamp: z.string().optional(),
});

/**
 * POST /api/forms/signup
 * Handle signup form submission
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
    const validatedData = signupSchema.parse(body);

    // Sanitize inputs
    const sanitizedEmail = sanitizeEmail(validatedData.email);

    // TODO: In production, implement:
    // 1. Check if email already exists in database
    // 2. Hash password
    // 3. Create user in database
    // 4. Send confirmation email
    // 5. Log signup event

    // Mock implementation - simulate database operations
    console.log('Signup attempt:', {
      email: sanitizedEmail,
      page: validatedData.page,
      timestamp: validatedData.timestamp,
      ip,
    });

    // Simulate email sending
    // In production, use a service like SendGrid, Mailgun, or AWS SES
    const emailSent = await sendConfirmationEmail(sanitizedEmail);

    if (!emailSent) {
      return NextResponse.json(
        {
          success: false,
          message: 'Erreur lors de l\'envoi de l\'email de confirmation',
          error: 'EMAIL_SEND_FAILED',
        },
        { status: 500 }
      );
    }

    // Return success response
    return NextResponse.json(
      {
        success: true,
        message: 'Inscription réussie! Vérifiez votre email.',
        data: {
          email: sanitizedEmail,
          confirmationSent: true,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    console.error('Signup error:', error);

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
        message: 'Erreur lors de l\'inscription',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}

/**
 * Mock email sending function
 * In production, integrate with email service
 */
async function sendConfirmationEmail(email: string): Promise<boolean> {
  try {
    // TODO: Implement actual email sending
    // Example with SendGrid:
    // const sgMail = require('@sendgrid/mail');
    // sgMail.setApiKey(process.env.SENDGRID_API_KEY);
    // await sgMail.send({
    //   to: email,
    //   from: 'noreply@leopardo.com',
    //   subject: 'Confirmez votre email',
    //   html: confirmationEmailTemplate(email),
    // });

    console.log('Confirmation email would be sent to:', email);
    return true;
  } catch (error) {
    console.error('Email sending error:', error);
    return false;
  }
}
