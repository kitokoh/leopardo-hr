import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail } from '@/modules/vitrine/lib/validation';

// Rate limiter instance (in production, use Redis)
const rateLimiter = new RateLimiter(10, 15 * 60 * 1000); // 10 attempts per 15 minutes

// Newsletter schema
const newsletterSchema = z.object({
  email: z.string().email(),
  page: z.string().optional(),
  timestamp: z.string().optional(),
});

/**
 * POST /api/forms/newsletter
 * Handle newsletter signup form submission
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
    const validatedData = newsletterSchema.parse(body);

    // Sanitize inputs
    const sanitizedEmail = sanitizeEmail(validatedData.email);

    // TODO: In production, implement:
    // 1. Check if email already subscribed
    // 2. Add email to newsletter list
    // 3. Send confirmation email
    // 4. Log event to analytics
    // 5. Integrate with email service (Mailchimp, ConvertKit, etc.)

    console.log('Newsletter signup:', {
      email: sanitizedEmail,
      page: validatedData.page,
      timestamp: validatedData.timestamp,
      ip,
    });

    // Send confirmation email
    const emailSent = await sendNewsletterConfirmationEmail(sanitizedEmail);

    if (!emailSent) {
      console.warn('Email sending failed for newsletter signup');
      // Don't fail the request, just log the warning
    }

    // Return success response
    return NextResponse.json(
      {
        success: true,
        message: 'Inscription à la newsletter réussie!',
        data: {
          email: sanitizedEmail,
          confirmationSent: true,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    console.error('Newsletter signup error:', error);

    if (error instanceof z.ZodError) {
      return NextResponse.json(
        {
          success: false,
          message: 'Données invalides',
          error: 'VALIDATION_ERROR',
          details: error.errors,
        },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: 'Erreur lors de l\'inscription à la newsletter',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}

/**
 * Send confirmation email to user
 */
async function sendNewsletterConfirmationEmail(email: string): Promise<boolean> {
  try {
    // TODO: Implement actual email sending
    // Example with Mailchimp:
    // const mailchimp = require('@mailchimp/mailchimp_marketing');
    // mailchimp.setConfig({
    //   apiKey: process.env.MAILCHIMP_API_KEY,
    //   server: process.env.MAILCHIMP_SERVER_PREFIX,
    // });
    // await mailchimp.lists.addListMember(process.env.MAILCHIMP_LIST_ID, {
    //   email_address: email,
    //   status: 'pending',
    // });

    console.log('Newsletter confirmation email would be sent to:', email);
    return true;
  } catch (error) {
    console.error('Email sending error:', error);
    return false;
  }
}
