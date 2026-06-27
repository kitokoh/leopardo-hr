#!/usr/bin/env bash
# =============================================================================
# cleanup-legacy.sh — Migration Clean Architecture: suppression progressive
#                     des fichiers originaux (flat structure)
#
# USAGE: ./scripts/cleanup-legacy.sh [--dry-run] [--module MODULE]
# EXEMPLES:
#   ./scripts/cleanup-legacy.sh --dry-run
#   ./scripts/cleanup-legacy.sh --module HR
#   ./scripts/cleanup-legacy.sh --module Payroll
#
# PRÉREQUIS: Tous les tests CI doivent être verts avant d'exécuter ce script.
# =============================================================================

set -euo pipefail

DRY_RUN=false
TARGET_MODULE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --module) TARGET_MODULE="$2"; shift 2 ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

API_DIR="$(cd "$(dirname "$0")/.." && pwd)/api"
cd "$API_DIR"

log() { echo "[$1] $2"; }
remove_file() {
    local file="$1"
    if [ -f "$file" ]; then
        if [ "$DRY_RUN" = true ]; then
            log "DRY-RUN" "Would remove: $file"
        else
            git rm "$file"
            log "REMOVED" "$file"
        fi
    fi
}

# ============================================================
# HR MODULE
# ============================================================
cleanup_hr() {
    log "MODULE" "HR"
    # Controllers
    for f in \
        app/Http/Controllers/Api/V1/EmployeeController.php \
        app/Http/Controllers/Api/V1/DepartmentController.php \
        app/Http/Controllers/Api/V1/OrgChartController.php \
        app/Http/Controllers/Api/V1/HrController.php \
        app/Http/Controllers/Api/V1/HrReportController.php \
        app/Http/Controllers/Api/V1/PositionController.php \
        app/Http/Controllers/Api/V1/EmployeeImportController.php \
        app/Http/Controllers/Api/V1/ContractController.php \
        app/Http/Controllers/Api/V1/EvaluationController.php \
        app/Http/Controllers/Api/V1/OnboardingController.php \
        app/Http/Controllers/Api/V1/OnboardingChecklistController.php \
        app/Http/Controllers/Api/V1/OnboardingStepController.php \
        app/Http/Controllers/Api/V1/OnboardingQrController.php \
        app/Http/Controllers/Api/V1/TrainingController.php \
        app/Http/Controllers/Api/V1/SelfServiceController.php \
        app/Http/Controllers/Api/V1/RoleAssignmentController.php \
        app/Http/Controllers/Api/V1/InvitationController.php; do
        remove_file "$f"
    done
    # Services
    for f in \
        app/Services/EmployeeService.php \
        app/Services/RoleInvitationService.php \
        app/Services/UserInvitationService.php \
        app/Services/SectorTemplateService.php; do
        remove_file "$f"
    done
    # Models
    for f in \
        app/Models/Department.php \
        app/Models/Position.php \
        app/Models/Contract.php \
        app/Models/ContractAmendment.php \
        app/Models/Evaluation.php \
        app/Models/OnboardingStep.php \
        app/Models/TrainingCourse.php \
        app/Models/TrainingEnrollment.php \
        app/Models/TrainingSession.php \
        app/Models/UserInvitation.php; do
        remove_file "$f"
    done
}

# ============================================================
# PAYROLL MODULE
# ============================================================
cleanup_payroll() {
    log "MODULE" "Payroll"
    for f in \
        app/Http/Controllers/Api/V1/PayrollController.php \
        app/Http/Controllers/Api/V1/PayrollCycleController.php \
        app/Http/Controllers/Api/V1/PayrollRunController.php \
        app/Http/Controllers/Api/V1/PaySlipController.php \
        app/Http/Controllers/Api/V1/SalaryStructureController.php \
        app/Http/Controllers/Api/V1/SalaryComponentController.php \
        app/Http/Controllers/Api/V1/SalaryAdvanceController.php \
        app/Http/Controllers/Api/V1/BankExportController.php \
        app/Http/Controllers/Api/V1/SocialContributionController.php \
        app/Http/Controllers/Api/V1/SocialDeclarationController.php \
        app/Http/Controllers/Api/V1/TaxSlabController.php \
        app/Http/Controllers/Api/V1/CotisationSimulationController.php \
        app/Http/Controllers/Api/V1/BulkPaymentController.php \
        app/Http/Controllers/Api/V1/PaymentBatchController.php \
        app/Http/Controllers/Api/V1/PaymentDocumentController.php; do
        remove_file "$f"
    done
    for f in \
        app/Services/PayrollService.php \
        app/Services/PayrollCycleService.php \
        app/Services/SalaryAdvanceService.php \
        app/Services/BankExportGenerator.php \
        app/Services/PaySlipPdfGenerator.php \
        app/Services/SocialDeclarationGenerator.php \
        app/Services/CommissionService.php; do
        remove_file "$f"
    done
    for f in \
        app/Models/Payroll.php app/Models/PayrollRun.php \
        app/Models/PaySlip.php app/Models/PaySlipLine.php \
        app/Models/SalaryStructure.php app/Models/SalaryComponent.php \
        app/Models/SalaryAdvance.php app/Models/BankExport.php \
        app/Models/SocialContribution.php app/Models/TaxSlab.php \
        app/Models/Payment.php app/Models/PaymentBatch.php \
        app/Models/PaymentItem.php app/Models/PaymentDocument.php \
        app/Models/PaymentConfirmation.php app/Models/LoanRepayment.php; do
        remove_file "$f"
    done
}

# ============================================================
# MAIN
# ============================================================
case "$TARGET_MODULE" in
    HR)      cleanup_hr ;;
    Payroll) cleanup_payroll ;;
    "")
        log "INFO" "No module specified. Use --module HR|Payroll|Attendance|Planning|Recruitment|Cabinet|Fleet|Billing"
        log "INFO" "Or add --dry-run to preview"
        exit 0
        ;;
    *)
        log "ERROR" "Unknown module: $TARGET_MODULE"
        exit 1
        ;;
esac

if [ "$DRY_RUN" = true ]; then
    log "DRY-RUN" "Done. No files were actually removed."
else
    log "DONE" "Files removed. Run: git commit -m 'chore(cleanup): remove legacy $TARGET_MODULE files'"
fi
