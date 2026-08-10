#!/bin/bash
# Chunked payroll coverage measurement (sandbox-friendly: no single run > ~9 min)
cd /home/user/.workspace/leopardo-work/leopardo-hr/api
mkdir -p storage/coverage
export PATH="/usr/local/bin:$PATH"

run_chunk() {
  local n="$1"; shift
  local out="storage/coverage/clover-$n.xml"
  echo "=== CHUNK $n start $(date -u +%H:%M:%S) ===" >> storage/coverage/chunks.log
  timeout 1500 php artisan test "$@" --coverage-clover="$out" > "storage/coverage/chunk-$n.log" 2>&1
  echo "=== CHUNK $n exit=$? $(date -u +%H:%M:%S) ===" >> storage/coverage/chunks.log
  tail -3 "storage/coverage/chunk-$n.log" >> storage/coverage/chunks.log
}

# Chunk 1: core payroll services + golden
run_chunk 1 \
  tests/Feature/Payroll/Golden \
  tests/Feature/Payroll/PayrollAnomalyServiceTest.php \
  tests/Feature/Payroll/PayrollClosingTest.php \
  tests/Feature/Payroll/PayrollAttendanceAnomalyApiTest.php \
  tests/Feature/Payroll/PayrollJournalApiTest.php \
  tests/Feature/Payroll/PayrollRunClosingApiTest.php

# Chunk 2: remaining Feature/Payroll
run_chunk 2 \
  tests/Feature/Payroll/PayrollServiceTest.php \
  tests/Feature/Payroll/PayrollTenantIsolationTest.php \
  tests/Feature/Payroll/PayrollWorkInputsTest.php \
  tests/Feature/Payroll/PayrollReferenceControllersTest.php \
  tests/Feature/Payroll/PayrollExportsTest.php \
  tests/Feature/Payroll/PaySlipDzMentionsTest.php \
  tests/Feature/Payroll/PrecalculatePayrollRunsCommandTest.php \
  tests/Feature/Payroll/ProcessPayrollBatchJobTest.php \
  tests/Feature/Payroll/SalaryAdvanceWorkflowTest.php

# Chunk 3: cycle + controller + unit
run_chunk 3 \
  tests/Feature/PayrollCycleIntegrationTest.php \
  tests/Feature/PayrollCyclePreviewTest.php \
  tests/Feature/PayrollCycleSettingsTest.php \
  tests/Feature/PayrollRunControllerTest.php \
  tests/Feature/PayrollAccountingExportTest.php \
  tests/Feature/PayrollCountryRulesTemporalVersioningTest.php \
  tests/Unit/PayrollCountryRulesTest.php \
  tests/Unit/PayrollCalculatorCountryRulesTest.php

# Chunk 4: root-level payroll/payment tests (F-13 migrated)
run_chunk 4 \
  tests/Feature/BankExportControllerTest.php \
  tests/Feature/GenerateBankExportJobTest.php \
  tests/Feature/BulkPaymentControllerTest.php \
  tests/Feature/ProcessBulkPaymentJobTest.php \
  tests/Feature/CotisationSimulationControllerTest.php \
  tests/Feature/CotisationSimulationTest.php \
  tests/Feature/EmployeeLoanControllerTest.php \
  tests/Feature/PaymentBatchControllerTest.php \
  tests/Feature/PaymentDocumentControllerTest.php \
  tests/Feature/PaymentWebhookControllerTest.php \
  tests/Feature/GeneratePaymentDocumentJobTest.php \
  tests/Feature/GeneratePaymentDocumentJobNotificationTest.php \
  tests/Feature/PaySlipControllerTest.php \
  tests/Feature/PaySlipPdfLocaleTest.php \
  tests/Feature/SalaryAdvanceProofTest.php \
  tests/Feature/SocialDeclarationControllerTest.php \
  tests/Feature/EmployeeIbanValidationTest.php

echo "ALL CHUNKS DONE $(date -u +%H:%M:%S)" >> storage/coverage/chunks.log
