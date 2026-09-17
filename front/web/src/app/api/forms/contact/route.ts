import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { areFormsEnabled, captureMarketingLead, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { evaluateSpam, forbiddenOriginResponse, readSpamSignals, spamResponse } from '../_lib/antispam';
import { RateLimiter, sanitizeEmail, sanitizeInput } from '@/modules/vitrine/lib/validation';

const rateLimiter = new RateLimiter(5, 15 * 60 * 1000);

const contactSchema = z.object({
  name: z.string().min(2).max(100),
  email: z.string().email().max(255),
  // #7594 — le formulaire de contact collecte `company` (champ « Entreprise »,
  // `id="company"`) et l'envoie, mais ce schéma ne le déclarait pas : zod
  // supprime les clés inconnues, donc la valeur était **perdue en silence**
  // avant même d'atteindre le lead. Déclaré ici, et transmis ci-dessous.
  company: z.string().max(100).optional().or(z.literal('')),
  subject: z.string().min(5).max(200),
  message: z.string().min(10).max(5000),
  phone: z.string().max(30).optional().or(z.literal('')),
  locale: z.enum(['fr', 'en', 'ar', 'tr']).optional(),
  page: z.string().max(300).optional(),
  source: z.string().max(120).optional(),
  timestamp: z.string().optional(),
});

export async function POST(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  try {
    const ip = getClientIp(request);

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

    // #7594 — barrières anti-bot AVANT toute validation et toute persistance.
    // Une seule lecture du corps : les signaux sont lus sur le BRUT, car les
    // schémas zod ne déclarent ni le honeypot ni l'horodatage de rendu (zod
    // supprime les clés inconnues). Rejet silencieux : même réponse qu'un
    // succès, mais rien n'est persisté.
    const rawBody: unknown = await request.json();
    const verdict = evaluateSpam(readSpamSignals(rawBody), request);
    if (verdict.spam) {
      return verdict.reason === 'bad-origin' ? forbiddenOriginResponse() : spamResponse();
    }

    const validatedData = contactSchema.parse(rawBody);
    const sanitizedData = {
      name: sanitizeInput(validatedData.name),
      email: sanitizeEmail(validatedData.email),
      company: validatedData.company ? sanitizeInput(validatedData.company) : undefined,
      subject: sanitizeInput(validatedData.subject),
      message: sanitizeInput(validatedData.message),
      phone: validatedData.phone ? sanitizeInput(validatedData.phone) : undefined,
    };

    const lead = await captureMarketingLead(request, {
      type: 'contact',
      email: sanitizedData.email,
      locale: validatedData.locale,
      page: validatedData.page || '/contact',
      source: validatedData.source || 'contact_form',
      timestamp: validatedData.timestamp,
      data: sanitizedData,
    });

    return NextResponse.json(
      {
        success: true,
        message: 'Message envoye. Nous vous repondrons bientot.',
        data: {
          id: lead.id,
          email: sanitizedData.email,
          name: sanitizedData.name,
          subject: sanitizedData.subject,
          confirmationSent: lead.emailForwarded,
          crmForwarded: lead.crmForwarded,
        },
      },
      { status: 201 }
    );
  } catch (error) {
    if (error instanceof z.ZodError) {
      return NextResponse.json(
        {
          success: false,
          message: 'Donnees invalides',
          error: 'VALIDATION_ERROR',
          details: error.issues,
        },
        { status: 400 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: "Erreur lors de l'envoi du message",
        error: 'INTERNAL_SERVER_ERROR',
      },
      { status: 500 }
    );
  }
}
