import {
  SignupFormData,
  DemoFormData,
  ContactFormData,
  NewsletterFormData,
  sanitizeInput,
  sanitizeEmail,
} from "./validation";

const safeLog = (..._args: unknown[]) => {};

function getBrowserLocale(): string {
  if (typeof document === "undefined") {
    return "fr";
  }

  return document.documentElement.lang || "fr";
}

function getSearchMetadata(): Record<string, string> {
  if (typeof window === "undefined") {
    return {};
  }

  const params = new URLSearchParams(window.location.search);
  const metadata: Record<string, string> = {};

  ["plan", "module", "utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"].forEach((key) => {
    const value = params.get(key);

    if (value) {
      metadata[key] = value;
    }
  });

  return metadata;
}

/**
 * Form submission handlers
 */

export interface FormSubmissionResponse {
  success: boolean;
  message: string;
  data?: any;
  error?: string;
  /**
   * Present when the backend accepted the request but could not reach the
   * OTP/trial provisioning API (e.g. cold-start timeout). `false` means the
   * lead was captured and the team will follow up manually; the caller must
   * NOT treat this the same as a normal OTP-sent success.
   */
  provisioned?: boolean;
  /**
   * #2469 : jeton de suivi du provisioning de l'essai guidé (64 caractères,
   * émis par POST /api/v1/trial/signup). Permet de poller GET /trial/status
   * via le proxy same-origin /api/forms/trial-status sans exposer l'email.
   */
  provisioningToken?: string;
}

/**
 * Submit signup form
 */
export async function submitSignupForm(
  data: SignupFormData,
  page: string
): Promise<FormSubmissionResponse> {
  try {
    const sanitizedData = {
      email: sanitizeEmail(data.email),
      company: sanitizeInput(data.company),
      role: data.role,
      employees: data.employees,
      phone: data.phone ? sanitizeInput(data.phone) : undefined,
    };

    const response = await fetch("/api/forms/signup", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...sanitizedData,
        ...getSearchMetadata(),
        locale: getBrowserLocale(),
        source: "signup_form",
        page,
        timestamp: new Date().toISOString(),
        requestedWorkflow: "guided_trial",
        nextStep: "contact_under_24h"
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: error.message || "Erreur lors de la demande d'essai",
        error: error.error || error.message,
        data: error.data,
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: result.message || "Code de verification envoye.",
      data: result.data,
      provisioned: result.provisioned !== false,
      // #2469 : token de suivi du provisioning (essai guidé), stocké en
      // sessionStorage par SignupForm pour le polling GET /trial/status.
      provisioningToken: result.data?.provisioningToken,
    };
  } catch (error) {
    safeLog("Signup form error:", error);
    return {
      success: false,
      message:
        error instanceof Error && error.name === 'AbortError'
          ? 'Le serveur met du temps a repondre. Veuillez reessayer dans quelques instants.'
          : "Erreur lors de la demande d'essai",
      error: error instanceof Error ? error.message : "Erreur inconnue",
    };
  }
}

/**
 * Submit OTP verification to complete trial provisioning
 */
export async function submitVerifyForm(
  email: string,
  code: string
): Promise<FormSubmissionResponse> {
  try {
    const response = await fetch("/api/forms/verify", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ email, code }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: error.message || "Code invalide ou expire.",
        error: error.error || "VERIFICATION_FAILED",
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: result.message || "Votre espace Leopardo est pret !",
      data: result.data,
    };
  } catch (error) {
    safeLog("Verify form error:", error);
    return {
      success: false,
      message: "Erreur lors de la verification",
      error: error instanceof Error ? error.message : "Erreur inconnue",
    };
  }
}

/**
 * Trial provisioning status (guided trial, #2469)
 *
 * Proxy same-origin GET /api/forms/trial-status → backend
 * GET /api/v1/trial/status?token=... Le token ne quitte jamais le client
 * autrement que par ce proxy (jamais d'email ni d'URL visible).
 */
export interface TrialStatus {
  status: 'pending' | 'ready' | 'failed';
  login_url?: string;
}

export async function fetchTrialStatus(token: string): Promise<
  | { success: true; data: TrialStatus }
  | { success: false; error?: string; message?: string }
> {
  try {
    const response = await fetch(
      `/api/forms/trial-status?token=${encodeURIComponent(token)}`,
      {
        method: 'GET',
        headers: { Accept: 'application/json' },
      }
    );

    const result = await response.json();

    if (response.ok && result.success) {
      return {
        success: true,
        data: {
          status: result.data?.status ?? 'pending',
          login_url: result.data?.login_url,
        },
      };
    }

    return {
      success: false,
      error: result.error || 'TRIAL_STATUS_ERROR',
      message: result.message,
    };
  } catch (error) {
    safeLog("Trial status error:", error);
    return {
      success: false,
      error: error instanceof Error ? error.name : 'NETWORK_ERROR',
    };
  }
}
/**
 * Submit demo request form
 */
export async function submitDemoForm(
  data: DemoFormData,
  page: string
): Promise<FormSubmissionResponse> {
  try {
    // Sanitize inputs
    const sanitizedData = {
      name: sanitizeInput(data.name),
      email: sanitizeEmail(data.email),
      company: sanitizeInput(data.company),
      phone: data.phone ? sanitizeInput(data.phone) : undefined,
      employees: data.employees,
      preferredDate: data.preferredDate,
    };

    // Send to API
    const response = await fetch("/api/forms/demo", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...sanitizedData,
        ...getSearchMetadata(),
        locale: getBrowserLocale(),
        source: "demo_form",
        page,
        timestamp: new Date().toISOString(),
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: "Erreur lors de la demande de démo",
        error: error.message,
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: "Demande de démo envoyée! Nous vous contacterons bientôt.",
      data: result,
    };
  } catch (error) {
    safeLog("Demo form error:", error);
    return {
      success: false,
      message: "Erreur lors de la demande de démo",
      error: error instanceof Error ? error.message : "Erreur inconnue",
    };
  }
}

/**
 * Submit contact form
 */
export async function submitContactForm(
  data: ContactFormData,
  page: string
): Promise<FormSubmissionResponse> {
  try {
    // Sanitize inputs
    const sanitizedData = {
      name: sanitizeInput(data.name),
      email: sanitizeEmail(data.email),
      subject: sanitizeInput(data.subject),
      message: sanitizeInput(data.message),
      phone: data.phone ? sanitizeInput(data.phone) : undefined,
    };

    // Send to API
    const response = await fetch("/api/forms/contact", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...sanitizedData,
        ...getSearchMetadata(),
        locale: getBrowserLocale(),
        source: "contact_form",
        page,
        timestamp: new Date().toISOString(),
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: "Erreur lors de l'envoi du message",
        error: error.message,
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: "Message envoyé! Nous vous répondrons bientôt.",
      data: result,
    };
  } catch (error) {
    safeLog("Contact form error:", error);
    return {
      success: false,
      message: "Erreur lors de l'envoi du message",
      error: error instanceof Error ? error.message : "Erreur inconnue",
    };
  }
}

/**
 * Submit newsletter form
 */
export async function submitNewsletterForm(
  data: NewsletterFormData,
  page: string
): Promise<FormSubmissionResponse> {
  try {
    // Sanitize inputs
    const sanitizedData = {
      email: sanitizeEmail(data.email),
    };

    // Send to API
    const response = await fetch("/api/forms/newsletter", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...sanitizedData,
        ...getSearchMetadata(),
        locale: getBrowserLocale(),
        source: "newsletter_form",
        page,
        timestamp: new Date().toISOString(),
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: "Erreur lors de l'inscription à la newsletter",
        error: error.message,
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: "Inscription à la newsletter réussie!",
      data: result,
    };
  } catch (error) {
    safeLog("Newsletter form error:", error);
    return {
      success: false,
      message: "Erreur lors de l'inscription à la newsletter",
      error: error instanceof Error ? error.message : "Erreur inconnue",
    };
  }
}

/**
 * Form state management helper
 */
export interface FormState {
  isSubmitting: boolean;
  isSuccess: boolean;
  isError: boolean;
  message: string;
  errors: Record<string, string>;
}

export const initialFormState: FormState = {
  isSubmitting: false,
  isSuccess: false,
  isError: false,
  message: "",
  errors: {},
};

export function createFormReducer() {
  return (state: FormState, action: any): FormState => {
    switch (action.type) {
      case "SUBMIT_START":
        return {
          ...state,
          isSubmitting: true,
          isSuccess: false,
          isError: false,
          message: "",
          errors: {},
        };
      case "SUBMIT_SUCCESS":
        return {
          ...state,
          isSubmitting: false,
          isSuccess: true,
          isError: false,
          message: action.payload.message,
          errors: {},
        };
      case "SUBMIT_ERROR":
        return {
          ...state,
          isSubmitting: false,
          isSuccess: false,
          isError: true,
          message: action.payload.message,
          errors: action.payload.errors || {},

        };
      case "RESET":
        return initialFormState;
      default:
        return state;
    }
  };
}
