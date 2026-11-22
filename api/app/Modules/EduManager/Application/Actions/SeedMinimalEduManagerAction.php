<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Domain\Models\EduSubject;

/**
 * Amorçage minimal EduManager lors de l'activation de la solution (BOS-016, issue #8205).
 *
 * Crée un campus par défaut, une année académique par défaut, le socle de
 * matières et les types de frais courants si absents.
 * Idempotent : firstOrCreate sur les attributs clés du tenant.
 *
 * Mission « une école crée son espace » (#9786) — un directeur qui active le
 * pack École doit trouver un espace IMMÉDIATEMENT utilisable : sans matière, il
 * ne peut pas affecter d'enseignant à une classe (`edu_teacher_subjects` exige
 * `subject_id`) ; sans type de frais, il ne peut pas facturer (`edu_fee_charges`
 * exige `fee_type_id`). L'amorçage se limite au RÉFÉRENTIEL neutre : aucune
 * donnée d'élève, aucune PII — celles-ci relèvent du kit de démonstration
 * (`EduDemoDataKit`, #9785) que le client installe à la demande.
 *
 * Toutes les écritures passent par `TenantManager::withinTenant` : aucune
 * donnée ne peut être créée hors du tenant reçu. Aucun import inter-modules
 * (garde d'isolation #5584) — tout reste dans `Modules/EduManager`.
 */
final class SeedMinimalEduManagerAction
{
    /**
     * Socle de matières scolaires neutre et internationalisable (#9786).
     *
     * Codes STABLES : ils servent de clé d'idempotence (`(company_id, code)`
     * unique) et de référence dans les tests et les imports.
     *
     * Les libellés passent par le catalogue `edu.*` (PA2-I18N-007) : une
     * constante de classe ne peut pas appeler `__()`, on stocke donc la CLÉ et
     * on la résout à l'écriture.
     *
     * @var array<int, array{code: string, name_key: string, coefficient: string}>
     */
    private const DEFAULT_SUBJECTS = [
        ['code' => 'MATH', 'name_key' => 'edu.subject_math', 'coefficient' => '3.00'],
        ['code' => 'LANG', 'name_key' => 'edu.subject_lang', 'coefficient' => '3.00'],
        ['code' => 'SCI', 'name_key' => 'edu.subject_sci', 'coefficient' => '2.00'],
        ['code' => 'HIST', 'name_key' => 'edu.subject_hist', 'coefficient' => '2.00'],
        ['code' => 'PE', 'name_key' => 'edu.subject_pe', 'coefficient' => '1.00'],
        ['code' => 'ART', 'name_key' => 'edu.subject_art', 'coefficient' => '1.00'],
    ];

    /**
     * Types de frais scolaires courants (#9786), montants à 0 : le directeur
     * n'a qu'à ajuster les montants, pas à deviner la nomenclature.
     *
     * @var array<int, array{code: string, label_key: string, frequency: string}>
     */
    private const DEFAULT_FEE_TYPES = [
        ['code' => 'TUITION', 'label_key' => 'edu.fee_type_tuition', 'frequency' => EduFeeType::FREQUENCY_TERM],
        ['code' => 'CANTEEN', 'label_key' => 'edu.fee_type_canteen', 'frequency' => EduFeeType::FREQUENCY_MONTHLY],
        ['code' => 'TRANSPORT', 'label_key' => 'edu.fee_type_transport', 'frequency' => EduFeeType::FREQUENCY_MONTHLY],
        ['code' => 'REGISTRATION', 'label_key' => 'edu.fee_type_registration', 'frequency' => EduFeeType::FREQUENCY_ONCE],
    ];

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function execute(Company $company): void
    {
        $this->tenants->withinTenant($company, function () use ($company): void {
            $campus = $this->seedCampus($company);

            $this->seedAcademicYear($company);
            $this->seedSubjects($company, $campus);
            $this->seedFeeTypes($company, $campus);
        });
    }

    /**
     * Campus principal du tenant (idempotent). Retourne le campus existant ou
     * nouvellement créé — il sert d'ancrage optionnel aux matières et frais.
     */
    private function seedCampus(Company $company): EduCampus
    {
        /** @var EduCampus|null $campus */
        $campus = EduCampus::query()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if ($campus instanceof EduCampus) {
            return $campus;
        }

        /** @var EduCampus $campus */
        $campus = EduCampus::query()->create([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Campus principal',
            'status' => EduCampus::STATUS_ACTIVE,
            'timezone' => $company->timezone ?? 'UTC',
        ]);

        return $campus;
    }

    private function seedAcademicYear(Company $company): void
    {
        EduAcademicYear::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => '2026-2027',
            ],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'status' => EduAcademicYear::STATUS_ACTIVE,
            ]
        );
    }

    /**
     * Socle de matières — `firstOrCreate` sur `(company_id, code)` : rejouer
     * l'amorçage ne crée jamais de doublon (index unique en base).
     */
    private function seedSubjects(Company $company, ?EduCampus $campus): void
    {
        foreach (self::DEFAULT_SUBJECTS as $subject) {
            EduSubject::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $subject['code'],
                ],
                [
                    'name' => __($subject['name_key']),
                    'campus_id' => $campus?->getKey(),
                    'default_coefficient' => $subject['coefficient'],
                    'status' => EduSubject::STATUS_ACTIVE,
                ]
            );
        }
    }

    /**
     * Types de frais courants — devise du tenant, montant 0 (à ajuster par le
     * directeur). `firstOrCreate` sur `(company_id, code)` : idempotent.
     */
    private function seedFeeTypes(Company $company, ?EduCampus $campus): void
    {
        $currency = $this->currencyFor($company);

        foreach (self::DEFAULT_FEE_TYPES as $feeType) {
            EduFeeType::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $feeType['code'],
                ],
                [
                    'campus_id' => $campus?->getKey(),
                    'label' => __($feeType['label_key']),
                    'amount' => 0,
                    'currency' => $currency,
                    'billing_frequency' => $feeType['frequency'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Devise ISO 4217 du tenant (3 lettres), repli `DZD` — devise du premier
     * marché (RUNBOOK_PILOT_EDUMANAGER §2) et valeur par défaut de la colonne.
     */
    private function currencyFor(Company $company): string
    {
        $currency = is_string($company->currency ?? null) ? strtoupper(trim($company->currency)) : '';

        return strlen($currency) === 3 ? $currency : 'DZD';
    }
}
