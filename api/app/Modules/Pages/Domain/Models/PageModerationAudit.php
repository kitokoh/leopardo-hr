<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal de modération APPEND-ONLY d'une Page (BC PAGES, #8729).
 *
 * ## Immuabilité réelle, pas conventionnelle
 *
 * Un trigger PostgreSQL rejette tout `UPDATE` et tout `DELETE` sur cette table
 * (migration `2026_10_28_000200_8729`). Ce modèle ne l'expose donc pas comme
 * une ressource éditable : il n'y a **aucune** méthode de mise à jour, et
 * `$timestamps = false` empêche Eloquent d'écrire un `updated_at` qui
 * n'existe pas.
 *
 * ## Pourquoi ne pas réutiliser `audit_logs`
 *
 * `audit_logs` est un journal applicatif générique, mutable. L'exigence de
 * #8729 est un audit **immuable** : c'est la trace qu'un contestataire
 * voudrait effacer. Un journal qu'un opérateur peut réécrire ne prouve rien.
 *
 * ## EXCEPTION TENANT-SCOPE (#7999)
 *
 * `company_id` est NULLABLE et n'est **jamais un critère de lecture** : la
 * seule lecture applicative filtre par `page_id` (déjà résolu dans le schéma
 * tenant), et l'écriture dénormalise `$page->company_id` pour que la trace
 * reste interprétable hors contexte tenant (export d'audit, réclamation).
 *
 * Le trait `BelongsToCompany` est inapplicable ici, et pas seulement
 * inutile : son scope global fail-closed ajouterait un `WHERE company_id = ?`
 * à une table dont l'immuabilité est garantie par trigger. Une ligne écrite
 * avec `company_id` NULL (Page plateforme, ou acteur super-admin hors tenant)
 * deviendrait alors invisible à la lecture — c'est-à-dire exactement la trace
 * qu'un contestataire voudrait faire disparaître. Même régime que
 * `FundraisingPaymentEvent` et `PaymentWebhookEvent`.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $page_id
 * @property string $actor_id
 * @property string|null $actor_role
 * @property string $action
 * @property string $reason
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property Carbon $created_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PageModerationAudit extends Model
{
    protected $table = 'page_moderation_audits';

    /** Une ligne immuable n'a pas d'état « après ». */
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'page_id',
        'actor_id',
        'actor_role',
        'action',
        'reason',
        'before',
        'after',
        'request_id',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
