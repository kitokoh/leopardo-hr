<?php

return [
    // Auth
    'INVALID_CREDENTIALS' => 'Invalid email or password.',
    'ACCOUNT_SUSPENDED' => 'Your account has been suspended. Contact your manager.',
    'ACCOUNT_ARCHIVED' => 'This account is archived.',
    'TOKEN_EXPIRED' => 'Your session has expired. Please log in again.',
    'TOO_MANY_ATTEMPTS' => 'Too many attempts. Try again in :minutes minutes.',
    'EMPLOYEE_NOT_ACTIVE' => 'This employee account is not active.',
    'COMPANY_NOT_FOUND' => 'Company not found.',
    'INVALID_CURRENT_PASSWORD' => 'The current password is incorrect.',
    'UNAUTHENTICATED' => 'Authentication required.',

    // Attendance
    'ALREADY_CHECKED_IN' => 'You have already checked in today.',
    'MISSING_CHECK_IN' => 'Please check in first before checking out.',
    'ALREADY_CHECKED_OUT' => 'You have already checked out today.',
    'PUNCH_PHOTO_REQUIRED' => 'A photo is required to punch in or out at your company.',

    // Finance
    'PLAN_CAMERAS_REQUIRED' => 'Your plan does not include the cameras module. Upgrade to Business.',
    'MAX_CAMERAS_REACHED' => 'Camera limit of :limit reached for your plan.',
    'PLAN_FINANCE_REQUIRED' => 'Your plan does not include the finance module.',
    'FINANCE_MAX_DOCS_REACHED' => 'Document limit of :limit reached this month.',
    'INVOICE_ALREADY_SENT' => 'This invoice has already been sent and cannot be modified.',

    // Invitations
    'INVITATION_ALREADY_ACCEPTED' => 'This invitation has already been accepted.',
    'INVITATION_EXPIRED' => 'This invitation has expired.',
    'INVITATION_NOT_FOUND' => 'Invitation not found.',

    // Biometric
    'CAMERA_TOKEN_EXPIRED' => 'Access to this camera has expired.',
    'CAMERA_TOKEN_REVOKED' => 'This access has been revoked.',

    // Payroll
    'PAYROLL_BALANCE_UNAVAILABLE' => 'Employee balance is temporarily unavailable. Please try again in a moment.',
    // General
    'NOT_FOUND' => 'Resource not found.',
    'FORBIDDEN' => 'You do not have permission for this action.',
    'SERVER_ERROR' => 'An error occurred. Please try again.',
    'VALIDATION_ERROR' => 'Some fields are incorrect.',
    'BAD_REQUEST' => 'Bad request.',
    'CONFLICT' => 'A data conflict prevents this operation.',
    'VALIDATION_FAILED' => 'Some fields are incorrect.',
    'TOO_MANY_REQUESTS' => 'Too many requests. Try again later.',
    'SERVICE_UNAVAILABLE' => 'Service temporarily unavailable.',
    'HTTP_ERROR' => 'An error occurred. Please try again.',
    'UNSUPPORTED_API_VERSION' => 'Unsupported API version.',

    // #3810 — codes stables (audit 2026-08-15) : plus de message brut exposé
    'PAYROLL_RUN_VALIDATION_FAILED' => 'Payroll run validation failed. Please retry or contact support.',
    'PAYROLL_RUN_LOCK_FAILED' => 'Payroll run lock failed. Please retry.',
    'PAYROLL_RUN_UNLOCK_FAILED' => 'Payroll run unlock failed. Please retry.',
    'PAYROLL_REGULARIZATION_FAILED' => 'Regularization run creation failed. Please retry.',
    'SAML_AUTH_FAILED' => 'SAML authentication failed. Please retry or contact your administrator.',
    'OIDC_AUTHORIZE_FAILED' => 'OIDC sign-in could not start. Please retry.',
    'OIDC_CALLBACK_FAILED' => 'OIDC sign-in could not complete. Please retry.',
    'ANNOUNCEMENT_PUBLISH_FAILED' => 'Announcement could not be published. Please retry.',
    'ANNOUNCEMENT_CANCEL_FAILED' => 'Announcement could not be cancelled. Please retry.',
    'RATE_APPROVAL_FAILED' => 'Rate approval failed. Check the row state and retry.',
    'RATE_REJECTION_FAILED' => 'Rate rejection failed. Check the row state and retry.',
    'SOCIAL_CONTRIBUTION_SUBMIT_FAILED' => 'Contribution submission failed. Check the row state and retry.',
    'TAX_SLAB_SUBMIT_FAILED' => 'Tax slab submission failed. Check the row state and retry.',

    'PAYMENT_SESSION_FAILED' => 'Unable to create the payment session.',
    'NO_PAYMENT_ACCOUNT' => 'No associated payment account. Subscribe to a plan first.',
    'VERIFICATION_CODE_SENT' => 'Verification code sent.',
    'VERIFICATION_TEMPORARILY_UNAVAILABLE' => 'Verification of your request is temporarily unavailable. Please try again shortly.',
    'TRIAL_SPACE_READY' => 'Your Leopardo workspace is ready!',
    'SESSION_ALREADY_OPEN' => 'A session is already open for this employee.',
    'OUTSIDE_GEOFENCE' => 'Position outside the attendance zone.',
    'ATTENDANCE_MODE_PERSONALIZATION_DISABLED' => 'Attendance mode personalization is disabled.',
    'PREFERENCE_UPDATED' => 'Preference updated.',
    'CONFIG_UPDATED' => 'Configuration updated.',

    // #4312/#4313/#4314 — FR résiduels localisés (vague expert20)
    'PAYOUT_REQUEST_REFUSED' => 'Payout request refused.',
    'PAYOUT_REQUEST_FAILED' => 'An error occurred while requesting the payout.',
    'COMPANY_MODE_FORCED' => 'Your company enforces a clocking mode. You cannot change it.',
    'GPS_CONSENT_REQUIRED' => 'GPS consent is required to enable automatic clocking.',
    'PAYMENT_BATCH_RUN_INVALID' => 'The payroll cycle must be calculated or validated before creating a payment batch.',
    'PAYMENT_BATCH_CREATED' => 'Bulk payment declared. Employee confirmations and documents are processed in the background.',
    'ARCHIVED_DOCUMENT_NOT_FOUND' => 'Archived document not found.',
    'TOO_MANY_PENDING_REQUESTS' => 'You already have 3 pending requests.',
    'SAML_RESPONSE_MISSING' => 'SAMLResponse missing.',
    'SAML_ASSERTION_RECEIVED' => 'SAML assertion received.',
    'OIDC_CODE_MISSING' => 'Code or id_token missing.',
    'OIDC_LOGIN_SUCCESS' => 'OIDC login successful.',
    'TENANT_COUNTRY_REQUIRED' => 'The tenant legal country is required and must be supported before this operation.',
    'TENANT_COUNTRY_INVALID' => 'Tenant country missing or unsupported (:country).',
    'FILE_TOO_LARGE' => 'The uploaded file exceeds the maximum allowed size.',
    'CONTRACT_ACTIVATION_INVALID_STATE' => 'Only draft contracts can be activated.',
    'CONTRACT_SUSPENSION_INVALID_STATE' => 'Only active contracts can be suspended.',
    'CONTRACT_TERMINATION_INVALID_STATE' => 'Contract must be active or suspended to terminate.',

    // Trial signup (audit #4395)
    'ALREADY_PROCESSED' => 'This trial request has already been processed.',
    'INVALID_OR_EXPIRED_CODE' => 'Invalid or expired verification code.',
    'EMAIL_ALREADY_REGISTERED' => 'An account with this email already exists. Sign in directly.',
    'INVALID_COUNTRY' => 'The signup country is invalid or unsupported. Please restart the signup.',
    'NO_PLAN_AVAILABLE' => 'The trial service is temporarily unavailable.',
    'PROVISIONING_FAILED' => 'Error creating your workspace. Please try again.',
];
