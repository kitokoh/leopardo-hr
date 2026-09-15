# Tables déclarées par plusieurs migrations — inventaire (issue #7452)

> Généré par `python3 dev-hub/tools/check-duplicate-schema-create.py --audit`.
> Ne pas éditer à la main : régénérer.

## Lecture

Un `Schema::create` est gardé par `if (! schemaTableExists('<table>'))`.
**La première migration exécutée gagne** ; toutes les suivantes sont des no-op
silencieux. Quand deux générations divergent, le code et les tests écrits contre la
dernière échouent en `column "x" does not exist` — souvent masqué par une cascade
`25P02` (« current transaction is aborted »), d'où des suites entières rouges sans
cause lisible : 223 échecs de `tests/Feature/Travel` (#7452), dérive EduManager
(#7410), référentiel d'annonces (#7417), fidélité voyage (#7445).

## Traitement

- Pour **rattraper** une colonne attendue par le code : migration dédiée
  `Schema::table` **idempotente** (`schemaHasColumn`), jamais un second
  `Schema::create` — la garde `.github/workflows/migration-duplication-guard.yml`
  refuse désormais toute nouvelle déclaration concurrente.
- Pour **consolider** : une table = une migration (transformer la génération perdante
  en `ALTER TABLE` idempotents ou la retirer), module par module.
- Les en-têtes ci-dessous indiquent, pour chaque table, les migrations concurrentes et
  les colonnes **absentes du schéma réel** (donc à rattraper côté base).

# Inventaire des tables déclarées plusieurs fois — 68 tables dupliquées, 36 divergentes

## `audit_logs` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_04_01_000104_create_payrolls_tasks_evaluations_notifications.php [action|changes|company_id|created_at|employee_id|id|ip|target_id|target_type]
  - api/database/migrations/tenant/2026_05_10_000001_create_audit_logs_table.php [action|auditable_id|auditable_type|company_id|created_at|ip_address|metadata|new_values|old_values|user_agent|user_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : auditable_id, auditable_type, ip_address, metadata, new_values, old_values, user_agent, user_id

## `delivery_cod_settlements` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000800_6283_create_delivery_tables.php [accounting_ref|collected_minor|commission_minor|company_id|driver_id|expected_minor|idempotency_key|route_id|settled_at|status]
  - api/database/migrations/tenant/2026_08_30_000802_6283_create_delivery_tables.php [accounting_ref|collected_minor|commission_minor|company_id|driver_id|expected_minor|idempotency_key|route_id|settled_at|status]

## `delivery_deliveries` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000800_6283_create_delivery_tables.php [cod_amount_minor|company_id|declared_value_minor|delivered_at|dropoff_address|dropoff_contact|dropoff_phone|failed_at|idempotency_key|pickup_address|pickup_contact|reference|returned_at|source|source_reference|status|type|volume_cm3|weight_grams|window_from|window_to]
  - api/database/migrations/tenant/2026_08_30_000802_6283_create_delivery_tables.php [cod_amount_minor|company_id|declared_value_minor|delivered_at|dropoff_address|dropoff_contact|dropoff_phone|failed_at|idempotency_key|pickup_address|pickup_contact|reference|returned_at|source|source_reference|status|type|volume_cm3|weight_grams|window_from|window_to]

## `delivery_events` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000800_6283_create_delivery_tables.php [company_id|delivery_id|event_at|idempotency_key|latitude|longitude|origin|payload|type]
  - api/database/migrations/tenant/2026_08_30_000802_6283_create_delivery_tables.php [company_id|delivery_id|event_at|idempotency_key|latitude|longitude|origin|payload|type]

## `delivery_notifications` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000806_6290_create_delivery_notifications.php [attempts|channel|company_id|delivery_id|event_type|payload|recipient_phone|sent_at|status|template_key]
  - api/database/migrations/tenant/2026_08_30_001100_6290_create_delivery_notifications.php [attempts|channel|company_id|delivery_id|event_type|payload|recipient_phone|sent_at|status|template_key]

## `delivery_recipient_opt_outs` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000806_6290_create_delivery_notifications.php [company_id|phone]
  - api/database/migrations/tenant/2026_08_30_001100_6290_create_delivery_notifications.php [company_id|phone]

## `delivery_routes` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000800_6283_create_delivery_tables.php [closed_at|cod_collected_minor|company_id|delivered_count|deliveries_count|driver_id|failed_count|idempotency_key|route_date|status|vehicle_code|zone]
  - api/database/migrations/tenant/2026_08_30_000802_6283_create_delivery_tables.php [closed_at|cod_collected_minor|company_id|delivered_count|deliveries_count|driver_id|failed_count|idempotency_key|route_date|status|vehicle_code|zone]

## `delivery_stops` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000800_6283_create_delivery_tables.php [address|arrived_at|company_id|contact|delivered_at|delivery_id|eta|etd|phone|proof_id|route_id|sort_order|status]
  - api/database/migrations/tenant/2026_08_30_000802_6283_create_delivery_tables.php [address|arrived_at|company_id|contact|delivered_at|delivery_id|eta|etd|phone|proof_id|route_id|sort_order|status]

## `delivery_tracking_shares` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000804_6288_create_delivery_tracking_shares.php [company_id|delivery_id|expires_at|share_token]
  - api/database/migrations/tenant/2026_08_30_000900_6288_create_delivery_tracking_shares.php [company_id|delivery_id|expires_at|share_token]

## `edge_licenses` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [allowed_features|company_id|edge_node_id|expires_at|id|issued_at|last_validated_at|license_key|max_employees|signed_payload|validation_status]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [allowed_features|company_id|edge_node_id|expires_at|id|issued_at|last_validated_at|license_key|max_employees|signed_payload|validation_status]

## `edge_nodes` — 3× (DIVERGENTE)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [capabilities|company_id|edge_version|id|last_seen_at|last_sync_at|license_expires_at|license_key|local_ip|metadata|mode|name|public_ip|site_address|slug|status]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [capabilities|company_id|edge_version|id|last_seen_at|last_sync_at|license_expires_at|license_key|local_ip|metadata|mode|name|public_ip|site_address|slug|status]
  - api/database/migrations/tenant/2026_06_30_000001_create_edge_nodes_table.php [alert_muted|company_id|ip_address|last_alert_sent_at|last_seen_at|license_expires_at|license_valid|name|node_id|pending_count|revoked_at|status|sync_requested_at|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : alert_muted, ip_address, last_alert_sent_at, license_valid, node_id, pending_count, revoked_at, sync_requested_at, version

## `edu_academic_years` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000201_5819_create_edu_academic_years_table.php [company_id|end_date|name|start_date|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [company_id|created_by|end_date|name|notes|start_date|status]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [company_id|created_by|end_date|name|notes|start_date|status]
  - api/database/migrations/tenant/2026_08_31_000205_5819_create_edu_year_class_subject_tables.php [company_id|created_by|end_date|name|notes|start_date|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_by, notes

## `edu_admissions` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000301_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_name|company_id|consent_at|consent_marketing|contact_reference|decided_at|decided_by|metadata|status|student_id|submitted_at]
  - api/database/migrations/tenant/2026_08_30_000706_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_birth_date|applicant_email|applicant_first_name|applicant_last_name|applicant_phone|applied_at|campus_id|company_id|consent_contact|consented_at|converted_at|created_by|crm_contact_id|external_id|notes|source|status|student_id]
  - api/database/migrations/tenant/2026_08_30_001512_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_birth_date|applicant_email|applicant_first_name|applicant_last_name|applicant_phone|applied_at|campus_id|company_id|consent_contact|consented_at|converted_at|created_by|crm_contact_id|external_id|notes|source|status|student_id]
  - api/database/migrations/tenant/2026_08_31_000206_5820_create_edu_admissions_table.php [academic_year_id|admission_number|applicant_birth_date|applicant_email|applicant_first_name|applicant_last_name|applicant_phone|applied_at|campus_id|company_id|consent_contact|consented_at|converted_at|created_by|crm_contact_id|external_id|notes|source|status|student_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : applicant_birth_date, applicant_email, applicant_first_name, applicant_last_name, applicant_phone, applied_at, campus_id, consent_contact, consented_at, converted_at, created_by, crm_contact_id, external_id, notes, source

## `edu_assessments` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [academic_year_id|assessment_date|class_id|coefficient|company_id|created_by|max_score|published_at|subject_id|title|type]
  - api/database/migrations/tenant/2026_08_30_000601_5823_create_edu_assessments_table.php [academic_year_id|assessment_date|assessment_type|class_id|coefficient|company_id|created_by|max_score|published_at|status|subject_id|title]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [academic_year_id|assessment_date|class_id|coefficient|company_id|created_by|max_score|published_at|subject_id|title|type]
  - api/database/migrations/tenant/2026_08_31_000209_5823_create_edu_assessment_tables.php [academic_year_id|assessment_date|class_id|coefficient|company_id|created_by|max_score|published_at|subject_id|title|type]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : assessment_type, status

## `edu_attendance_corrections` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000402_5821_create_edu_attendance_corrections_table.php [attendance_record_id|company_id|corrected_at|corrected_by|new_status|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_000707_5821_create_edu_attendance_tables.php [attendance_id|company_id|corrected_by|new_status|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_001513_5821_create_edu_attendance_tables.php [attendance_id|company_id|corrected_by|new_status|previous_status|reason]
  - api/database/migrations/tenant/2026_08_31_000207_5821_create_edu_attendance_tables.php [attendance_id|company_id|corrected_by|new_status|previous_status|reason]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : attendance_id

## `edu_attendances` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000707_5821_create_edu_attendance_tables.php [attendance_date|class_id|company_id|justification|reason|recorded_by|status|student_id]
  - api/database/migrations/tenant/2026_08_30_001513_5821_create_edu_attendance_tables.php [attendance_date|class_id|company_id|justification|reason|recorded_by|status|student_id]
  - api/database/migrations/tenant/2026_08_31_000207_5821_create_edu_attendance_tables.php [attendance_date|class_id|company_id|justification|reason|recorded_by|status|student_id]

## `edu_campuses` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000101_5818_create_edu_campuses_table.php [address|code|company_id|name|status|timezone]
  - api/database/migrations/tenant/2026_08_30_000701_5818_create_edu_campuses_table.php [address|code|company_id|name|status|timezone]
  - api/database/migrations/tenant/2026_08_31_000201_5818_create_edu_campuses_table.php [address|code|company_id|name|status|timezone]

## `edu_classes` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000202_5819_create_edu_classes_table.php [academic_year_id|capacity|company_id|grade_level|name|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [academic_year_id|campus_id|capacity|code|company_id|created_by|level|name|status|teacher_id]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [academic_year_id|campus_id|capacity|code|company_id|created_by|level|name|status|teacher_id]
  - api/database/migrations/tenant/2026_08_31_000205_5819_create_edu_year_class_subject_tables.php [academic_year_id|campus_id|capacity|code|company_id|created_by|level|name|status|teacher_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : campus_id, code, created_by, level, teacher_id

## `edu_course_slots` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000708_5822_create_edu_course_slots_table.php [academic_year_id|class_id|company_id|created_by|day_of_week|end_time|room|start_time|status|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_30_001514_5822_create_edu_course_slots_table.php [academic_year_id|class_id|company_id|created_by|day_of_week|end_time|room|start_time|status|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_31_000208_5822_create_edu_course_slots_table.php [academic_year_id|class_id|company_id|created_by|day_of_week|end_time|room|start_time|status|subject_id|teacher_id]

## `edu_exports` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000711_5833_create_edu_import_tables.php [company_id|exported_by|filename|kind|record_count]
  - api/database/migrations/tenant/2026_08_31_000211_5833_create_edu_import_tables.php [company_id|exported_by|filename|kind|record_count]

## `edu_fees` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000712_5832_create_edu_fees_table.php [admission_id|amount|company_id|created_by|due_date|external_reference|label|paid_at|payment_reference|status|student_id]
  - api/database/migrations/tenant/2026_08_31_000212_5832_create_edu_fees_table.php [admission_id|amount|company_id|created_by|due_date|external_reference|label|paid_at|payment_reference|status|student_id]

## `edu_grade_versions` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [changed_by|comment|company_id|grade_id|score|version]
  - api/database/migrations/tenant/2026_08_30_000603_5823_create_edu_grade_versions_table.php [changed_at|changed_by|company_id|grade_id|new_score|new_status|previous_score|previous_status|reason]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [changed_by|comment|company_id|grade_id|score|version]
  - api/database/migrations/tenant/2026_08_31_000209_5823_create_edu_assessment_tables.php [changed_by|comment|company_id|grade_id|score|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : changed_at, new_score, new_status, previous_score, previous_status, reason

## `edu_grades` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000409_5823_create_edu_assessment_tables.php [assessment_id|comment|company_id|graded_by|published_at|score|status|student_id|version]
  - api/database/migrations/tenant/2026_08_30_000602_5823_create_edu_grades_table.php [assessment_id|comment|company_id|graded_at|graded_by|score|status|student_id]
  - api/database/migrations/tenant/2026_08_30_000709_5823_create_edu_assessment_tables.php [assessment_id|comment|company_id|graded_by|published_at|score|status|student_id|version]
  - api/database/migrations/tenant/2026_08_31_000209_5823_create_edu_assessment_tables.php [assessment_id|comment|company_id|graded_by|published_at|score|status|student_id|version]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : graded_at

## `edu_guardian_access_tokens` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000713_5829_create_edu_guardian_access_tokens_table.php [company_id|created_by|expires_at|guardian_id|token_hash|used_at]
  - api/database/migrations/tenant/2026_08_31_000213_5829_create_edu_guardian_access_tokens_table.php [company_id|created_by|expires_at|guardian_id|token_hash|used_at]

## `edu_guardians` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000103_5818_create_edu_guardians_table.php [company_id|contact_reference|employee_id|first_name|last_name|relationship_code|verified_at]
  - api/database/migrations/tenant/2026_08_30_000703_5818_create_edu_guardians_table.php [company_id|contact_reference|employee_id|first_name|last_name|relationship_code|verified_at]
  - api/database/migrations/tenant/2026_08_31_000203_5818_create_edu_guardians_table.php [company_id|contact_reference|employee_id|first_name|last_name|relationship_code|verified_at]

## `edu_imports` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_000711_5833_create_edu_import_tables.php [columns|committed_at|committed_by|company_id|created_by|entity_type|error_rows|errors|filename|preview_data|raw_rows|status|total_rows|valid_rows]
  - api/database/migrations/tenant/2026_08_31_000211_5833_create_edu_import_tables.php [columns|committed_at|committed_by|company_id|created_by|entity_type|error_rows|errors|filename|preview_data|raw_rows|status|total_rows|valid_rows]

## `edu_report_card_lines` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000410_5824_create_edu_report_card_tables.php [assessment_count|average|coefficient|company_id|report_card_id|subject_id]
  - api/database/migrations/tenant/2026_08_30_000710_5824_create_edu_report_card_tables.php [assessment_count|average|coefficient|company_id|report_card_id|subject_id]
  - api/database/migrations/tenant/2026_08_31_000210_5824_create_edu_report_card_tables.php [assessment_count|average|coefficient|company_id|report_card_id|subject_id]

## `edu_report_cards` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000410_5824_create_edu_report_card_tables.php [academic_year_id|company_id|generated_at|period|published_at|status|student_id|validated_at|validated_by]
  - api/database/migrations/tenant/2026_08_30_000710_5824_create_edu_report_card_tables.php [academic_year_id|company_id|generated_at|period|published_at|status|student_id|validated_at|validated_by]
  - api/database/migrations/tenant/2026_08_30_001520_5824_create_edu_report_cards_table.php [academic_year_id|average_score|class_id|company_id|created_by|data|period_end|period_label|period_start|published_at|status|student_id|validated_at|validated_by]
  - api/database/migrations/tenant/2026_08_31_000210_5824_create_edu_report_card_tables.php [academic_year_id|company_id|generated_at|period|published_at|status|student_id|validated_at|validated_by]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : average_score, class_id, created_by, data, period_end, period_label, period_start

## `edu_student_guardians` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000104_5818_create_edu_student_guardians_table.php [can_receive_notifications|can_view_grades|company_id|guardian_id|relationship_code|student_id]
  - api/database/migrations/tenant/2026_08_30_000704_5818_create_edu_student_guardians_table.php [can_receive_notifications|can_view_grades|company_id|guardian_id|relationship_code|student_id]
  - api/database/migrations/tenant/2026_08_31_000204_5818_create_edu_student_guardians_table.php [can_receive_notifications|can_view_grades|company_id|guardian_id|relationship_code|student_id]

## `edu_students` — 3× (identique)
  - api/database/migrations/tenant/2026_08_30_000102_5818_create_edu_students_table.php [birth_date_encrypted|company_id|display_name|metadata|status|student_number]
  - api/database/migrations/tenant/2026_08_30_000702_5818_create_edu_students_table.php [birth_date_encrypted|company_id|display_name|metadata|status|student_number]
  - api/database/migrations/tenant/2026_08_31_000202_5818_create_edu_students_table.php [birth_date_encrypted|company_id|display_name|metadata|status|student_number]

## `edu_subjects` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000203_5819_create_edu_subjects_table.php [code|company_id|name|status]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [campus_id|code|company_id|created_by|default_coefficient|name|status]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [campus_id|code|company_id|created_by|default_coefficient|name|status]
  - api/database/migrations/tenant/2026_08_31_000205_5819_create_edu_year_class_subject_tables.php [campus_id|code|company_id|created_by|default_coefficient|name|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : campus_id, created_by, default_coefficient

## `edu_teacher_subjects` — 4× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000205_5819_create_edu_teacher_subjects_table.php [academic_year_id|company_id|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_30_000705_5819_create_edu_year_class_subject_tables.php [class_id|company_id|created_by|status|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_30_001511_5819_create_edu_year_class_subject_tables.php [class_id|company_id|created_by|status|subject_id|teacher_id]
  - api/database/migrations/tenant/2026_08_31_000205_5819_create_edu_year_class_subject_tables.php [class_id|company_id|created_by|status|subject_id|teacher_id]
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

## `restaurant_public_shop_tokens` — 2× (identique)
  - api/database/migrations/tenant/2026_08_30_001535_6226_create_restaurant_public_shop_tokens_table.php [active|company_id|last_used_at|name|token_hash]
  - api/database/migrations/tenant/2026_08_30_001538_6226_create_restaurant_public_shop_tokens_table.php [active|company_id|last_used_at|name|token_hash]

## `sync_logs` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [conflicts_detected|conflicts_resolved|direction|edge_node_id|error_message|finished_at|id|records_received|records_sent|started_at|status|summary]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [conflicts_detected|conflicts_resolved|direction|edge_node_id|error_message|finished_at|id|records_received|records_sent|started_at|status|summary]

## `sync_queue` — 2× (identique)
  - api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php [attempt_count|conflict_note|conflict_resolution|edge_node_id|entity_id|entity_type|id|operation|payload|status|synced_at]
  - api/database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php [attempt_count|conflict_note|conflict_resolution|edge_node_id|entity_id|entity_type|id|operation|payload|status|synced_at]

## `travel_advert_positions` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000017_6110_create_travel_advert_tables.php [code|company_id|is_active|name]
  - api/database/migrations/tenant/2026_08_30_000921_6108_create_travel_advert_reference_tables.php [code|company_id|created_at|label|updated_at]
  - api/database/migrations/tenant/2026_08_30_001544_6108_create_travel_advert_reference_tables.php [code|company_id|description|name]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, description, label, updated_at

## `travel_advert_prices` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000017_6110_create_travel_advert_tables.php [company_id|currency|position_id|price_per_character_minor|price_per_image_minor|type_id]
  - api/database/migrations/tenant/2026_08_30_000922_6109_create_travel_advert_prices_table.php [advert_position_id|advert_type_id|company_id|created_at|currency|price_per_character_minor|price_per_image_minor|updated_at]
  - api/database/migrations/tenant/2026_08_30_001545_6109_create_travel_advert_prices_table.php [advert_position_id|advert_type_id|company_id|currency|price_character_minor|price_image_minor]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : advert_position_id, advert_type_id, created_at, price_character_minor, price_image_minor, updated_at

## `travel_advert_types` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000017_6110_create_travel_advert_tables.php [code|company_id|is_active|name]
  - api/database/migrations/tenant/2026_08_30_000921_6108_create_travel_advert_reference_tables.php [code|company_id|created_at|label|updated_at]
  - api/database/migrations/tenant/2026_08_30_001544_6108_create_travel_advert_reference_tables.php [code|company_id|description|name]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, description, label, updated_at

## `travel_article_categories` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000013_6104_create_travel_article_tables.php [code|company_id|is_active|name]
  - api/database/migrations/tenant/2026_08_30_000916_6104_create_travel_articles_tables.php [company_id|name|slug]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : slug

## `travel_articles` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000013_6104_create_travel_article_tables.php [author_user_id|body_redacted|category_id|company_id|moderation_note|published_at|status|title]
  - api/database/migrations/tenant/2026_08_30_000916_6104_create_travel_articles_tables.php [author_id|author_type|body_redacted|category_id|company_id|moderated_at|moderated_by_user_id|published_at|slug|status|title]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : author_id, author_type, moderated_at, moderated_by_user_id, slug

## `travel_bookings` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000608_6022_create_travel_bookings_and_passengers_table.php [booked_by_user_id|booking_source|company_id|currency|customer_contact_id|expires_at|idempotency_key|passenger_count|payment_status|reference|status|total_amount_minor|trip_id|version]
  - api/database/migrations/tenant/2026_08_29_000908_6022_create_travel_bookings_and_passengers_table.php [booked_by_user_id|booking_source|company_id|currency|customer_contact_id|expires_at|idempotency_key|passenger_count|payment_status|reference|status|total_amount_minor|trip_id|version]

## `travel_cancellation_policies` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_001504_6103_create_travel_cancellation_policies_table.php [cancel_before_hours|class_id|company_id|description|is_active|penalty_percent|refundable|trip_id]
  - api/database/migrations/tenant/2026_08_30_001509_6103_create_travel_cancellation_policies_table.php [class_id|company_id|created_by_user_id|hours_before_departure|penalty_percent|refundable|trip_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_by_user_id, hours_before_departure

## `travel_cities` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000002_6015_create_travel_cities_table.php [company_id|country_iso2|latitude|longitude|name|region|status]
  - api/database/migrations/tenant/2026_08_29_000901_6015_create_travel_cities_table.php [company_id|country_iso2|latitude|longitude|name|region|status]

## `travel_comments` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000014_6105_create_travel_comments_table.php [article_id|author_name|author_type|author_user_id|body|company_id|moderated_at|moderated_by_user_id|report_reason|reported_at|status]
  - api/database/migrations/tenant/2026_08_30_000917_6105_create_travel_comments_engagement_tables.php [article_id|author_id|author_type|company_id|content_redacted|moderated_at|moderated_by_user_id|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : author_id, content_redacted

## `travel_countries` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000900_6014_create_travel_countries_table.php [company_id|iso2|iso3|name|phone_code|status]
  - api/database/migrations/tenant/2026_08_29_000914_6014_create_travel_countries_table.php [company_id|iso2|iso3|name|phone_code|status]

## `travel_currency_rates` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000020_6096_create_travel_currency_rates_table.php [base_currency|company_id|quote_currency|rate|valid_from|valid_until]
  - api/database/migrations/tenant/2026_08_30_001506_6096_create_travel_currency_rates_table.php [company_id|from_currency|rate_minor|to_currency|valid_from|valid_to]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : from_currency, rate_minor, to_currency, valid_to

## `travel_daily_sales` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000915_6076_create_travel_report_read_models_table.php [bookings_count|company_id|passengers_count|revenue_minor|sale_date|trip_id]
  - api/database/migrations/tenant/2026_08_30_001503_6076_create_travel_report_read_models_table.php [amount_minor|booking_count|company_id|currency|passenger_count|sale_date|source|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : amount_minor, booking_count, currency, passenger_count, source, status

## `travel_likes` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000015_6106_create_travel_engagement_tables.php [actor_identifier|actor_type|actor_user_id|article_id|company_id]
  - api/database/migrations/tenant/2026_08_30_000917_6105_create_travel_comments_engagement_tables.php [actor_id|actor_type|article_id|company_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : actor_id

## `travel_loyalty_accounts` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000012_6101_create_travel_loyalty_tables.php [company_id|contact_identifier|opt_in|opt_in_at|opt_out_at|points_balance]
  - api/database/migrations/tenant/2026_08_30_001510_6101_create_travel_loyalty_tables.php [company_id|contact_id|opt_in_at|opt_out_at|points_balance]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : contact_id

## `travel_offices` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000003_6016_create_travel_stations_and_offices_tables.php [address|city_id|company_id|contact_phone|name|status]
  - api/database/migrations/tenant/2026_08_29_000902_6016_create_travel_stations_and_offices_tables.php [address|city_id|company_id|contact_phone|name|status]

## `travel_outbox_events` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000610_6024_create_travel_outbox_events_table.php [attempts|available_at|company_id|created_at|event_type|idempotency_key|last_error|payload_redacted|status|updated_at]
  - api/database/migrations/tenant/2026_08_29_000910_6024_create_travel_outbox_events_table.php [attempts|available_at|company_id|created_at|event_type|idempotency_key|last_error|payload_redacted|status|updated_at]

## `travel_passengers` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000608_6022_create_travel_bookings_and_passengers_table.php [age_category|birth_date|booking_id|class_id|company_id|document_number_encrypted|document_number_hash|document_type|full_name|seat_number|unit_price_minor]
  - api/database/migrations/tenant/2026_08_29_000908_6022_create_travel_bookings_and_passengers_table.php [age_category|birth_date|booking_id|class_id|company_id|document_number_encrypted|document_number_hash|document_type|full_name|seat_number|unit_price_minor]

## `travel_payments` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000609_6023_create_travel_tickets_and_payments_table.php [amount_minor|booking_id|callback_payload_redacted|company_id|currency|idempotency_key|provider_code|provider_reference|reference|status]
  - api/database/migrations/tenant/2026_08_29_000909_6023_create_travel_tickets_and_payments_table.php [amount_minor|booking_id|callback_payload_redacted|company_id|currency|idempotency_key|provider_code|provider_reference|reference|status]

## `travel_quiz_participations` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000016_6107_create_travel_quiz_tables.php [answers_redacted|company_id|completed_at|participant_identifier|quiz_id|score|total_points]
  - api/database/migrations/tenant/2026_08_30_000920_6107_create_travel_quiz_tables.php [answers|bonus|company_id|created_at|participant_contact_id|participant_email|participant_name|quiz_id|score|status|updated_at]
  - api/database/migrations/tenant/2026_08_30_000923_6107_create_travel_quiz_tables.php [answers|company_id|completed_at|participant_id|participant_type|quiz_id|score|status]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : answers, bonus, created_at, participant_contact_id, participant_email, participant_id, participant_name, participant_type, status, updated_at

## `travel_quiz_questions` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000016_6107_create_travel_quiz_tables.php [choices|company_id|correct_answer_hash|label|points|quiz_id|rank]
  - api/database/migrations/tenant/2026_08_30_000920_6107_create_travel_quiz_tables.php [company_id|correct_option_index|created_at|options|points|position|question|quiz_id|updated_at]
  - api/database/migrations/tenant/2026_08_30_000923_6107_create_travel_quiz_tables.php [company_id|correct_option_index|options|points|question|quiz_id|sort_order]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : correct_option_index, created_at, options, position, question, sort_order, updated_at

## `travel_quizzes` — 3× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000016_6107_create_travel_quiz_tables.php [bonus_points|company_id|created_by_user_id|description_redacted|max_participations_per_contact|status|title]
  - api/database/migrations/tenant/2026_08_30_000920_6107_create_travel_quiz_tables.php [company_id|created_at|description_redacted|ends_at|max_participations_per_contact|starts_at|status|title|updated_at]
  - api/database/migrations/tenant/2026_08_30_000923_6107_create_travel_quiz_tables.php [company_id|description_redacted|max_attempts|published_at|status|title]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, ends_at, max_attempts, published_at, starts_at, updated_at

## `travel_quotes` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000006_6094_create_travel_quotes_table.php [booking_id|company_id|created_by_user_id|currency|customer_contact_id|expires_at|idempotency_key|passenger_count|passengers_json|reference|status|total_amount_minor|trip_id]
  - api/database/migrations/tenant/2026_08_30_000019_6094_create_travel_corporate_tables.php [class_id|company_id|corporate_account_id|created_by_user_id|currency|expires_at|passengers_count|status|total_amount_minor|trip_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : class_id, corporate_account_id, passengers_count

## `travel_ratings` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000015_6106_create_travel_engagement_tables.php [actor_identifier|actor_type|actor_user_id|article_id|company_id|stars]
  - api/database/migrations/tenant/2026_08_30_000917_6105_create_travel_comments_engagement_tables.php [actor_id|actor_type|article_id|company_id|rating]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : actor_id, rating

## `travel_shares` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000015_6106_create_travel_engagement_tables.php [actor_identifier|actor_type|actor_user_id|article_id|channel|company_id]
  - api/database/migrations/tenant/2026_08_30_000917_6105_create_travel_comments_engagement_tables.php [actor_id|actor_type|article_id|channel|company_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : actor_id

## `travel_stations` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000003_6016_create_travel_stations_and_offices_tables.php [address|city_id|code|company_id|contact_phone|is_terminal|name|status|timezone]
  - api/database/migrations/tenant/2026_08_29_000902_6016_create_travel_stations_and_offices_tables.php [address|city_id|code|company_id|contact_phone|is_terminal|name|status|timezone]

## `travel_tickets` — 2× (identique)
  - api/database/migrations/tenant/2026_08_29_000609_6023_create_travel_tickets_and_payments_table.php [booking_id|checked_in_at|checked_in_by_user_id|company_id|issued_at|passenger_id|pdf_asset_id|status|ticket_number|valid_from|valid_until|validation_code]
  - api/database/migrations/tenant/2026_08_29_000909_6023_create_travel_tickets_and_payments_table.php [booking_id|checked_in_at|checked_in_by_user_id|company_id|issued_at|passenger_id|pdf_asset_id|status|ticket_number|valid_from|valid_until|validation_code]

## `travel_tourist_sites` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000018_6112_create_travel_tourist_sites_table.php [city_id|company_id|description_redacted|images|latitude|longitude|name|status]
  - api/database/migrations/tenant/2026_08_30_000924_6112_create_travel_tourist_sites_table.php [city_id|company_id|created_at|description_redacted|image_asset_id|latitude|longitude|name|status|updated_at]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : created_at, image_asset_id, updated_at

## `travel_trip_occupancy` — 2× (DIVERGENTE)
  - api/database/migrations/tenant/2026_08_30_000915_6076_create_travel_report_read_models_table.php [company_id|departure_date|occupancy_rate|seats_sold|total_seats|trip_id]
  - api/database/migrations/tenant/2026_08_30_001503_6076_create_travel_report_read_models_table.php [company_id|departure_date|free_seats|occupancy_rate|reserved_seats|sold_seats|total_seats|trip_id]
  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : free_seats, reserved_seats, sold_seats

