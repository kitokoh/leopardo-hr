<?php

declare(strict_types=1);

/**
 * Server-side i18n labels for sectorial solutions (PDF rendering).
 * Mirrors api/lang/fr/solutions.php (en = default fallback).
 */

return [
    'restaurant' => [
        'package' => [
            'mobile_employee' => 'Employee mobile app',
            'mobile_manager' => 'Manager mobile app',
            'attendance_mobile' => 'Geolocated mobile attendance',
            'kiosk' => 'Attendance kiosk',
            'edge' => 'Local Edge node (offline-first)',
            'planning' => 'Team scheduling',
            'payroll' => 'Payroll (multi-country)',
            'accounting' => 'Accounting',
            'delivery' => 'Delivery management',
            'reservations' => 'Online reservations',
            'inventory' => 'Inventory management',
            'loyalty' => 'Loyalty & marketing',
            'pos' => 'Connected POS',
        ],
        'reason' => [
            'base' => 'Essential: your employees clock in and view their payslips.',
            'manager' => 'Your team is large enough to manage from mobile.',
            'attendance_mobile' => 'You chose mobile attendance.',
            'kiosk' => 'You chose kiosk attendance.',
            'edge' => 'The kiosk works offline thanks to the local node.',
            'scheduling' => 'You manage team schedules.',
            'payroll' => 'You want in-house payroll.',
            'accounting' => 'You asked for accounting tracking.',
            'delivery' => 'You offer delivery.',
            'reservations' => 'You take reservations.',
            'inventory' => 'You want inventory tracking.',
            'loyalty' => 'You want to retain customers.',
            'pos' => 'You want a connected POS.',
        ],
    ],
    'pdf' => [
        'title' => 'Your Leopardo pack',
        'empty' => 'No item selected.',
        'next_steps' => 'Next steps',
        'next_step_account' => 'Create your Leopardo workspace (free trial, no credit card).',
        'next_step_install' => 'Install the mobile apps (QR codes on the download page).',
        'next_step_edge' => 'Install the local Edge node if you chose the attendance kiosk.',
        'footer' => 'Automatically generated — leopardo-hr. Your pack can be changed at any time in your workspace.',
    ],
];
