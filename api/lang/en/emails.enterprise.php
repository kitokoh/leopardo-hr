<?php

return [
    'invitation' => [
        'subject' => 'You are invited to join :company on Leopardo HR',
        'greeting' => 'Hello :name,',
        'body' => 'You have been invited to join :company. Click the link below to activate your account.',
        'action' => 'Activate my account',
        'footer' => 'If you did not request this action, ignore this email.',
    ],
    'reset_password' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello :name,',
        'body' => 'Click the link below to reset your password.',
        'action' => 'Reset password',
        'footer' => 'If you did not request this action, ignore this email.',
    ],
    'payroll_ready' => [
        'subject' => 'Your payslip is ready',
        'greeting' => 'Hello :name,',
        'body' => 'Your payslip for :period is ready. You can review it in Leopardo HR.',
        'action' => 'View my payslip',
        'footer' => 'Please review your information before accounting export.',
    ],
    'absence_approved' => [
        'subject' => 'Your leave request has been approved',
        'greeting' => 'Hello :name,',
        'body' => 'Your leave request for :period has been approved.',
        'action' => 'View request',
        'footer' => 'The team schedule has been updated.',
    ],
    'absence_rejected' => [
        'subject' => 'Your leave request has been rejected',
        'greeting' => 'Hello :name,',
        'body' => 'Your leave request for :period has been rejected.',
        'action' => 'View request',
        'footer' => 'Please contact your manager if you need more details.',
    ],
];
