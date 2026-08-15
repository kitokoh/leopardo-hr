<?php

// Platform admin cockpit i18n keys (SPA admin contract, issue #1764).
return [
    'alert_redis_unreachable' => 'Redis unreachable — cache/queue degraded.',
    'alert_queue_depth' => 'Queue backlog high: :count jobs waiting.',
    'alert_failed_jobs' => ':count failed job(s) — check the queue.',
    'alert_licenses_expiring' => ':count Edge license(s) expiring within 30 days.',
    'alert_trials_expiring' => ':count trial(s) expiring within 7 days.',
    'alert_high_priority_tickets' => ':count high-priority support ticket(s) open.',
    'activity_company_created' => 'New company: :name',
    'activity_support_ticket' => 'Support ticket: :subject',
    'activity_edge_sync' => 'Edge sync: :name',
    'activity_user_signup' => 'New user: :name (:email)',
    'admin_chat_unavailable' => 'The AI assistant is configured per tenant: the platform console cannot answer on behalf of a tenant. Sign in to the tenant workspace to use the assistant.',
    'conversation_not_found' => 'Conversation not found.',
    'conversations_unavailable' => 'Conversations unavailable.',
    'oauth_save_failed' => 'Unable to save the configuration.',
];
