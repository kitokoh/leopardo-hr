<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #5820 (EDU-004) — Cycle de vie des dossiers d'inscription.
 *
 * Conversion dossier → élève IDEMPOTENTE, détection de doublons candidats et
 * traçabilité du consentement contact. Tout est strictement borné au tenant
 * courant (`currentCompany()`, helper existant — api/app/helpers.php) : un
 * dossier d'un autre tenant est refusé avant toute écriture.
 *
 * Découplage CRM : le CRM commercial plateforme (`marketing_leads`) n'est
 * JAMAIS accessible depuis EduManager ; `contact_reference` est une simple
 * chaîne chiffrée (cast `encrypted`), sans FK (spec §6.4).
 */
class AdmissionService
{
    /**
     * Fenêtre de recherche des doublons potentiels (admissions récentes).
     */
    public const DUPLICATE_LOOKBACK_DAYS = 90;

    /**
     * Convertit un dossier d'inscription en élève — idempotente.
     *
     * Si `$admission->student_id` est déjà renseigné, l'élève existant est
     * retourné sans nouvelle écriture (no-op). Sinon : création de
     * l'`EduStudent` (student_number auto-généré unique par tenant,
     * display_name depuis applicant_name, statut active), rattachement du
     * dossier (`student_id`), passage à `enrolled` et traçage
     * `decided_by`/`decided_at`.
     *
     * @param  array{
     *     birth_date_encrypted?: string|null,
     *     metadata?: array<string, mixed>|null,
     * }  $studentData  Données complémentaires de l'élève (PII chiffrée).
     *
     * @throws TenantContextMissingException Dossier d'un autre tenant.
     */
    public function convert(EduAdmission $admission, array $studentData): EduStudent
    {
        $this->assertSameTenant($admission);

        return DB::transaction(function () use ($admission, $studentData): EduStudent {
            // Idempotence : déjà converti → retourne l'élève existant (no-op).
            if ($admission->student_id !== null) {
                $existing = EduStudent::query()->find($admission->student_id);

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var EduStudent $student */
            $student = EduStudent::query()->create([
                'company_id' => $admission->company_id,
                'student_number' => $this->nextStudentNumber($admission->company_id),
                'display_name' => $admission->applicant_name,
                'birth_date_encrypted' => $studentData['birth_date_encrypted'] ?? null,
                'status' => EduStudent::STATUS_ACTIVE,
                'metadata' => $studentData['metadata'] ?? null,
            ]);

            $admission->update([
                'student_id' => $student->id,
                'status' => EduAdmission::STATUS_ENROLLED,
                'decided_at' => now(),
                'decided_by' => $this->currentDeciderId(),
            ]);

            return $student;
        });
    }

    /**
     * Détecte un doublon potentiel parmi les admissions récentes du tenant :
     * même nom normalisé (SQL, en clair) ET même référence de contact
     * (comparée côté PHP après déchiffrement — la colonne est chiffrée au
     * repos et non interrogeable en base).
     *
     * Sans référence de contact fournie, le nom seul suffit à signaler un
     * doublon potentiel (conservateur — revue humaine).
     */
    public function detectDuplicates(string $applicantName, ?string $contactRef = null): bool
    {
        $normalizedName = $this->normalizeName($applicantName);

        if ($normalizedName === '') {
            return false;
        }

        /** @var Collection<int, EduAdmission> $candidates */
        $candidates = EduAdmission::query()
            // Fenêtre de recherche bornée (pas d'index fonctionnel sur le nom
            // normalisé — les accents rendent une normalisation SQL fragile).
            ->where('created_at', '>=', now()->subDays(self::DUPLICATE_LOOKBACK_DAYS))
            ->get(['id', 'applicant_name', 'contact_reference'])
            ->filter(fn (EduAdmission $candidate): bool => $this->normalizeName((string) $candidate->applicant_name) === $normalizedName);

        if ($contactRef === null || $contactRef === '') {
            return $candidates->isNotEmpty();
        }

        foreach ($candidates as $candidate) {
            $storedRef = $candidate->contact_reference;

            if ($storedRef !== null && hash_equals($contactRef, $storedRef)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marque le consentement contact (marketing) du dossier et le trace.
     *
     * @throws TenantContextMissingException Dossier d'un autre tenant.
     */
    public function giveConsent(EduAdmission $admission): void
    {
        $this->assertSameTenant($admission);

        $admission->update([
            'consent_marketing' => true,
            'consent_at' => now(),
        ]);
    }

    /**
     * Numéro d'élève auto-généré, unique par tenant : séquence numérique
     * (max()+1) préfixée « E- », avec garde anti-collision.
     */
    private function nextStudentNumber(string $companyId): string
    {
        $sequence = (int) EduStudent::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->max('student_number');

        do {
            ++$sequence;
            $number = 'E-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        } while (EduStudent::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('student_number', $number)
            ->exists());

        return $number;
    }

    /**
     * Employé courant (décideur) — null hors contexte authentifié (jobs...).
     */
    private function currentDeciderId(): ?int
    {
        $user = auth()->user();

        return $user instanceof Employee ? (int) $user->id : null;
    }

    /**
     * Garde tenant : le dossier doit appartenir à la compagnie courante.
     *
     * @throws TenantContextMissingException Dossier d'un autre tenant (403).
     */
    private function assertSameTenant(EduAdmission $admission): void
    {
        if ($admission->company_id !== currentCompany()->id) {
            throw new TenantContextMissingException;
        }
    }

    private function normalizeName(string $applicantName): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $applicantName));
        // Translittération ASCII : 'Aïcha' → 'Aicha' (les accents ne doivent pas
        // faire échouer la détection de doublon, cf. test duplicate detection).
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($transliterated !== false) {
            $name = $transliterated;
        }

        return mb_strtolower($name);
    }
}
