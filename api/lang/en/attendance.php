<?php

return [
    // Statuses
    'status_present' => 'Present',
    'status_absent' => 'Absent',
    'status_late' => 'Late',
    'status_leave' => 'On leave',
    'status_ontime' => 'On time',
    'status_incomplete' => 'Incomplete',

    // Actions
    'check_in' => 'Check in',
    'check_out' => 'Check out',
    'check_in_success' => 'Checked in successfully.',
    'check_out_success' => 'Checked out successfully.',

    // Labels
    'hours_worked' => ':hours hours worked',
    'late_by_minutes' => ':minutes minutes late',
    'check_in_time' => 'Check-in: :time',
    'check_out_time' => 'Check-out: :time',
    'no_data_today' => 'No attendance record today',
    'overtime' => 'Overtime',
    'daily_summary' => 'Daily summary',
    'monthly_summary' => 'Monthly summary',
    'history' => 'History',

    // Geo sessions (SmartAttendance)
    'geo_session_approved' => 'Session approved. The attendance record has been created.',
    'geo_session_rejected' => 'Session rejected.',

    // Attendance corrections (manager/HR review)
    'corrections_title' => 'Attendance corrections',
    'corrections_subtitle' => 'Approve or reject correction requests submitted by employees.',
    'corrections_empty' => 'No correction request at the moment.',
    'correction_reason_label' => 'Employee reason',
    'correction_requested_check_in' => 'Requested check-in',
    'correction_requested_check_out' => 'Requested check-out',
    'correction_status_pending' => 'Pending',
    'correction_status_applied' => 'Applied',
    'correction_status_rejected' => 'Rejected',
    'correction_approve' => 'Approve',
    'correction_reject' => 'Reject',
    'correction_applied' => 'Correction applied to the attendance record.',
    'correction_rejected' => 'Correction rejected.',
    'correction_already_processed' => 'This correction request has already been processed.',
    'correction_filter_pending' => 'Pending',
    'correction_filter_applied' => 'Applied',
    'correction_filter_rejected' => 'Rejected',
    'correction_filter_all' => 'All',
];
