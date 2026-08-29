<?php

return [
    // CRM communication channels (issues #5725/#5727)
    'CRM_CHANNEL_NOT_FOUND' => 'CRM channel not found in the current tenant.',
    'CRM_CHANNEL_TYPE_INVALID' => 'Unknown CRM channel type.',
    'CRM_CHANNEL_NOT_CONFIGURED' => 'CRM channel is active but not configured (missing token/provider).',
    'CRM_CONSENT_REQUIRED' => 'Communication consent required for this contact, channel and purpose.',
    'CRM_QUOTA_EXCEEDED' => 'Monthly channel quota exceeded for this tenant.',
    'CRM_PROVIDER_ERROR' => 'The channel provider returned an error.',
    'CRM_WEBHOOK_SIGNATURE_INVALID' => 'Invalid CRM webhook signature.',
    'CRM_WEBHOOK_NOT_CONFIGURED' => 'CRM webhook not configured (missing secret).',
    'CRM_WEBHOOK_VERIFY_INVALID' => 'CRM webhook subscription verification refused.',

    'merge' => [
        'unknown_entity' => 'Unknown entity (accounts, contacts or leads).',
    ],

    'CRM_AUTOMATION_NOT_FOUND' => 'CRM automation not found in the current tenant.',
    'CRM_AUTOMATION_INVALID_TRIGGER' => 'Unknown CRM automation trigger event.',
    'CRM_AUTOMATION_EMERGENCY_STOPPED' => 'CRM automations emergency-stopped for this tenant.',
    'CRM_AUTOMATION_INVALID' => 'Invalid CRM automation (rule or action not allowlisted).',

];
