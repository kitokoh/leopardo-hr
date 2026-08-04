<?php

return [
    // Subjects
    'invoice_subject' => 'Invoice :number — :company',
    'welcome_subject' => 'Welcome to :company',
    'password_reset_subject' => 'Reset your password',
    'morning_brief_subject' => 'Morning brief — :date',
    'invitation_subject' => 'You are invited to join :company on Leopardo RH',
    'role_assignment_subject' => 'Leopardo RH — You have been appointed :role',

    // Body
    'invitation_body' => 'Hello :name, you have been invited to join :company. Click the link below to activate your account.',
    'invitation_button' => 'Activate my account',
    'welcome_body' => 'Welcome :name! Your account has been created successfully on Leopardo RH.',
    'password_reset_body' => 'Click the link below to reset your password.',
    'password_reset_button' => 'Reset password',

    // Role assignment
    'role_assignment_heading' => 'New role assigned',
    'role_assignment_greeting' => 'Hello :name,',
    'role_assignment_body' => ':assignedBy assigned you the role of :role within :company.',
    'role_assignment_app_title' => 'Download your dedicated app',
    'role_assignment_app_body' => 'As :role, you have access to the :appName app.',
    'role_assignment_android' => 'Android — Google Play',
    'role_assignment_ios' => 'iOS — App Store',
    'role_assignment_web_note' => 'You can also log in from the web at :url with your usual email.',
    'role_label_principal' => 'Company administrator',
    'role_label_rh' => 'HR manager',
    'role_label_comptable' => 'Accounting manager',
    'role_label_marketing' => 'Marketing manager',
    'role_label_dept' => 'Department head',
    'role_label_default' => 'Manager',

    // User invitation (account activation)
    'user_invitation_subject' => 'Welcome to Leopardo RH - Activate your account',
    'user_invitation_title' => 'Welcome to Leopardo RH',
    'user_invitation_greeting' => 'Hello :name,',
    'user_invitation_intro' => 'Your account has been prepared for the company :company.',
    'user_invitation_role_line' => 'Role: :role',
    'user_invitation_email_line' => 'Login email: :email',
    'user_invitation_invited_by_line' => 'Invitation sent by: :invitedBy',
    'user_invitation_location_line' => 'City / country: :city, :country',
    'user_invitation_locale_line' => 'Default language: :language - Timezone: :timezone',
    'user_invitation_next_step' => 'Recommended next step: download the Leopardo RH mobile app, log in with this email, then complete your profile and biometric request if your company uses modernized clock-in.',
    'user_invitation_activate_line' => 'Activate your account and set your password by clicking here:',
    'user_invitation_expiry' => 'This link expires in 7 days.',
    'user_invitation_footer' => 'Once your account is activated, you will be able to complete your personal information, emergency contacts, and, if needed, submit your biometric information. Its actual activation will remain subject to manager or HR approval.',

    // Trial drip (automatic follow-ups)
    'trial_drip_default_subject' => 'New notification from :appName',
    'trial_day3_subject' => 'How to add your employees on :appName',
    'trial_day3_heading' => 'Hello :name,',
    'trial_day3_intro' => "You've been on :appName for 3 days!",
    'trial_day3_body' => 'To get the most out of the platform, the next step is to create your teams and invite your employees.',
    'trial_day3_cta_intro' => 'Head to your manager dashboard to get started:',
    'trial_day3_button' => 'Open my Dashboard',
    'trial_day3_help' => 'Need help? Feel free to reply to this email.',
    'trial_expiring_subject' => 'Your :appName trial expires in 3 days',
    'trial_expiring_intro' => 'Your free trial of :appName expires in 3 days.',
    'trial_expiring_body' => 'To keep enjoying all the features (Clock-in, Scheduling, Payroll), remember to activate your subscription.',
    'trial_expiring_button' => 'Activate my subscription',
    'trial_expired_subject' => 'Your :appName trial has ended',
    'trial_expired_intro' => 'Your free trial period has ended.',
    'trial_expired_body' => 'Your account is now restricted. To reactivate clock-in for your teams and payroll access, please subscribe to one of our plans.',
    'trial_expired_button' => 'Unlock my account',

    // Onboarding drip markdown (D+1, D+3, D+7)
    'trial_day1_subject' => 'Welcome to Leopardo RH — Your first steps',
    'trial_day1_heading' => 'Welcome to Leopardo RH, :name 👋',
    'trial_day1_intro' => 'Your workspace **:company** is ready. Here are your first steps to get started:',
    'trial_day1_step1' => 'Log in to your dashboard',
    'trial_day1_step2' => 'Add your first employees',
    'trial_day1_step3' => 'Configure your schedules and sites',
    'trial_day1_button' => 'Open my dashboard',
    'trial_day1_help' => 'Need help getting started? Check out our [documentation](:docsUrl) or reply directly to this email.',
    'trial_day3_mail_subject' => 'Leopardo RH — Have you tried mobile clock-in?',
    'trial_day3_mail_heading' => 'Have you tried mobile clock-in, :name?',
    'trial_day3_mail_intro' => "You've been on Leopardo RH for 3 days. Here's a tip to get the most out of the platform:",
    'trial_day3_mail_body' => '**Mobile clock-in** lets your employees clock in directly from their phone, with optional geolocation.',
    'trial_day3_mail_button' => 'Set up clock-in',
    'trial_day3_mail_apps_intro' => 'You can also download our mobile apps for Android and iOS:',
    'trial_day3_mail_apps_button' => 'Download the apps',
    'trial_day3_mail_help' => 'Need help? Reply directly to this email.',
    'trial_day7_subject' => 'Leopardo RH — Your trial ends soon',
    'trial_day7_heading' => 'Your trial ends soon, :name',
    'trial_day7_intro' => 'You currently manage **:count** employee(s) at **:company** with Leopardo RH.',
    'trial_day7_body' => 'To keep enjoying all the features without interruption, switch to a paid plan now.',
    'trial_day7_upgrade_button' => 'Switch to a paid plan',
    'trial_day7_compare_intro' => 'Want to compare our plans before deciding?',
    'trial_day7_pricing_button' => 'See pricing',
    'trial_day7_help' => 'Have a question? Reply directly to this email, our team is here to help.',

    // Common
    'greeting' => 'Hello :name,',
    'regards' => 'Best regards,',
    'team_signature' => 'The :company team',
    'footer_note' => 'If you did not request this action, please ignore this email.',

    // Communication (PA2-COMM-007 generic transactional notification email)
    'communication_unsubscribe_link' => 'Manage your notification preferences',

    // Premium layout (emails/layouts/premium.blade.php)
    'premium_layout_rights_reserved' => 'All rights reserved.',
    'premium_layout_footer_note' => 'You are receiving this email because you are registered on our platform.<br>For any question, contact <a href="mailto::supportEmail">support</a>.',
];
