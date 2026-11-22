<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\EduManager\Application\Actions\SeedMinimalEduManagerAction;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\EduSubject;

/**
 * Kit de données de démonstration de la verticale EduManager (#9785).
 *
 * Un directeur qui active le pack École arrive sur un espace vide : il ne peut
 * juger ni les classes, ni les inscriptions, ni le portail responsable légal
 * avant d'avoir tout saisi. Ce kit installe un établissement de démonstration
 * COMPLET mais manifestement FICTIF, pour que l'espace soit « vivant » dès la
 * première connexion.
 *
 * Contenu (aucune PII réelle — noms d'élèves et de responsables volontairement
 * génériques, aucun contact réel) :
 *   - le référentiel neutre (campus, année, matières, types de frais) via
 *     `SeedMinimalEduManagerAction` — jamais dupliqué ;
 *   - 3 classes de démonstration (6e A, 5e B, 4e C) ;
 *   - 6 élèves fictifs, inscrits dans ces classes ;
 *   - 3 responsables légaux fictifs, rattachés aux élèves.
 *
 * Garanties du contrat `DemoDataKit` :
 *   - IDEMPOTENCE : toutes les insertions sont ancrées sur des codes ou
 *     numéros STABLES (`DEMO-*`) et passent par `firstOrCreate` — rejouer
 *     `seed()` laisse l'état identique, sans doublon ;
 *   - TENANT-SCOPED : tout s'exécute dans `TenantManager::withinTenant`, et
 *     chaque requête filtre explicitement sur `company_id`.
 *
 * Aucun import inter-modules (garde d'isolation #5584).
 */
final class EduDemoDataKit implements DemoDataKit
{
    /** Préfixe des codes de démonstration (ancre d'idempotence). */
    private const DEMO_PREFIX = 'DEMO';

    /**
     * Classes de démonstration : code stable → libellé + niveau.
     *
     * @var array<int, array{code: string, name: string, level: string, capacity: int}>
     */
    private const DEMO_CLASSES = [
        ['code' => 'DEMO-6A', 'name' => '6e A (démo)', 'level' => '6', 'capacity' => 30],
        ['code' => 'DEMO-5B', 'name' => '5e B (démo)', 'level' => '5', 'capacity' => 30],
        ['code' => 'DEMO-4C', 'name' => '4e C (démo)', 'level' => '4', 'capacity' => 28],
    ];

    /**
     * Élèves fictifs : numéro stable → nom d'affichage + classe de rattachement.
     *
     * @var array<int, array{number: string, name: string, class_code: string}>
     */
    private const DEMO_STUDENTS = [
        ['number' => 'DEMO-0001', 'name' => 'Élève Démo Un', 'class_code' => 'DEMO-6A'],
        ['number' => 'DEMO-0002', 'name' => 'Élève Démo Deux', 'class_code' => 'DEMO-6A'],
        ['number' => 'DEMO-0003', 'name' => 'Élève Démo Trois', 'class_code' => 'DEMO-5B'],
        ['number' => 'DEMO-0004', 'name' => 'Élève Démo Quatre', 'class_code' => 'DEMO-5B'],
        ['number' => 'DEMO-0005', 'name' => 'Élève Démo Cinq', 'class_code' => 'DEMO-4C'],
        ['number' => 'DEMO-0006', 'name' => 'Élève Démo Six', 'class_code' => 'DEMO-4C'],
    ];

    /**
     * Responsables légaux fictifs : clé stable → nom + élèves rattachés.
     *
     * @var array<int, array{key: string, first_name: string, last_name: string, students: list<string>}>
     */
    private const DEMO_GUARDIANS = [
        ['key' => 'DEMO-G1', 'first_name' => 'Parent', 'last_name' => 'Démo Un', 'students' => ['DEMO-0001', 'DEMO-0002']],
        ['key' => 'DEMO-G2', 'first_name' => 'Parent', 'last_name' => 'Démo Deux', 'students' => ['DEMO-0003', 'DEMO-0004']],
        ['key' => 'DEMO-G3', 'first_name' => 'Parent', 'last_name' => 'Démo Trois', 'students' => ['DEMO-0005', 'DEMO-0006']],
    ];

    public function __construct(
        private readonly SeedMinimalEduManagerAction $minimalSeed,
        private readonly TenantManager $tenants,
    ) {}

    public function seed(Company $company): void
    {
        // Référentiel neutre d'abord (campus, année, matières, frais) : le kit
        // s'appuie dessus et ne le duplique jamais.
        $this->minimalSeed->execute($company);

        $this->tenants->withinTenant($company, function () use ($company): void {
            $campus = $this->campus($company);
            $year = $this->academicYear($company);

            if (! $campus instanceof EduCampus || ! $year instanceof EduAcademicYear) {
                // Sans campus ni année, une classe ne peut pas exister : le
                // référentiel minimal vient d'être créé, ce cas ne devrait pas
                // arriver — on sort proprement plutôt que d'écrire à moitié.
                return;
            }

            $classes = $this->seedClasses($company, $campus, $year);
            $students = $this->seedStudents($company, $classes, $year);
            $this->seedGuardians($company, $students);
        });
    }

    private function campus(Company $company): ?EduCampus
    {
        /** @var EduCampus|null $campus */
        $campus = EduCampus::query()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        return $campus;
    }

    private function academicYear(Company $company): ?EduAcademicYear
    {
        /** @var EduAcademicYear|null $year */
        $year = EduAcademicYear::query()
            ->where('company_id', $company->id)
            ->where('status', EduAcademicYear::STATUS_ACTIVE)
            ->orderBy('start_date')
            ->first();

        return $year;
    }

    /**
     * Classes de démonstration, ancrées sur `(company_id, academic_year_id, name)`
     * (index unique en base) — rejouer ne crée aucun doublon.
     *
     * @return array<string, EduClass> code de démonstration → classe
     */
    private function seedClasses(Company $company, EduCampus $campus, EduAcademicYear $year): array
    {
        $classes = [];

        foreach (self::DEMO_CLASSES as $definition) {
            /** @var EduClass $class */
            $class = EduClass::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'academic_year_id' => $year->getKey(),
                    'name' => $definition['name'],
                ],
                [
                    'campus_id' => $campus->getKey(),
                    'code' => $definition['code'],
                    'grade_level' => $definition['level'],
                    'level' => $definition['level'],
                    'capacity' => $definition['capacity'],
                    'status' => EduClass::STATUS_ACTIVE,
                ]
            );

            $classes[$definition['code']] = $class;
        }

        return $classes;
    }

    /**
     * Élèves fictifs + inscription dans leur classe de démonstration.
     *
     * @param  array<string, EduClass>  $classes
     * @return array<string, EduStudent> numéro d'élève → élève
     */
    private function seedStudents(Company $company, array $classes, EduAcademicYear $year): array
    {
        $students = [];

        foreach (self::DEMO_STUDENTS as $definition) {
            /** @var EduStudent $student */
            $student = EduStudent::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'student_number' => $definition['number'],
                ],
                [
                    'display_name' => $definition['name'],
                    'status' => EduStudent::STATUS_ACTIVE,
                    'metadata' => ['demo' => true],
                ]
            );

            $students[$definition['number']] = $student;

            $class = $classes[$definition['class_code']] ?? null;

            if ($class instanceof EduClass) {
                EduClassEnrollment::query()->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'class_id' => $class->getKey(),
                        'student_id' => $student->getKey(),
                    ],
                    [
                        'academic_year_id' => $year->getKey(),
                        'enrolled_at' => now(),
                        'status' => EduClassEnrollment::STATUS_ACTIVE,
                    ]
                );
            }
        }

        return $students;
    }

    /**
     * Responsables légaux fictifs + rattachement aux élèves.
     *
     * `EduGuardian` n'a pas de colonne `code` : l'idempotence s'appuie sur le
     * couple `(company_id, first_name, last_name)` — les noms de démonstration
     * sont uniques par construction.
     *
     * @param  array<string, EduStudent>  $students
     */
    private function seedGuardians(Company $company, array $students): void
    {
        foreach (self::DEMO_GUARDIANS as $definition) {
            /** @var EduGuardian $guardian */
            $guardian = EduGuardian::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                ],
                [
                    'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
                ]
            );

            foreach ($definition['students'] as $studentNumber) {
                $student = $students[$studentNumber] ?? null;

                if (! $student instanceof EduStudent) {
                    continue;
                }

                EduStudentGuardian::query()->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'student_id' => $student->getKey(),
                        'guardian_id' => $guardian->getKey(),
                    ],
                    [
                        'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
                        'can_view_grades' => true,
                        'can_receive_notifications' => true,
                    ]
                );
            }
        }
    }
}
