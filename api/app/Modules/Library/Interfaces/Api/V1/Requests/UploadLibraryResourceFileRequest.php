<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Téléversement du fichier d'une ressource de la bibliothèque (surface tenant).
 *
 * La validation MIME RÉELLE (contenu, pas seulement l'extension) et la taille
 * sont revérifiées côté service (`LibraryFileService`) : la règle `mimes` ici
 * n'est qu'un premier filtre, elle ne remplace pas la vérification serveur.
 */
class UploadLibraryResourceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = max(1, (int) config('library.max_upload_mb', 50)) * 1024;

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:pdf,epub,txt,html',
            ],
        ];
    }
}
