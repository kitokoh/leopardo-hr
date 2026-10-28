<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\InvalidPageVerificationTransitionException;
use App\Modules\Pages\Domain\Exceptions\PageTenantMismatchException;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Présence publique d'un établissement (BC PAGES, #8723).
 *
 * ## Deux axes indépendants
 *
 * `status` (publication) et `verification_status` (preuve de propriété) ne se
 * déduisent JAMAIS l'un de l'autre — c'est la décision produit de
 * `docs/design/etablissements-pages.md` §2/§11.4. Publier une Page non
 * vérifiée est légitime (elle s'affiche avec la mention « non vérifiée ») ;
 * vérifier une Page en brouillon l'est aussi (on valide les preuves avant
 * d'ouvrir au public).
 *
 * ## Isolation tenant
 *
 * `company_id` est porté par la ligne (scope global `BelongsToCompany`) mais
 * cela ne suffit pas : une Page pourrait pointer un `site_id` appartenant à une
 * autre société si rien ne le vérifie, et le scope global ne protège pas de
 * ça — il filtre sur `pages.company_id`, pas sur `sites.company_id`. D'où
 * {@see self::assertSameTenantAsSite()} appelé à chaque écriture du `site_id`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $site_id
 * @property string $slug
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property PageVerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property string|null $verification_method
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property string $theme
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $legal
 * @property array<string, mixed>|null $seo
 * @property array<array-key, mixed>|null $locales
 * @property string $default_locale
 * @property string|null $custom_domain
 * @property int $schema_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 *
 * @method static Builder<static> query()
 * @method static Builder<static> published()
 * @method static Builder<static> verified()
 *
 * @mixin Builder<static>
 */
class Page extends Model
{
    use BelongsToCompany;

    /** Motifs de suspension acceptés — allowlist fail-closed (#8729). */
    public const SUSPENSION_REASONS = [
        'ownership_dispute',
        'fraud',
        'illegal_content',
        'impersonation',
        'policy_violation',
        'tenant_request',
    ];

    /** Méthodes de vérification acceptées (#8725). */
    public const VERIFICATION_METHODS = [
        'sms',
        'document',
        'superadmin',
    ];

    /** Locales rendues par le produit (registre partagé `shared/i18n`). */
    public const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'tr'];

    private const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9\-]{0,158}[a-z0-9])?$/';

    protected $table = 'pages';

    protected $fillable = [
        'company_id',
        'site_id',
        'slug',
        'status',
        'published_at',
        'verification_status',
        'verified_at',
        'verification_method',
        'suspended_at',
        'suspension_reason',
        'theme',
        'settings',
        'legal',
        'seo',
        'locales',
        'default_locale',
        'custom_domain',
        'schema_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'verification_status' => PageVerificationStatus::class,
            'settings' => 'array',
            'legal' => 'array',
            'seo' => 'array',
            'locales' => 'array',
            'published_at' => 'datetime',
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'schema_version' => 'integer',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * L'établissement porteur.
     *
     * `withDefault()` n'est PAS utilisé : une Page orpheline (Site supprimé)
     * doit rester visible comme orpheline, pas se voir attribuer un Site
     * factice qui ferait croire à une donnée cohérente.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Blocs de contenu, dans l'ordre d'affichage (#8728).
     *
     * L'ordre est porté par `position` et non par l'ordre d'insertion : un
     * `UPDATE` qui réordonne les lignes ne préserve aucun ordre physique, et
     * deux réponses API pourraient sinon présenter les blocs différemment.
     *
     * @return HasMany<PageBlockModel, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlockModel::class, 'page_id')->orderBy('position');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Pages réellement joignables en public.
     *
     * Une Page suspendue a `status = draft` : le filtre sur le statut suffit,
     * mais on retire aussi explicitement les suspensions pour rendre la règle
     * lisible à l'endroit où elle s'applique (une suspension en cours de
     * traitement ne doit jamais réapparaître).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published->value)
            ->whereNull('suspended_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', PageVerificationStatus::Verified->value);
    }

    /**
     * La Page est-elle servie sur sa surface publique ?
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible()
            && $this->suspended_at === null;
    }

    /**
     * Locales que CETTE Page publie réellement (#8730).
     *
     * `locales` absent (NULL) signifie « toutes les locales supportées » : une
     * Page qui ne restreint rien ne doit pas se voir privée de la version
     * arabe qu'un bloc fournit déjà. Un tableau explicite RESTREINT cet
     * ensemble — c'est le seul cas où la locale par défaut n'est pas suffisante
     * à elle seule, elle s'y ajoute toujours puisque c'est le repli du rendu.
     *
     * Les entrées inconnues sont écartées : annoncer `<html lang="xx">` pour
     * une locale que le produit ne sait pas rendre produirait un `lang` qui
     * ment, ce qui trompe les lecteurs d'écran et les moteurs.
     *
     * @return list<string>
     */
    public function publishedLocales(): array
    {
        $default = in_array($this->default_locale, self::SUPPORTED_LOCALES, true)
            ? $this->default_locale
            : 'fr';

        // Pas de restriction déclarée : la Page publie tout ce que le produit
        // sait rendre. La locale par défaut passe en tête — c'est l'ordre de
        // repli si l'en-tête du visiteur ne correspond à rien.
        if (! is_array($this->locales) || $this->locales === []) {
            /** @var list<string> $all */
            $all = array_values(array_unique(array_merge([$default], self::SUPPORTED_LOCALES)));

            return $all;
        }

        $locales = [$default];

        foreach ($this->locales as $candidate) {
            if (is_string($candidate)
                && in_array($candidate, self::SUPPORTED_LOCALES, true)
                && ! in_array($candidate, $locales, true)
            ) {
                $locales[] = $candidate;
            }
        }

        /** @var non-empty-list<'ar'|'en'|'fr'|'tr'> $locales */
        return $locales;
    }

    // ── Invariants ───────────────────────────────────────────────────────────

    /**
     * Le `site_id` doit appartenir au MÊME tenant que la Page.
     *
     * Appelé par les Actions d'écriture et vérifié aussi au niveau de la base
     * via le scope : c'est la seule façon d'empêcher qu'un POST forgé associe
     * la Page d'un tenant à l'établissement d'un autre.
     *
     * @throws PageTenantMismatchException
     */
    public function assertSameTenantAsSite(): void
    {
        // #7960 — le scope tenant est délibérément contourné : c'est justement
        // le cas inter-tenant qu'on veut DÉTECTER. Passer par le wrapper
        // documenté (plutôt qu'un contournement brut) rend l'exception
        // auditable et nomme sa raison.
        $site = $this->site()
            ->crossTenantForSystemTask('intégrité tenant Page↔Site (#8723)')
            ->first();

        if (! $site instanceof Site) {
            throw PageTenantMismatchException::missingSite($this->site_id);
        }

        if ((string) $site->company_id !== (string) $this->company_id) {
            throw PageTenantMismatchException::crossTenant((string) $this->company_id, $this->site_id);
        }
    }

    /**
     * Normalise un slug public.
     *
     * Règles (critère d'acceptation #8723 : « slugs normalisés, globalement
     * uniques ») :
     *  - translittération ASCII (accents français/turcs, `ي`… rejetés) ;
     *  - minuscules, `-` comme seul séparateur, pas de tiret en bord ;
     *  - longueur 1..160.
     *
     * Retourne `null` si rien d'exploitable ne reste : l'appelant décide alors
     * du message d'erreur (on ne fabrique pas un slug de secours silencieux à
     * partir d'un ID — ce serait une URL opaque impossible à corriger).
     */
    public static function normalizeSlug(string $raw): ?string
    {
        $value = trim($raw);

        // Décomposition NFD puis retrait des diacritiques : « Épicerie »
        // devient « ep", pas « picerie ».
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $value = $decomposed;
            }
        }

        $value = (string) preg_replace('/\p{Mn}+/u', '', $value);
        $value = mb_strtolower($value, 'UTF-8');

        // Tout ce qui n'est pas [a-z0-9] devient un séparateur, puis on
        // compresse — « Chez Léa & Fils » -> « chez-lea-fils ».
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
     * La liste est volontairement courte et explicite : un refus doit être
     * compréhensible par l'utilisateur (« ce nom est réservé »), pas un 500.
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [
            'admin', 'api', 'app', 'assets', 'blog', 'dashboard', 'docs', 'help',
            'leopardo', 'login', 'logout', 'mail', 'marketplace', 'new', 'public',
            'register', 'root', 'settings', 'signup', 'static', 'status', 'support',
            'system', 'vitrine', 'www',
        ];
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, self::reservedSlugs(), true);
    }

    // ── Transitions de publication ───────────────────────────────────────────

    /**
     * Publie la Page.
     *
     * Idempotent : republier une Page déjà publiée ne déplace pas
     * `published_at` (le sitemap et l'ordre de l'annuaire s'appuient dessus ;
     * une republication sans changement de contenu ne doit pas remonter la
     * Page artificiellement en tête).
     */
    public function publish(?Carbon $at = null): void
    {
        if ($this->suspended_at !== null) {
            throw new \DomainException('SUSPENDED_PAGE_CANNOT_BE_PUBLISHED');
        }

        $this->status = PageStatus::Published;
        $this->published_at ??= $at ?? Carbon::now();
    }

    public function unpublish(): void
    {
        $this->status = PageStatus::Draft;
        $this->published_at = null;
    }

    // ── Transitions de vérification ──────────────────────────────────────────

    /**
     * Applique une transition de vérification validée par l'enum.
     *
     * `verified_at`/`verification_method` sont posés UNIQUEMENT sur `verified`
     * et effacés sur toute sortie de cet état : conserver la date d'une preuve
     * révoquée ferait croire à une vérification encore valable (leçon #8024 —
     * un état invérifiable masque le défaut).
     *
     * @throws InvalidPageVerificationTransitionException
     */
    public function transitionVerification(
        PageVerificationStatus $target,
        ?string $method = null,
        ?Carbon $at = null,
    ): void {
        $current = $this->verification_status;

        $current->assertCanTransitionTo($target);

        if ($target === PageVerificationStatus::Verified) {
            if ($method === null || ! in_array($method, self::VERIFICATION_METHODS, true)) {
                throw new \DomainException('PAGE_VERIFICATION_METHOD_REQUIRED');
            }

            $this->verified_at = $at ?? Carbon::now();
            $this->verification_method = $method;
        } else {
            $this->verified_at = null;
            $this->verification_method = null;
        }

        $this->verification_status = $target;
    }

    /**
     * Suspend la Page (décision plateforme, #8729).
     *
     * Force le retour au brouillon : une suspension doit couper l'accès public
     * immédiatement, sans attendre un job de purge de cache. La trace
     * (`suspended_at`, `suspension_reason`, `suspension_actor_id` côté audit)
     * reste pour la modération.
     */
    public function suspend(string $reason, ?Carbon $at = null): void
    {
        if (! in_array($reason, self::SUSPENSION_REASONS, true)) {
            throw new \DomainException('PAGE_SUSPENSION_REASON_INVALID');
        }

        $this->suspended_at = $at ?? Carbon::now();
        $this->suspension_reason = $reason;
        $this->status = PageStatus::Draft;
        $this->published_at = null;
    }

    /**
     * Lève la suspension. La Page revient en brouillon : c'est au tenant de
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
}
