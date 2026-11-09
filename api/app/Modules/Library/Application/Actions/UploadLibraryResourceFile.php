<?php

declare(strict_types=1);

namespace App\Modules\Library\Application\Actions;

use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Infrastructure\Services\LibraryFileService;
use Illuminate\Http\UploadedFile;

/**
 * Téléversement du fichier d'une ressource de la bibliothèque (BC-40 LIBRARY).
 *
 * Action de la couche Application (1 action = 1 execute(), convention du
 * dépôt) : elle délègue la validation MIME/taille et l'écriture au service
 * d'infrastructure `LibraryFileService`, qui construit une clé isolée par
 * tenant. Aucun chemin fourni par le client n'est utilisé.
 */
final class UploadLibraryResourceFile
{
    public function __construct(
        private readonly LibraryFileService $files,
    ) {}

    public function execute(LibraryResource $resource, UploadedFile $file): LibraryResource
    {
        return $this->files->attach($resource, $file);
    }
}
