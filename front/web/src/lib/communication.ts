/**
 * BC-29 COMMUNICATION — types et aides du module côté espace client web
 * (R6, #7691). Miroir strict des schémas OpenAPI `Communication*`
 * (dev-hub/openapi/v1.yaml) : ne consomme QUE l'API tenant
 * `/communication/*` (R1→R5, #7686 → #7690).
 */

import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export interface CommunicationModuleStatus {
  module: string;
  enabled: boolean;
  status: string;
  stage: string;
  capabilities: {
    integrations?: boolean;
    sync?: boolean;
    classification?: boolean;
    follow_ups?: boolean;
    replies?: boolean;
  };
}

export interface CommunicationIntegration {
  id: string;
  provider: string;
  email: string | null;
  scopes: string[];
  status: 'active' | 'revoked' | 'error';
  expires_at: string | null;
  connected_at: string | null;
  revoked_at: string | null;
}

export interface CommunicationThread {
  id: string;
  integration_id: string;
  gmail_thread_id: string;
  subject: string | null;
  snippet: string | null;
  message_count: number;
  last_message_at: string | null;
}

export interface CommunicationMessage {
  id: string;
  gmail_message_id: string;
  from_email: string | null;
  to_emails: string[];
  cc_emails: string[];
  subject: string | null;
  snippet: string | null;
  body: string | null;
  labels: string[];
  sent_at: string | null;
  ai_category: string | null;
  ai_language: string | null;
  ai_sentiment: 'positive' | 'neutral' | 'negative' | null;
  ai_action: 'reply' | 'follow_up' | 'schedule' | 'task' | 'payment' | 'none' | null;
  ai_confidence: number | null;
  classification_status: 'pending' | 'classified' | 'failed';
}

export interface CommunicationCategory {
  id: string;
  key: string;
  label: string;
  custom_label: string | null;
  is_system: boolean;
  active: boolean;
}

export interface CommunicationContactProposal {
  id: string;
  integration_id: string;
  email: string;
  suggested_name: string | null;
  status: 'proposed' | 'accepted' | 'dismissed';
  message_count: number;
  crm_contact_id: number | null;
  decided_at: string | null;
}

export interface CommunicationFollowUpRuleStep {
  position?: number;
  delay_days: number;
  template_key?: string;
}

export interface CommunicationFollowUpRule {
  id: string;
  integration_id: string;
  name: string;
  active: boolean;
  steps: CommunicationFollowUpRuleStep[];
  created_at: string | null;
  updated_at: string | null;
}

export interface CommunicationFollowUpOptOut {
  id: string;
  email: string;
  source: 'manual' | 'unsubscribe';
  created_at: string | null;
}

export type CommunicationReplyPolicyLevel = 'off' | 'draft' | 'confirm' | 'auto';

export interface CommunicationReplyPolicy {
  id: string;
  integration_id: string;
  category_key: string;
  policy: CommunicationReplyPolicyLevel;
  auto_blocked: boolean;
  updated_at: string | null;
}

export interface CommunicationPendingReply {
  id: string;
  integration_id: string;
  thread_id: string;
  message_id: string;
  category_key: string;
  mode: 'draft' | 'confirm' | 'auto';
  to_email: string;
  subject: string | null;
  body: string | null;
  ai_language: string | null;
  ai_confidence: number | null;
  status: 'pending' | 'drafted' | 'sent' | 'rejected' | 'skipped' | 'failed';
  skip_reason: string | null;
  edited_at: string | null;
  decided_at: string | null;
  sent_at: string | null;
  created_at: string | null;
}

export const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

/** La boîte a-t-elle le scope d'envoi (relances R4 / réponses R5) ? */
export function integrationHasSendScope(integration: CommunicationIntegration): boolean {
  return integration.scopes.includes(GMAIL_SEND_SCOPE);
}

/**
 * Traduction du catalogue partagé avec interpolation `:variable`
 * (même convention de placeholders que l'API Laravel).
 */
export function tc(
  locale: AppLocale,
  key: string,
  vars?: Record<string, string | number>,
): string {
  let value = t(locale, `communicationApp.${key}`, key);
  if (vars) {
    for (const [name, replacement] of Object.entries(vars)) {
      value = value.replace(`:${name}`, String(replacement));
    }
  }
  return value;
}

/** Libellé localisé d'une catégorie (taxonomie serveur, déjà localisée). */
export function categoryLabel(
  categories: CommunicationCategory[],
  key: string | null,
  fallback: string,
): string {
  if (!key) {
    return fallback;
  }
  return categories.find((category) => category.key === key)?.label ?? key;
}

/**
 * Mappe un code d'erreur machine du lot R5 vers une clé i18n
 * `communicationApp.policies.errors.*` (422 de POST /reply-policies).
 */
export function policyErrorKey(code: string | undefined): string {
  switch (code) {
    case 'REPLY_AUTO_CATEGORY_BLOCKED':
      return 'policies.errors.autoCategoryBlocked';
    case 'GMAIL_SEND_SCOPE_REQUIRED':
      return 'policies.errors.sendScopeRequired';
    case 'GMAIL_COMPOSE_SCOPE_REQUIRED':
      return 'policies.errors.composeScopeRequired';
    case 'REPLY_CATEGORY_UNKNOWN':
      return 'policies.errors.categoryUnknown';
    default:
      return 'policies.saveError';
  }
}
