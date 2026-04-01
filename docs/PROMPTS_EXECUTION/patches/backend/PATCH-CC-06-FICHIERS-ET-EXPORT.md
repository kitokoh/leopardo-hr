# PATCH CC-06 — Serveur fichiers privés + BOM export bancaire
# À appliquer PENDANT CC-06 (module Paie)
# Réf : 23_STOCKAGE_ET_SAUVEGARDES.md + 26_FORMATS_EXPORT_BANCAIRE.md

---

## PATCH 1 — Serveur de fichiers privés

**Problème :** Sans ce patch, les PDF de bulletins de paie sont accessibles via une URL
directe `storage/payslips/...` sans aucune authentification.
N'importe qui avec le lien peut télécharger le bulletin de n'importe quel employé.

**Règle absolue :** Les fichiers privés (PDF, photos, justificatifs) ne sont JAMAIS
servis directement depuis `storage/`. Ils passent toujours par un contrôleur Laravel.

### Structure de stockage (organisation par tenant)

```
storage/app/
├── logos/                           ← Public (logo entreprise)
│   └── {company_uuid}.png
└── private/
    └── {tenant_uuid}/
        ├── payslips/                ← PDF bulletins (PRIVÉ)
        │   └── {year}/{month}/{employee_id}.pdf
        ├── attachments/             ← Justificatifs absences (PRIVÉ)
        │   └── {absence_id}.{ext}
        ├── photos/                  ← Photos de pointage (PRIVÉ)
        │   └── {employee_id}/{date}.jpg
        └── exports/                 ← CSV virements (PRIVÉ, temporaire 24h)
            └── virements_{period}.csv
```

### Contrôleur de fichiers privés

```php
// app/Http/Controllers/Shared/PrivateFileController.php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payroll;
use App\Models\Tenant\Absence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateFileController extends Controller
{
    /**
     * GET /api/v1/storage/payslips/{payrollId}
     * Sert le PDF d'un bulletin de paie après vérification des droits.
     */
    public function servePayslip(Request $request, int $payrollId)
    {
        $payroll  = Payroll::findOrFail($payrollId);
        $user     = $request->user();
        $company  = app('current_company');

        // Vérification des droits : employé voit seulement son propre bulletin
        // Manager/Comptable voit tous les bulletins de l'entreprise
        $canView = $user->id === $payroll->employee_id
                || in_array($user->manager_role, ['principal', 'rh', 'comptable']);

        if (!$canView) {
            abort(403, 'Accès non autorisé à ce bulletin.');
        }

        if (!$payroll->pdf_path || !Storage::exists($payroll->pdf_path)) {
            abort(404, 'Fichier PDF non trouvé. Il est peut-être encore en cours de génération.');
        }

        // Stream sécurisé : pas de redirect, pas d'URL publique
        return response()->stream(function () use ($payroll) {
            echo Storage::get($payroll->pdf_path);
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletin_' . $payroll->period . '.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /api/v1/storage/attachments/{absenceId}
     * Sert le justificatif d'une absence.
     */
    public function serveAttachment(Request $request, int $absenceId)
    {
        $absence = Absence::findOrFail($absenceId);
        $user    = $request->user();

        $canView = $user->id === $absence->employee_id
                || in_array($user->manager_role, ['principal', 'rh']);

        if (!$canView) {
            abort(403);
        }

        if (!$absence->attachment_path || !Storage::exists($absence->attachment_path)) {
            abort(404, 'Justificatif non trouvé.');
        }

        $mimeType = Storage::mimeType($absence->attachment_path);
        $fileName = basename($absence->attachment_path);

        return response()->stream(function () use ($absence) {
            echo Storage::get($absence->attachment_path);
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
```

### Routes à ajouter dans routes/tenant.php

```php
// Ces routes passent par auth:sanctum + tenant + sub.check
// (fichiers privés nécessitent une session valide)

Route::prefix('storage')->group(function () {
    Route::get('/payslips/{payrollId}',     [PrivateFileController::class, 'servePayslip']);
    Route::get('/attachments/{absenceId}',  [PrivateFileController::class, 'serveAttachment']);
});
```

### Job GeneratePayslipPdfJob — chemin de stockage correct

```php
// app/Jobs/GeneratePayslipPdfJob.php

public function handle(): void
{
    $company = app('current_company');

    // Chemin PRIVÉ — jamais dans storage/app/public/
    $path = "private/{$company->uuid}/payslips/{$this->year}/{$this->month}/{$this->employee->id}.pdf";

    $pdf = Pdf::loadView('payslips.template', [
        'payroll'  => $this->payroll,
        'employee' => $this->employee,
        'company'  => $company,
    ]);

    Storage::put($path, $pdf->output());

    // Mettre à jour le chemin en base (pas une URL publique — un chemin interne)
    $this->payroll->update([
        'pdf_path' => $path,    // chemin interne Storage
        'pdf_url'  => null,     // JAMAIS d'URL directe
        'status'   => 'validated',
    ]);

    // Notifier l'employé avec un lien vers l'endpoint sécurisé
    $this->employee->notify(new PayslipAvailableNotification(
        payrollId: $this->payroll->id,
        // L'URL dans la notification pointe vers /api/v1/storage/payslips/{id}
        // pas vers un fichier statique
    ));
}
```

---

## PATCH 2 — BOM UTF-8 dans l'export bancaire algérien

**Problème :** Excel sur Windows (très répandu en Algérie) ne détecte pas l'UTF-8
sans BOM. Sans le BOM, tous les noms arabes et caractères accentués (Prénom, etc.)
seront affichés comme du charabia (mojibake).

**Correction dans BankExportService :**

```php
// app/Services/BankExportService.php

public function generateDZGeneric(array $payrolls, string $period): string
{
    // BOM UTF-8 OBLIGATOIRE pour Excel algérien
    $bom = "\xEF\xBB\xBF";

    $header = '"Matricule","Nom","Prénom","IBAN","Banque","Montant","Devise","Motif","Période"';
    $rows   = [$bom . $header];  // ← BOM en tout premier caractère du fichier

    foreach ($payrolls as $payroll) {
        $employee = $payroll->employee;

        // Déchiffrer l'IBAN (champ encrypted)
        $iban = $employee->iban ?? '';  // Cast 'encrypted' déchiffre automatiquement

        $rows[] = implode(',', [
            '"' . $employee->matricule . '"',
            '"' . addslashes($employee->last_name) . '"',
            '"' . addslashes($employee->first_name) . '"',
            '"' . $iban . '"',
            '"' . addslashes($employee->bank_name ?? '') . '"',
            number_format($payroll->net_salary, 2, '.', ''),
            '"DZD"',
            '"Salaire ' . $period . '"',
            '"' . $period . '"',
        ]);
    }

    return implode("\r\n", $rows);  // CRLF pour Windows
}

public function downloadResponse(string $content, string $filename): \Illuminate\Http\Response
{
    return response($content, 200, [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Content-Length'      => strlen($content),
    ]);
}
```

### Utilisation dans PayrollController

```php
// app/Http/Controllers/Tenant/PayrollController.php

public function exportBank(Request $request): \Illuminate\Http\Response
{
    $batchId  = $request->query('batch_id');
    $format   = $request->query('format', 'generic');
    $batch    = PayrollExportBatch::findOrFail($batchId);

    // Vérification : seul comptable ou principal peut exporter
    $this->authorize('exportBank', $batch);

    $payrolls = Payroll::where('batch_id', $batchId)
        ->with('employee')
        ->get()
        ->toArray();

    $content  = $this->bankExportService->generateDZGeneric($payrolls, $batch->period);
    $filename = "virements_{$batch->period}_" . now()->format('Ymd_His') . ".csv";

    return $this->bankExportService->downloadResponse($content, $filename);
}
```

---

## TESTS OBLIGATOIRES

```php
// tests/Feature/Payroll/PayslipSecurityTest.php

it('payslip PDF is not accessible without auth', function () {
    $payroll = Payroll::factory()->create(['pdf_path' => 'private/xxx/payslips/2026/4/1.pdf']);

    $this->getJson("/api/v1/storage/payslips/{$payroll->id}")
         ->assertStatus(401);
});

it('employee cannot access another employee payslip', function () {
    [$company, $employeeA, $tokenA] = createEmployeeWithToken();
    $employeeB = Employee::factory()->inSchema($company)->create();
    $payroll   = Payroll::factory()->create(['employee_id' => $employeeB->id]);

    $this->withToken($tokenA)
         ->getJson("/api/v1/storage/payslips/{$payroll->id}")
         ->assertStatus(403);
});

it('employee can download their own payslip', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    Storage::fake();
    Storage::put("private/{$company->uuid}/payslips/2026/4/{$employee->id}.pdf", 'PDF_CONTENT');

    $payroll = Payroll::factory()->create([
        'employee_id' => $employee->id,
        'pdf_path'    => "private/{$company->uuid}/payslips/2026/4/{$employee->id}.pdf",
    ]);

    $response = $this->withToken($token)
         ->get("/api/v1/storage/payslips/{$payroll->id}");

    $response->assertStatus(200)
             ->assertHeader('Content-Type', 'application/pdf');
});

it('bank export CSV has UTF-8 BOM for Excel compatibility', function () {
    [$company, $manager, $token] = createManagerWithToken('comptable');
    $batch   = PayrollExportBatch::factory()->create(['period' => '2026-04']);
    Payroll::factory()->count(2)->create(['batch_id' => $batch->id]);

    $response = $this->withToken($token)
         ->get("/api/v1/payroll/export-bank?batch_id={$batch->id}");

    $response->assertStatus(200);

    // Vérifier que le BOM UTF-8 est présent (3 premiers octets)
    $content = $response->getContent();
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
});
```
