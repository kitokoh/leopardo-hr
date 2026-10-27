<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incident équipement FuelStation — FUEL-010 (issue #5804).
 *
 * Workflow audité : open → in_progress → resolved → closed. Chaque
 * transition est horodatée et attribuée (reported_by, assigned_to,
 * resolved_by, closed_by). Notification à l'assignation sans PII
 * (FUEL-019).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $equipment_type pump|tank|meter|other
 * @property int|null $equipment_id
 * @property string $category equipment|power|pos|safety|other
 * @property string $severity low|medium|high|critical
 * @property string $status open|in_progress|resolved|closed
 * @property string $title
 * @property string|null $description
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $closure_notes
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    /**
     * #8578 (D1) — l'état de naissance canonique est `reported` : un incident
     * est SIGNALÉ, puis assigné, puis en cours. L'ancien `open` ne disait rien
     * de plus que « pas encore fermé » et dupliquait `reported` ; deux états
     * pour un seul fait métier obligent chaque rapport et chaque filtre à
     * traiter deux cas, et produisent des statistiques fausses.
     */
    public const STATUS_REPORTED = 'reported';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SUSPENDED = 'suspended';

    /** États exposés en lecture — un seul état de naissance. */
    public const STATUSES = [
        self::STATUS_REPORTED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_SUSPENDED,
    ];

    /** Catégories d'incident (allowlist fermée, #8578 D5). */
    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_SAFETY = 'safety';

    public const CATEGORY_ENVIRONMENT = 'environment';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_EQUIPMENT,
        self::CATEGORY_SAFETY,
        self::CATEGORY_ENVIRONMENT,
        self::CATEGORY_OTHER,
    ];

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH, self::SEVERITY_CRITICAL];

    public const EQUIPMENT_PUMP = 'pump';

    public const EQUIPMENT_TANK = 'tank';

    public const EQUIPMENT_METER = 'meter';

    public const EQUIPMENT_OTHER = 'other';

    public const EQUIPMENT_TYPES = [self::EQUIPMENT_PUMP, self::EQUIPMENT_TANK, self::EQUIPMENT_METER, self::EQUIPMENT_OTHER];

    /**
     * Graphe de workflow — #8578 (D3).
     *
     * Chemin canonique : `reported → assigned → in_progress → resolved → closed`.
     *
     * On ne clôture JAMAIS sans être passé par `resolved`. Clôturer sans
     * résolution vide la clôture de son sens : on ne sait plus si le problème a
     * été réglé ni comment. `closed` doit signifier « réglé et classé », pas
     * « abandonné » — l'abandon a son propre état (`suspended`). C'est un
     * durcissement volontaire : la traçabilité d'audit prime sur la commodité
     * de clôturer vite, sur un module qui alimente la maintenance d'équipement.
     */
    public const TRANSITIONS = [
        self::STATUS_REPORTED => [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_SUSPENDED],
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_SUSPENDED],
        self::STATUS_IN_PROGRESS => [self::STATUS_RESOLVED, self::STATUS_SUSPENDED],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED],
        self::STATUS_SUSPENDED => [self::STATUS_REPORTED, self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    /**
     * Statuts cibles légitimes d'une transition (issue #7398).
     *
     * Doit être exactement l'union des cibles de `TRANSITIONS` — une constante
     * PHP ne peut pas être calculée, donc la cohérence est verrouillée par
     * `FuelStateVocabularyTest::test_transition_targets_match_the_graph()`.
     * Sans ce test, la liste se désynchronise silencieusement du graphe : c'est
     * précisément ce qui s'était produit (`assigned` en était absent).
     *
     * @var list<string>
     */
    public const TRANSITION_TARGETS = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_SUSPENDED,
        // `reported` est une cible légitime : `suspended → reported` rouvre un
        // incident suspendu (constaté par le garde de cohérence, qui a détecté
        // l'écart entre cette liste et le graphe).
        self::STATUS_REPORTED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'severity',
        'status',
        'category',
        'title',
        'description',
        'reported_by',
        'assigned_to',
        'occurred_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'closed_at',
        'closed_by',
        'closure_notes',
        'external_id',
    ];

    /**
     * État de naissance d'un incident.
     *
     * Exposé comme constante pour que le service, la migration et les tests
     * partagent UNE valeur : c'est la divergence entre le défaut SQL (`open`),
     * le défaut du modèle et le graphe qui a produit le blocage de workflow de
     * l'issue #8578.
     */
    public const DEFAULT_STATUS = self::STATUS_REPORTED;

    /**
     * État de naissance posé par le MODÈLE, pas seulement par la colonne.
     *
     * Le défaut SQL seul ne suffit pas : dès qu'une ligne arrive par un chemin
     * qui ne passe pas par `report()` (fixture de test, import, restauration),
     * elle héritait du défaut de colonne — historiquement `open`, un état qui
     * n'existe plus dans le graphe. L'incident naissait alors dans un état
     * SANS transition sortante et devenait immuable (`Transition open →
     * assigned interdite`). Poser le défaut au niveau du modèle rend la
     * naissance canonique indépendante du schéma physique.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $incident): void {
            if ($incident->status === '') {
                $incident->status = self::DEFAULT_STATUS;
            }

            // `category` est declaree NON nullable avec un defaut `equipment`.
            // Depuis le docblock `@property string $category`, PHPStan sait que
            // la valeur n'est jamais `null` : la comparaison a `null` etait du
            // code mort (`identical.alwaysFalse`). On teste la chaine vide,
            // seul cas possible quand une ligne arrive par un chemin qui ne
            // passe pas par `report()` (fixture, import, restauration).
            if ($incident->category === '') {
                $incident->category = self::CATEGORY_EQUIPMENT;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'equipment_id' => 'integer',
            'reported_by' => 'integer',
            'assigned_to' => 'integer',
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
            'closed_at' => 'datetime',
            'closed_by' => 'integer',
        ];
    }

    /** @return HasMany<FuelIncidentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FuelIncidentAttachment::class, 'incident_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FuelMaintenanceTask::class, 'incident_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'station_id');
    }
}
