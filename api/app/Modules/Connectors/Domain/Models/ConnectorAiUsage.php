<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #9925 (BC-14 INTEGRATION) — consommation de tokens d'une connexion IA.
 *
 * Une ligne = un mois (`period` = `YYYY-MM`) de consommation d'une connexion
 * `ai_*` d'un tenant. `input_tokens` / `output_tokens` sont cumulés à chaque
 * appel ; `total_tokens` est maintenu à l'écriture pour éviter de re-sommer.
 *
 * Isolation : `BelongsToCompany` (scope tenant fail-closed #3727) — un tenant
 * ne voit jamais la consommation d'un autre.
 *
 * @property int $id
 * @property string $company_id
 * @property int $connection_id
 * @property string $period
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $total_tokens
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ConnectorAiUsage extends Model
{
    use \App\Shared\Traits\BelongsToCompany;

    protected $table = 'connector_ai_usage';

    protected $fillable = [
        'company_id',
        'connection_id',
        'period',
        'input_tokens',
        'output_tokens',
        'total_tokens',
    ];

    protected $casts = [
        'connection_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
    ];

    /**
     * Période de facturation d'un instant (défaut : maintenant), au format
     * `YYYY-MM` — la clé de regroupement du budget mensuel.
     */
    public static function periodFor(?Carbon $at = null): string
    {
        return ($at ?? Carbon::now())->format('Y-m');
    }

    /**
     * Représentation API : la consommation d'un mois, jamais de secret.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'connection_id' => $this->connection_id,
            'period' => $this->period,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'total_tokens' => $this->total_tokens,
        ];
    }
}