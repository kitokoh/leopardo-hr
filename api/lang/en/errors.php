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

    // General
    'NOT_FOUND' => 'Resource not found.',
    'FORBIDDEN' => 'You do not have permission for this action.',
    'SERVER_ERROR' => 'An error occurred. Please try again.',
    'VALIDATION_ERROR' => 'Some fields are incorrect.',
    'UNSUPPORTED_API_VERSION' => 'Unsupported API version.',
];
