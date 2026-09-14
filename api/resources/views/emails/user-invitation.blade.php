@php
    $__locale = $locale ?? app()->getLocale();
    $__roleLabel = $employee->manager_role
        ? \App\Modules\HR\Infrastructure\Services\RoleInvitationService::getRoleLabel($employee->manager_role)
        : ($employee->role === 'manager' ? __('employees.role_manager') : __('employees.role_employee'));
@endphp
{{--
    Invitation d'un collaborateur (issue #7346).

    Migré du layout `premium` vers le layout canonique. Le pied de page et
    l'en-tête viennent désormais du layout : ce template ne porte plus que son
    corps. Les variables `:role`, `:email`, `:company`… restent rendues en gras
    via des substitutions échappées (`e()`), jamais de HTML brut concaténé.
--}}
@extends('emails.layouts.base')

@section('heading', __('emails.user_invitation_title'))

@section('content')
    <p style="margin:0 0 16px 0;">
        {{ str_replace(':name', trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')), trans('emails.user_invitation_greeting')) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin:0 0 20px 0;">
        <tr>
            <td style="padding:16px 18px; font-size:14px; line-height:23px;">
                <p style="margin:0 0 8px 0;">{!! str_replace(':company', '<strong>'.e($company->name).'</strong>', e(trans('emails.user_invitation_intro'))) !!}</p>
                <p style="margin:0 0 8px 0;">{!! str_replace(':role', '<strong>'.e($__roleLabel).'</strong>', e(trans('emails.user_invitation_role_line'))) !!}</p>
                <p style="margin:0 0 8px 0;">{!! str_replace(':email', '<strong>'.e($employee->email).'</strong>', e(trans('emails.user_invitation_email_line'))) !!}</p>
                <p style="margin:0 0 8px 0;">{!! str_replace(':invitedBy', '<strong>'.e($invitedByEmail).'</strong>', e(trans('emails.user_invitation_invited_by_line'))) !!}</p>
                <p style="margin:0 0 8px 0;">{!! str_replace([':city', ':country'], ['<strong>'.e($company->city).'</strong>', '<strong>'.e($company->country).'</strong>'], e(trans('emails.user_invitation_location_line'))) !!}</p>
                <p style="margin:0;">{!! str_replace([':language', ':timezone'], ['<strong>'.e(strtoupper($company->language ?? 'fr')).'</strong>', '<strong>'.e($company->timezone ?? 'Africa/Algiers').'</strong>'], e(trans('emails.user_invitation_locale_line'))) !!}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px 0;">{{ __('emails.user_invitation_next_step') }}</p>

    @include('emails.partials.button', [
        'url' => $activationUrl,
        'label' => __('emails.user_invitation_activate_line'),
        'align' => 'center',
    ])

    <p style="margin:0 0 8px 0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.user_invitation_expiry') }}</p>
    <p style="margin:0; font-size:13px; line-height:20px; color:#64748b;">{{ __('emails.user_invitation_footer') }}</p>
@endsection
