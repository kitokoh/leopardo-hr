import { FormSubmission } from "./analytics";
import {
  SignupFormData,
  DemoFormData,
  ContactFormData,
  NewsletterFormData,
  sanitizeInput,
  sanitizeEmail,
} from "./validation";

const safeLog = (..._args: unknown[]) => {};

/**
 * Form submission handlers
 */

export interface FormSubmissionResponse {
  success: boolean;
  message: string;
  data?: any;
  error?: string;
}

/**
 * Submit signup form
 */
export async function submitSignupForm(
  data: SignupFormData,
  page: string
): Promise<FormSubmissionResponse> {
  try {
    // Sanitize inputs
    const sanitizedData = {
      email: sanitizeEmail(data.email),
      password: data.password,
    };

    // Send to API
    const response = await fetch("/api/forms/signup", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ...sanitizedData,
        page,
        timestamp: new Date().toISOString(),
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return {
        success: false,
        message: "Erreur lors de l'inscription",
        error: error.message,
      };
    }

    const result = await response.json();

    return {
      success: true,
      message: "Inscription réussie! Vérifiez votre email.",
      data: result,
    };
  } catch (error) {
    safeLog("Signup form error:", error);
    return {
      success: false,
      message: "Erreur lors de l'inscription",
      error: error instanceof Error ? error.message : "Erreur inconnue",
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
 * Get form submission from data
 */
export function getFormSubmission(
  type: "signup" | "demo" | "contact" | "newsletter",
  data: any,
  page: string
): FormSubmission {
  return {
    id: `${type}-${Date.now()}`,
    type,
    email: data.email,
    name: data.name,
    company: data.company,
    message: data.message,
    timestamp: new Date(),
    page,
  };
}

/**
 * Form submission tracking
 */
export async function trackFormSubmission(
  submission: FormSubmission
): Promise<void> {
  try {
    await fetch("/api/analytics/track", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(submission),
    });
  } catch (error) {
    safeLog("Form submission tracking error:", error);
  }
}

/**
 * CSRF token management
 */
export async function getCSRFToken(): Promise<string> {
  try {
    const response = await fetch("/api/csrf-token");
    const data = await response.json();
    return data.token;
  } catch (error) {
    safeLog("CSRF token error:", error);
    return "";
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