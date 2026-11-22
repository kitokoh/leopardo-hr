<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document commercial — BC-36 TRADE, #9779.
 *
 * ## Ce que cette ressource ne sort JAMAIS
 *
 * - **`path`** et **`disk`** : ce sont des details de stockage. Les exposer
 *   donnerait la forme des chemins internes, et surtout laisserait croire
 *   qu'une URL publique existe — elle n'existe pas. Le telechargement passe
 *   uniquement par `GET /trade/documents/{document}/download`, qui verifie le
 *   perimetre tenant avant de servir le fichier.
 * - **`uploaded_by_employee_id`** : l'identifiant d'un collegue dans une charge
 *   utile de liste est une donnee d'audit interne, pas une donnee de dossier.
 *
 * `size_bytes` est la taille CONSTATE a la reception (`getSize()`), jamais un
 * `Content-Length` declare par le client.
 *
 * @mixin TradeDocument
 */
final class TradeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            // Le libelle vient de l'enum, pas du client : un document ne peut
            // pas s'auto-nommer « Facture commerciale » s'il est un brouillon.
            'type_label' => $this->type instanceof \App\Modules\Trade\Domain\Enums\TradeDocumentType
                ? $this->type->label()
                : null,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'trade_order_id' => $this->trade_order_id,
            'trade_quote_id' => $this->trade_quote_id,
            'trade_rfq_id' => $this->trade_rfq_id,
            'trade_deal_id' => $this->trade_deal_id,
            'shared_at' => $this->shared_at?->toIso8601String(),
            // Le telechargement est une ROUTE, jamais une URL de stockage.
            'download_url' => url('/api/v1/trade/documents/'.$this->id.'/download'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
