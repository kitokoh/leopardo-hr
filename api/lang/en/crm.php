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
    'CRM_EXPORT_NOT_FOUND' => 'CRM export job not found in the current tenant.',
    'CRM_EXPORT_NOT_READY' => 'CRM export not ready yet (processing) or failed.',
    'CRM_EXPORT_EXPIRED' => 'CRM export expired — generate a new export.',
    'CRM_EXPORT_ENTITY_UNAVAILABLE' => 'CRM export entity unavailable (V0 base not merged yet on this environment).',
    'CRM_EXPORT_INVALID_REQUEST' => 'Invalid CRM export request (entity or column not allowlisted).',
    'CRM_EXPORT_FAILED' => 'CRM export generation failed.',
    'CRM_EXPORT_ENTITY_INVALID' => 'Unknown CRM export entity.',

    'merge' => [
        'unknown_entity' => 'Unknown entity (accounts, contacts or leads).',
    ],];
