@php
    $__locale = $locale ?? app()->getLocale();
    $__dir = \App\Support\I18nCatalog::isRtl($__locale) ? 'rtl' : 'ltr';
    $__roleLabel = $employee->manager_role
        ? \App\Modules\HR\Infrastructure\Services\RoleInvitationService::getRoleLabel($employee->manager_role)
        : ($employee->role === 'manager' ? __('employees.role_manager') : __('employees.role_employee'));
@endphp
<div dir="{{ $__dir }}" style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2>{{ __('emails.user_invitation_title') }}</h2>
    <p>{{ str_replace(':name', trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')), trans('emails.user_invitation_greeting')) }}</p>
    <p>{!! str_replace(':company', '<strong>'.e($company->name).'</strong>', e(trans('emails.user_invitation_intro'))) !!}</p>
    <p>{!! str_replace(':role', '<strong>'.e($__roleLabel).'</strong>', e(trans('emails.user_invitation_role_line'))) !!}</p>
    <p>{!! str_replace(':email', '<strong>'.e($employee->email).'</strong>', e(trans('emails.user_invitation_email_line'))) !!}</p>
    <p>{!! str_replace(':invitedBy', '<strong>'.e($invitedByEmail).'</strong>', e(trans('emails.user_invitation_invited_by_line'))) !!}</p>
    <p>{!! str_replace([':city', ':country'], ['<strong>'.e($company->city).'</strong>', '<strong>'.e($company->country).'</strong>'], e(trans('emails.user_invitation_location_line'))) !!}</p>
    <p>{!! str_replace([':language', ':timezone'], ['<strong>'.e(strtoupper($company->language ?? 'fr')).'</strong>', '<strong>'.e($company->timezone ?? 'Africa/Algiers').'</strong>'], e(trans('emails.user_invitation_locale_line'))) !!}</p>
    <p>{{ __('emails.user_invitation_next_step') }}</p>
    <p>
        {{ __('emails.user_invitation_activate_line') }}
        <a href="{{ $activationUrl }}">{{ $activationUrl }}</a>
    </p>
    <p>{{ __('emails.user_invitation_expiry') }}</p>
    <p>{{ __('emails.user_invitation_footer') }}</p>
</div>
