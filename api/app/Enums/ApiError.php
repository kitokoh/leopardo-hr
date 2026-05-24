<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\JsonResponse;

enum ApiError: string
{
    // Authentication (401)
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case TOKEN_INVALID = 'TOKEN_INVALID';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case TWO_FA_REQUIRED = 'TWO_FA_REQUIRED';
    case TWO_FA_INVALID = 'TWO_FA_INVALID';
    case ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    case ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';

    // Authorization (403)
    case FORBIDDEN = 'FORBIDDEN';
    case MANAGER_REQUIRED = 'MANAGER_REQUIRED';
    case INSUFFICIENT_ROLE = 'INSUFFICIENT_ROLE';
    case TENANT_MISMATCH = 'TENANT_MISMATCH';
    case SUPER_ADMIN_REQUIRED = 'SUPER_ADMIN_REQUIRED';
    case POLICY_DENIED = 'POLICY_DENIED';

    // Not Found (404)
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    case EMPLOYEE_NOT_FOUND = 'EMPLOYEE_NOT_FOUND';
    case COMPANY_NOT_FOUND = 'COMPANY_NOT_FOUND';
    case USER_NOT_FOUND = 'USER_NOT_FOUND';

    // Validation (422)
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case INVALID_STATUS_TRANSITION = 'INVALID_STATUS_TRANSITION';
    case DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    case INVALID_DATE_RANGE = 'INVALID_DATE_RANGE';
    case INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';

    // Business Logic (409/422)
    case ALREADY_APPROVED = 'ALREADY_APPROVED';
    case ALREADY_REJECTED = 'ALREADY_REJECTED';
    case ALREADY_LINKED = 'ALREADY_LINKED';
    case ALREADY_ENABLED = 'ALREADY_ENABLED';
    case CONTRACT_EXPIRED = 'CONTRACT_EXPIRED';
    case PAYROLL_ALREADY_VALIDATED = 'PAYROLL_ALREADY_VALIDATED';
    case SUBSCRIPTION_INACTIVE = 'SUBSCRIPTION_INACTIVE';
    case TRIAL_EXPIRED = 'TRIAL_EXPIRED';
    case INVITATION_ALREADY_ACCEPTED = 'INVITATION_ALREADY_ACCEPTED';
    case TOO_MANY_PENDING_REQUESTS = 'TOO_MANY_PENDING_REQUESTS';
    case SHARE_EXPIRED = 'SHARE_EXPIRED';

    // Passwords
    case INVALID_CURRENT_PASSWORD = 'INVALID_CURRENT_PASSWORD';
    case INVALID_PASSWORD = 'INVALID_PASSWORD';
    case SETUP_REQUIRED = 'SETUP_REQUIRED';

    // Rate Limiting (429)
    case RATE_LIMITED = 'RATE_LIMITED';

    // Server (500)
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case PDF_GENERATION_FAILED = 'PDF_GENERATION_FAILED';
    case EXPORT_FAILED = 'EXPORT_FAILED';
    case EXTERNAL_SERVICE_ERROR = 'EXTERNAL_SERVICE_ERROR';

    public function status(): int
    {
        return match ($this) {
            self::INVALID_CREDENTIALS, self::TOKEN_EXPIRED, self::TOKEN_INVALID,
            self::UNAUTHENTICATED, self::TWO_FA_REQUIRED, self::TWO_FA_INVALID,
            self::ACCOUNT_LOCKED, self::ACCOUNT_SUSPENDED => 401,

            self::FORBIDDEN, self::MANAGER_REQUIRED, self::INSUFFICIENT_ROLE,
            self::TENANT_MISMATCH, self::SUPER_ADMIN_REQUIRED, self::POLICY_DENIED => 403,

            self::RESOURCE_NOT_FOUND, self::EMPLOYEE_NOT_FOUND,
            self::COMPANY_NOT_FOUND, self::USER_NOT_FOUND => 404,

            self::ALREADY_APPROVED, self::ALREADY_REJECTED, self::ALREADY_LINKED,
            self::ALREADY_ENABLED, self::DUPLICATE_ENTRY => 409,

            self::VALIDATION_FAILED, self::INVALID_STATUS_TRANSITION,
            self::INVALID_DATE_RANGE, self::INSUFFICIENT_BALANCE,
            self::CONTRACT_EXPIRED, self::PAYROLL_ALREADY_VALIDATED,
            self::SUBSCRIPTION_INACTIVE, self::TRIAL_EXPIRED,
            self::INVITATION_ALREADY_ACCEPTED, self::TOO_MANY_PENDING_REQUESTS,
            self::SHARE_EXPIRED, self::INVALID_CURRENT_PASSWORD,
            self::INVALID_PASSWORD, self::SETUP_REQUIRED => 422,

            self::RATE_LIMITED => 429,

            self::INTERNAL_ERROR, self::SERVICE_UNAVAILABLE,
            self::PDF_GENERATION_FAILED, self::EXPORT_FAILED,
            self::EXTERNAL_SERVICE_ERROR => 500,
        };
    }

    public function message(): string
    {
        return __("api_errors.{$this->value}", [], app()->getLocale())
            ?: $this->defaultMessage();
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::INVALID_CREDENTIALS => 'Invalid email or password.',
            self::TOKEN_EXPIRED => 'Authentication token has expired.',
            self::TOKEN_INVALID => 'Authentication token is invalid.',
            self::UNAUTHENTICATED => 'Authentication required.',
            self::TWO_FA_REQUIRED => 'Two-factor authentication is required.',
            self::TWO_FA_INVALID => 'Invalid two-factor code.',
            self::ACCOUNT_LOCKED => 'Account is locked due to too many failed attempts.',
            self::ACCOUNT_SUSPENDED => 'Account has been suspended.',
            self::FORBIDDEN => 'You do not have permission to perform this action.',
            self::MANAGER_REQUIRED => 'This endpoint requires manager access.',
            self::INSUFFICIENT_ROLE => 'Your role does not have access to this resource.',
            self::TENANT_MISMATCH => 'Resource does not belong to your organization.',
            self::SUPER_ADMIN_REQUIRED => 'Platform administrator access required.',
            self::POLICY_DENIED => 'You are not authorized for this action on this resource.',
            self::RESOURCE_NOT_FOUND => 'The requested resource was not found.',
            self::EMPLOYEE_NOT_FOUND => 'Employee not found.',
            self::COMPANY_NOT_FOUND => 'Company not found.',
            self::USER_NOT_FOUND => 'User not found.',
            self::VALIDATION_FAILED => 'The given data was invalid.',
            self::INVALID_STATUS_TRANSITION => 'This status transition is not allowed.',
            self::DUPLICATE_ENTRY => 'A similar entry already exists.',
            self::INVALID_DATE_RANGE => 'The date range is invalid.',
            self::INSUFFICIENT_BALANCE => 'Insufficient balance for this operation.',
            self::ALREADY_APPROVED => 'This request has already been approved.',
            self::ALREADY_REJECTED => 'This request has already been rejected.',
            self::ALREADY_LINKED => 'This link already exists.',
            self::ALREADY_ENABLED => 'This feature is already enabled.',
            self::CONTRACT_EXPIRED => 'The contract has expired.',
            self::PAYROLL_ALREADY_VALIDATED => 'This payroll run has already been validated.',
            self::SUBSCRIPTION_INACTIVE => 'Your subscription is not active.',
            self::TRIAL_EXPIRED => 'Your trial period has expired.',
            self::INVITATION_ALREADY_ACCEPTED => 'This invitation has already been accepted.',
            self::TOO_MANY_PENDING_REQUESTS => 'Too many pending requests.',
            self::SHARE_EXPIRED => 'This share link has expired.',
            self::INVALID_CURRENT_PASSWORD => 'The current password is incorrect.',
            self::INVALID_PASSWORD => 'The password is invalid.',
            self::SETUP_REQUIRED => 'Initial setup is required before proceeding.',
            self::RATE_LIMITED => 'Too many requests. Please try again later.',
            self::INTERNAL_ERROR => 'An internal error occurred.',
            self::SERVICE_UNAVAILABLE => 'Service temporarily unavailable.',
            self::PDF_GENERATION_FAILED => 'PDF generation failed.',
            self::EXPORT_FAILED => 'Export generation failed.',
            self::EXTERNAL_SERVICE_ERROR => 'External service error.',
        };
    }

    /** @param array<string, mixed> $extra */
    public function response(array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'error' => $this->value,
            'message' => $this->message(),
        ], $extra), $this->status());
    }
}
