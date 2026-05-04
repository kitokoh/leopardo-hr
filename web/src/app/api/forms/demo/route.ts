import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

// Rate limiter instance (in production, use Redis)
const rateLimiter = new RateLimiter(5, 15 * 60 * 1000); // 5 attempts per 15 minutes

// Demo request schema
const demoSchema = z.object({
  name: z.string().min(2),
  email: z.string().email(),
  company: z.string().min(2),
  phone: z.string().optional(),
  employees: z.enum(['1-10', '11-50', '51-200', '201-500', '500+']).optional(),
  preferredDate: z.string().optional(),
  page: z.string().optional(),
  timestamp: z.string().optional(),
});

/**
 * POST /api/forms/demo
 * Handle demo request form submission
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
    const validatedData = demoSchema.parse(body);

    // Sanitize inputs
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      company: sanitizeInput(validatedData.company),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
      employees: validatedData.employees,
      preferredDate: validatedData.preferredDate,
    };

    // TODO: In production, implement:
    // 1. Save demo request to database
    // 2. Send confirmation email to user
    // 3. Send notification email to sales team
    // 4. Create calendar event
    // 5. Log event to analytics

    console.log('Demo request:', {
      ...sanitizedData,
      page: validatedData.page,
      timestamp: validatedData.timestamp,
      ip,
    });

    // Send confirmation email to user
    const userEmailSent = await sendDemoConfirmationEmail(sanitizedData.email);

    // Send notification email to sales team
    const salesEmailSent = await sendDemoNotificationEmail(sanitizedData);

    if (!userEmailSent || !salesEmailSent) {
      console.warn('Email sending failed for demo request');
      // Don't fail the request, just log the warning
    }

    // Return success response
    return NextResponse.json(
      {
        success: true,
        message: 'Demande de démo envoyée! Nous vous contacterons bientôt.',
        data: {
          email: sanitizedData.email,
          name: sanitizedData.name,
          company: sanitizedData.company,
          confirmationSent: true,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    console.error('Demo request error:', error);

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
        message: 'Erreur lors de la demande de démo',
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}

/**
 * Send confirmation email to user
 */
async function sendDemoConfirmationEmail(email: string): Promise<boolean> {
  try {
    // TODO: Implement actual email sending
    console.log('Demo confirmation email would be sent to:', email);
    return true;
  } catch (error) {
    console.error('Email sending error:', error);
    return false;
  }
}

/**
 * Send notification email to sales team
 */
async function sendDemoNotificationEmail(data: Record<string, unknown>): Promise<boolean> {
  try {
    // TODO: Implement actual email sending to sales team
    console.log('Demo notification email would be sent to sales team:', data);
    return true;
  } catch (error) {
    console.error('Email sending error:', error);
    return false;
  }
}
