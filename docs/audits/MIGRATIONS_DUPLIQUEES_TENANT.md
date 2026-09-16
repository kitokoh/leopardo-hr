# Inventaire des tables déclarées plusieurs fois — 24 tables dupliquées, 17 divergentes

## `audit_logs` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_04_01_000104_create_payrolls_tasks_evaluations_notifications.php [action|changes|company_id|created_at|employee_id|id|ip|target_id|target_type]
  - api/database/migrations/tenant/2026_05_10_000001_create_audit_logs_table.php [action|auditable_id|auditable_type|company_id|created_at|ip_address|metadata|new_values|old_values|user_agent|user_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : auditable_id, auditable_type, ip_address, metadata, new_values, old_values, user_agent, user_id

## `edge_licenses` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [allowed_features|company_id|edge_node_id|expires_at|id|issued_at|last_validated_at|license_key|max_employees|signed_payload|validation_status]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [allowed_features|company_id|edge_node_id|expires_at|id|issued_at|last_validated_at|license_key|max_employees|signed_payload|validation_status]

## `edge_nodes` — 3× (DIVERGENTE)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [capabilities|company_id|edge_version|id|last_seen_at|last_sync_at|license_expires_at|license_key|local_ip|metadata|mode|name|public_ip|site_address|slug|status]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [capabilities|company_id|edge_version|id|last_seen_at|last_sync_at|license_expires_at|license_key|local_ip|metadata|mode|name|public_ip|site_address|slug|status]
  - api/database/migrations/tenant/2026_06_30_000001_create_edge_nodes_table.php [alert_muted|company_id|ip_address|last_alert_sent_at|last_seen_at|license_expires_at|license_valid|name|node_id|pending_count|revoked_at|status|sync_requested_at|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : alert_muted, ip_address, last_alert_sent_at, license_valid, node_id, pending_count, revoked_at, sync_requested_at, version

## `edu_academic_years` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000201_5819_create_edu_academic_years_table.php [company_id|end_date|name|start_date|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [company_id|created_by|end_date|name|notes|start_date|status]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [company_id|created_by|end_date|name|notes|start_date|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_by, notes

## `edu_admissions` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000301_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_name|company_id|consent_at|consent_marketing|contact_reference|decided_at|decided_by|metadata|status|student_id|submitted_at]
  - api/database/migrations/tenant/2026_08_30_000706_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_birth_date|applicant_email|applicant_first_name|applicant_last_name|applicant_phone|applied_at|campus_id|company_id|consent_contact|consented_at|converted_at|created_by|crm_contact_id|external_id|notes|source|status|student_id]
  - api/database/migrations/tenant/2026_08_30_001512_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_birth_date|applicant_email|applicant_first_name|applicant_last_name|applicant_phone|applied_at|campus_id|company_id|consent_contact|consented_at|converted_at|created_by|crm_contact_id|external_id|notes|source|status|student_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : applicant_birth_date, applicant_email, applicant_first_name, applicant_last_name, applicant_phone, applied_at, campus_id, consent_contact, consented_at, converted_at, created_by, crm_contact_id, external_id, notes, source

## `edu_assessments` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [academic_year_id|assessment_date|class_id|coefficient|company_id|created_by|max_score|published_at|subject_id|title|type]
  - api/database/migrations/tenant/2026_08_30_000601_5823_create_edu_assessments_table.php [academic_year_id|assessment_date|assessment_type|class_id|coefficient|company_id|created_by|max_score|published_at|status|subject_id|title]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [academic_year_id|assessment_date|class_id|coefficient|company_id|created_by|max_score|published_at|subject_id|title|type]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : assessment_type, status

## `edu_attendance_corrections` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000402_5821_create_edu_attendance_corrections_table.php [attendance_record_id|company_id|corrected_at|corrected_by|new_status|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_000707_5821_create_edu_attendance_tables.php [attendance_id|company_id|corrected_by|new_status|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_001513_5821_create_edu_attendance_tables.php [attendance_id|company_id|corrected_by|new_status|previous_status|reason]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : attendance_id

## `edu_attendances` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000707_5821_create_edu_attendance_tables.php [attendance_date|class_id|company_id|justification|reason|recorded_by|status|student_id]
  - api/database/migrations/tenant/2026_08_30_001513_5821_create_edu_attendance_tables.php [attendance_date|class_id|company_id|justification|reason|recorded_by|status|student_id]

## `edu_classes` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000202_5819_create_edu_classes_table.php [academic_year_id|capacity|company_id|grade_level|name|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [academic_year_id|campus_id|capacity|code|company_id|created_by|level|name|status|teacher_id]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [academic_year_id|campus_id|capacity|code|company_id|created_by|level|name|status|teacher_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : campus_id, code, created_by, level, teacher_id

## `edu_grade_versions` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [changed_by|comment|company_id|grade_id|score|version]
  - api/database/migrations/tenant/2026_08_30_000603_5823_create_edu_grade_versions_table.php [changed_at|changed_by|company_id|grade_id|new_score|new_status|previous_score|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [changed_by|comment|company_id|grade_id|score|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : changed_at, new_score, new_status, previous_score, previous_status, reason

## `edu_grades` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [assessment_id|comment|company_id|graded_by|published_at|score|status|student_id|version]
  - api/database/migrations/tenant/2026_08_30_000602_5823_create_edu_grades_table.php [assessment_id|comment|company_id|graded_at|graded_by|score|status|student_id]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [assessment_id|comment|company_id|graded_by|published_at|score|status|student_id|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : graded_at

## `edu_guardians` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000103_5818_create_edu_guardians_table.php [company_id|contact_reference|employee_id|first_name|last_name|relationship_code|verified_at]
  - api/database/migrations/tenant/2026_08_30_000703_5818_create_edu_guardians_table.php [company_id|contact_reference|employee_id|first_name|last_name|relationship_code|verified_at]

## `edu_report_card_lines` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000410_5824_create_edu_report_card_tables.php [assessment_count|average|coefficient|company_id|report_card_id|subject_id]
  - api/database/migrations/tenant/2026_08_30_000710_5824_create_edu_report_card_tables.php [assessment_count|average|coefficient|company_id|report_card_id|subject_id]

## `edu_report_cards` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000410_5824_create_edu_report_card_tables.php [academic_year_id|company_id|generated_at|period|published_at|status|student_id|validated_at|validated_by]
  - api/database/migrations/tenant/2026_08_30_000710_5824_create_edu_report_card_tables.php [academic_year_id|company_id|generated_at|period|published_at|status|student_id|validated_at|validated_by]
  - api/database/migrations/tenant/2026_08_30_001520_5824_create_edu_report_cards_table.php [academic_year_id|average_score|class_id|company_id|created_by|data|period_end|period_label|period_start|published_at|status|student_id|validated_at|validated_by]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : average_score, class_id, created_by, data, period_end, period_label, period_start

## `edu_students` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000102_5818_create_edu_students_table.php [birth_date_encrypted|company_id|display_name|metadata|status|student_number]
  - api/database/migrations/tenant/2026_08_30_000702_5818_create_edu_students_table.php [birth_date_encrypted|company_id|display_name|metadata|status|student_number]

## `edu_subjects` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000203_5819_create_edu_subjects_table.php [code|company_id|name|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [campus_id|code|company_id|created_by|default_coefficient|name|status]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [campus_id|code|company_id|created_by|default_coefficient|name|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : campus_id, created_by, default_coefficient

## `edu_teacher_subjects` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000205_5819_create_edu_teacher_subjects_table.php [academic_year_id|company_id|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [class_id|company_id|created_by|status|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [class_id|company_id|created_by|status|subject_id|teacher_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : class_id, created_by, status

## `fuel_incidents` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000405_5804_create_fuel_incident_tables.php [assigned_to|closed_at|closed_by|closure_notes|company_id|description|equipment_id|equipment_type|occurred_at|reported_by|resolution_notes|resolved_at|resolved_by|severity|station_id|status|title]
  - api/database/migrations/tenant/2026_08_30_000600_5804_create_fuel_incident_tables.php [assigned_at|assigned_to|attachments_metadata|category|closed_at|closed_by|company_id|description_redacted|external_id|reported_at|reported_by|resolved_at|resolved_by|severity|station_id|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : assigned_at, attachments_metadata, category, description_redacted, external_id, reported_at

## `fuel_maintenance_tasks` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000405_5804_create_fuel_incident_tables.php [assigned_to|company_id|completed_at|completed_by|completion_notes|description|incident_id|priority|scheduled_for|station_id|status|task_type|title]
  - api/database/migrations/tenant/2026_08_30_000600_5804_create_fuel_incident_tables.php [assigned_to|company_id|completed_at|completed_by|created_by|description_redacted|due_at|external_id|incident_id|priority|started_at|station_id|status|task_type|title]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_by, description_redacted, due_at, external_id, started_at

## `fuel_outbox_events` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000406_5809_create_fuel_outbox_events_table.php [aggregate_id|aggregate_type|attempts|available_at|company_id|created_at|event_type|idempotency_key|last_error|payload|processed_at|status|updated_at]
  - api/database/migrations/tenant/2026_08_30_000700_5809_create_fuel_outbox_events_table.php [aggregate_id|aggregate_type|attempts|available_at|company_id|created_at|event_type|idempotency_key|last_error|payload|processed_at|status|updated_at]
  - api/database/migrations/tenant/2026_08_30_001518_5809_create_fuel_outbox_tables.php [aggregate_id|aggregate_type|attempts|available_at|company_id|event_type|idempotency_key|last_error|payload|processed_at|status]

## `fuel_reconciliation_runs` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000404_5803_create_fuel_stock_tables.php [company_id|created_by|finished_at|last_error|run_date|started_at|station_id|status|summary]
  - api/database/migrations/tenant/2026_08_30_000500_5803_create_fuel_stock_tables.php [company_id|created_at|created_by|finished_at|last_error|run_date|started_at|station_id|status|summary|updated_at]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, updated_at

## `fuel_report_snapshots` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_001540_5811_create_fuel_report_tables.php [company_id|computed_at|payload|report_type|snapshot_date|station_id]
  - api/database/migrations/tenant/2026_08_30_001541_5811_create_fuel_report_snapshots_table.php [company_id|created_at|generated_at|generated_by|payload|period_end|period_start|snapshot_type|station_id|updated_at]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, generated_at, generated_by, period_end, period_start, snapshot_type, updated_at

## `sync_logs` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [conflicts_detected|conflicts_resolved|direction|edge_node_id|error_message|finished_at|id|records_received|records_sent|started_at|status|summary]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [conflicts_detected|conflicts_resolved|direction|edge_node_id|error_message|finished_at|id|records_received|records_sent|started_at|status|summary]

## `sync_queue` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [attempt_count|conflict_note|conflict_resolution|edge_node_id|entity_id|entity_type|id|operation|payload|status|synced_at]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [attempt_count|conflict_note|conflict_resolution|edge_node_id|entity_id|entity_type|id|operation|payload|status|synced_at]

