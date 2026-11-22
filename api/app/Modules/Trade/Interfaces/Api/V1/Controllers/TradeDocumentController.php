<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Domain\Models\TradeDocument;
use App\Modules\Trade\Interfaces\Api\V1\Requests\StoreTradeDocumentRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents commerciaux — BC-36 TRADE, #9779.
 *
 * ## Le trou que ce controleur ferme
 *
 * `TradeDocument`, `TradeDocumentType` (facture pro forma, facture commerciale,
 * packing list, B/L, certificat d'origine...) et le disque `private` existaient
 * depuis TRADE-005/#8480. **Aucune route ne les exposait.** Un exportateur ne
 * pouvait donc pas deposer sa facture commerciale sur son dossier — alors que
 * la checklist douaniere (`GET /trade/customs/checklist`) la lui demande
 * explicitement. L'ecran annoncait une piece a fournir qu'aucun ecran ne
 * permettait de fournir.
 *
 * ## Le stockage n'est jamais celui du client
 *
 * - disque : `private` (config/filesystems.php), jamais un disque public ;
 * - chemin : genere par le serveur (`trade/documents/{uuid}.{ext}`), jamais un
 *   nom fourni — un nom controle par le client permettrait `../` ;
 * - `original_name` : conserve pour l'affichage, mais JAMAIS utilise comme
 *   chemin ;
 * - `size_bytes` / `mime_type` : constates a la reception.
 *
 * ## Telechargement
 *
 * Une route dediee, pas une URL de stockage : `GET /trade/documents/{document}/download`.
 * Le perimetre tenant est applique par le model binding (`BelongsToCompany`) —
 * un document d'une autre entreprise est **404**, donc indistinguable d'un
 * document inexistant.
 */
final class TradeDocumentController
{
    private const DISK = 'private';

    /**
     * GET /trade/documents — liste filtrable par dossier et par type.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeDocument::query()->orderByDesc('id');

        foreach (['trade_order_id', 'trade_quote_id', 'trade_rfq_id', 'trade_deal_id'] as $link) {
            if ($request->filled($link)) {
                $query->where($link, $request->integer($link));
            }
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeDocumentResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    /**
     * POST /trade/documents — depot d'un fichier rattache a un dossier.
     */
    public function store(StoreTradeDocumentRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        // Chemin genere par le serveur : un nom client ne doit jamais devenir
        // un segment de chemin. `Str::uuid()` garantit l'unicite sans reveeler
        // ni le titre du document ni l'identifiant de l'entreprise.
        $path = 'trade/documents/'.Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'bin');

        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $document = TradeDocument::query()->create([
            ...collect($validated)->except('file')->all(),
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            // Constate a la reception, jamais declare par le client.
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => 'draft',
            'uploaded_by_employee_id' => $request->user()?->id,
        ]);

        return new JsonResponse(
            ['data' => TradeDocumentResource::make($document->refresh())->resolve()],
            201,
        );
    }

    /**
     * GET /trade/documents/{document} — detail.
     */
    public function show(TradeDocument $document): JsonResponse
    {
        return new JsonResponse(['data' => TradeDocumentResource::make($document)->resolve()]);
    }

    /**
     * GET /trade/documents/{document}/download — sert le fichier.
     *
     * Le fichier n'est jamais servi depuis une URL de stockage : sans cette
     * route, un document deplace ou prive deviendrait inatteignable, et avec
     * une URL publique il deviendrait lisible par qui devine le chemin.
     */
    public function download(TradeDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name);
    }

    /**
     * DELETE /trade/documents/{document} — suppression LOGIQUE + fichier retire.
     *
     * La ligne est conservee (`SoftDeletes`) pour la tracabilite documentaire
     * exigee par une transaction internationale ; le fichier, lui, est
     * reellement supprime — un document commercial retire ne doit pas rester
     * lisible sur le disque.
     */
    public function destroy(TradeDocument $document): JsonResponse
    {
        $id = $document->id;

        if (Storage::disk($document->disk)->exists($document->path)) {
            Storage::disk($document->disk)->delete($document->path);
        }

        $document->delete();

        return new JsonResponse(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
