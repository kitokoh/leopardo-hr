<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Models;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Exceptions\BrandPublishException;
use App\Modules\Branding\Domain\Exceptions\BrandSlugException;
use App\Modules\Branding\Domain\Palette\BrandPalette;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Enseigne d'un tenant (BC-41, #9578).
 *
 * ## Le problème que cet agrégat résout
 *
 * Jusqu'ici, le branding était un JSON libre dans `companies.metadata.branding`,
 * logé dans le module HR. Conséquence : **une entreprise ne peut avoir qu'une
 * seule marque**. Un groupe qui exploite deux enseignes (un restaurant et une
 * pharmacie, ou deux restaurants de positionnements différents) devait créer
 * deux tenants — donc deux jeux de données, deux facturations, deux équipes.
 *
 * `Brand` est un agrégat **multi-instances** : un tenant en a 1..n, chacune avec
 * son slug, son identité, sa palette, son domaine et sa verticale.
 *
 * ## Ce que `Brand` n'est PAS
 *
 * - Ce n'est pas un `Site` : un Site est un ÉTABLISSEMENT (adresse, horaires,
 *   GPS). Deux enseignes peuvent partager un établissement, et une enseigne
 *   peut avoir plusieurs établissements. Deux axes orthogonaux.
 * - Ce n'est pas une `Page` : une Page est la surface d'un établissement (contenu,
 *   vérification, modération). Une Page sans Brand explicite hérite de la Brand
 *   par défaut du tenant — c'est ce qui rend la migration non destructive.
 *
 * ## Invariants portés ici (et par le schéma)
 *
 * | # | Invariant | Mécanisme |
 * |---|---|---|
 * | I1 | Une seule Brand `is_default` par tenant | index partiel unique en base |
 * | I2 | Slug unique par tenant, normalisé, non réservé | `normalizeSlug()` + unique |
 * | I3 | `on_primary` contraste ≥ 4.5:1 | `BrandPalette` (calcul serveur) |
 * | I4 | Publier exige `display_name` | `publish()` |
 * | I5 | Suspendre exige un motif d'allowlist | `suspend()` |
 * | I6 | `vertical` ∈ registre fermé | enum `BrandVertical` |
 * | I7 | Un host = une Brand | unique partiel en base |
 * | I8 | Brand suspendue ne sert pas | `isPubliclyVisible()` |
 *
 * @property int $id
 * @property string $company_id
 * @property string $slug
 * @property string $name
 * @property BrandVertical $vertical
 * @property BrandStatus $status
 * @property bool $is_default
 * @property array<string, mixed>|null $palette
 * @property array<string, mixed>|null $identity
 * @property array<string, mixed>|null $surface
 * @property string|null $host
 * @property Carbon|null $published_at
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 * @method static Builder<static> published()
 *
 * @mixin Builder<static>
 */
class Brand extends Model
{
    use BelongsToCompany;

    /** Motifs de suspension acceptés — allowlist fail-closed. */
    public const SUSPENSION_REASONS = [
        'ownership_dispute',
        'fraud',
        'illegal_content',
        'impersonation',
        'policy_violation',
        'tenant_request',
    ];

    /** Locales rendues par le produit (registre partagé `shared/i18n`). */
    public const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'tr'];

    private const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9\-]{0,158}[a-z0-9])?$/';

    protected $table = 'brands';

    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'vertical',
        'status',
        'is_default',
        'palette',
        'identity',
        'surface',
        'host',
        'published_at',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vertical' => BrandVertical::class,
            'status' => BrandStatus::class,
            'is_default' => 'boolean',
            'palette' => 'array',
            'identity' => 'array',
            'surface' => 'array',
            'published_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    // ── Palette & identité ───────────────────────────────────────────────────

    /**
     * La palette de la Brand, avec `on_primary` RECALCULÉ.
     *
     * La colonne `palette.on_primary` n'est jamais la source de vérité : une
     * valeur écrite par une version antérieure du code, par un import ou par
     * un INSERT direct ne doit pas pouvoir imposer un contraste insuffisant.
     * Le calcul est la source de vérité.
     */
    public function palette(): BrandPalette
    {
        return BrandPalette::fromArray(is_array($this->palette) ? $this->palette : []);
    }

    /**
     * Nom public affiché sur la surface.
     *
     * Repli sur `name` : une Brand créée sans identité détaillée doit quand
     * même pouvoir s'afficher, mais `publish()` exige explicitement
     * `identity.display_name` — le repli sert au brouillon, pas à la mise en
     * ligne.
     */
    public function displayName(): string
    {
        $identity = is_array($this->identity) ? $this->identity : [];
        $display = $identity['display_name'] ?? null;

        return is_string($display) && trim($display) !== '' ? $display : $this->name;
    }

    public function tagline(): ?string
    {
        $identity = is_array($this->identity) ? $this->identity : [];
        $tagline = $identity['tagline'] ?? null;

        return is_string($tagline) && trim($tagline) !== '' ? $tagline : null;
    }

    public function logoUrl(): ?string
    {
        $identity = is_array($this->identity) ? $this->identity : [];
        $logo = $identity['logo_url'] ?? null;

        return is_string($logo) && trim($logo) !== '' ? $logo : null;
    }

    /**
     * Locales que CETTE Brand publie réellement.
     *
     * `surface.locales` absent (NULL) signifie « toutes les locales supportées » :
     * une Brand qui ne restreint rien ne doit pas se voir privée de la version
     * arabe qu'un bloc fournit déjà. Un tableau explicite RESTREINT cet
     * ensemble — la locale par défaut s'y ajoute toujours, puisque c'est le
     * repli du rendu.
     *
     * Les entrées inconnues sont écartées : annoncer `<html lang="xx">` pour
     * une locale que le produit ne sait pas rendre produirait un `lang` qui
     * ment, ce qui trompe les lecteurs d'écran et les moteurs.
     *
     * @return list<string>
     */
    public function publishedLocales(): array
    {
        $surface = is_array($this->surface) ? $this->surface : [];
        $default = $this->defaultLocale();

        $declared = $surface['locales'] ?? null;

        if (! is_array($declared) || $declared === []) {
            /** @var list<string> $all */
            $all = array_values(array_unique(array_merge([$default], self::SUPPORTED_LOCALES)));

            return $all;
        }

        $locales = [$default];

        foreach ($declared as $candidate) {
            if (is_string($candidate)
                && in_array($candidate, self::SUPPORTED_LOCALES, true)
                && ! in_array($candidate, $locales, true)
            ) {
                $locales[] = $candidate;
            }
        }

        /** @var non-empty-list<string> $locales */
        return $locales;
    }

    public function defaultLocale(): string
    {
        $surface = is_array($this->surface) ? $this->surface : [];
        $default = $surface['default_locale'] ?? null;

        return is_string($default) && in_array($default, self::SUPPORTED_LOCALES, true)
            ? $default
            : 'fr';
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Brands réellement joignables en public.
     *
     * Une Brand suspendue a `status = draft` (voir `suspend()`), mais on retire
     * aussi explicitement les suspensions : la règle doit être lisible à
     * l'endroit où elle s'applique.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BrandStatus::Published->value)
            ->whereNull('suspended_at');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible() && $this->suspended_at === null;
    }

    // ── Transitions ──────────────────────────────────────────────────────────

    /**
     * Publie la Brand.
     *
     * Idempotent : republier une Brand déjà publiée ne déplace pas
     * `published_at` (le sitemap et l'ordre de l'annuaire s'appuient dessus).
     *
     * @throws BrandPublishException
     */
    public function publish(?Carbon $at = null): void
    {
        if ($this->suspended_at !== null) {
            throw BrandPublishException::suspended();
        }

        $identity = is_array($this->identity) ? $this->identity : [];
        $display = $identity['display_name'] ?? null;

        if (! is_string($display) || trim($display) === '') {
            throw BrandPublishException::missingDisplayName();
        }

        $this->status->assertCanTransitionTo(BrandStatus::Published);

        $this->status = BrandStatus::Published;
        $this->published_at ??= $at ?? Carbon::now();
    }

    public function unpublish(): void
    {
        $this->status->assertCanTransitionTo(BrandStatus::Draft);

        $this->status = BrandStatus::Draft;
        $this->published_at = null;
    }

    /**
     * Suspend la Brand (décision plateforme).
     *
     * Force le retour au brouillon : une suspension doit couper l'accès public
     * immédiatement, sans attendre un job de purge de cache. La trace
     * (`suspended_at`, `suspension_reason`) reste pour la modération.
     */
    public function suspend(string $reason, ?Carbon $at = null): void
    {
        if (! in_array($reason, self::SUSPENSION_REASONS, true)) {
            throw new \DomainException('BRAND_SUSPENSION_REASON_INVALID');
        }

        $this->suspended_at = $at ?? Carbon::now();
        $this->suspension_reason = $reason;
        $this->status = BrandStatus::Draft;
        $this->published_at = null;
    }

    /**
     * Lève la suspension. La Brand revient en brouillon : c'est au tenant de
     * republier consciemment, on ne republie jamais à sa place.
     */
    public function unsuspend(): void
    {
        $this->suspended_at = null;
        $this->suspension_reason = null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    // ── Slug ─────────────────────────────────────────────────────────────────

    /**
     * Normalise un slug public.
     *
     * Règles : translittération ASCII (accents français/turcs), minuscules,
     * `-` comme seul séparateur, pas de tiret en bord, longueur 1..160.
     *
     * Retourne `null` si rien d'exploitable ne reste : l'appelant décide alors
     * du message d'erreur. On ne fabrique pas un slug de secours silencieux à
     * partir d'un ID — ce serait une URL opaque impossible à corriger.
     */
    public static function normalizeSlug(string $raw): ?string
    {
        $value = trim($raw);

        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $value = $decomposed;
            }
        }

        $value = (string) preg_replace('/\p{Mn}+/u', '', $value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        $value = (string) preg_replace('/-{2,}/', '-', $value);

        if ($value === '' || ! preg_match(self::SLUG_PATTERN, $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Slugs réservés : ils entrent en collision avec des routes existantes de
     * la superapp ou avec des noms de surface réservés par la plateforme.
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [
            'admin', 'api', 'app', 'assets', 'auth', 'blog', 'dashboard', 'docs',
            'help', 'leopardo', 'login', 'logout', 'mail', 'marketplace', 'new',
            'p', 'public', 'register', 'root', 's', 'settings', 'shop', 'signup',
            'static', 'status', 'support', 'system', 'vitrine', 'www',
        ];
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, self::reservedSlugs(), true);
    }

    /**
     * Valide et normalise un slug, ou lève.
     *
     * @throws BrandSlugException
     */
    public static function assertValidSlug(string $raw): string
    {
        $slug = self::normalizeSlug($raw);

        if ($slug === null) {
            throw BrandSlugException::invalid($raw);
        }

        if (self::isReservedSlug($slug)) {
            throw BrandSlugException::reserved($slug);
        }

        return $slug;
    }
}
