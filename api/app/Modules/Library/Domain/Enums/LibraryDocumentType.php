<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Enums;

/**
 * Type de document d'une ressource de la bibliothèque.
 */
enum LibraryDocumentType: string
{
    case Book = 'book';
    case Ebook = 'ebook';
    case Guide = 'guide';
    case Manual = 'manual';
    case Report = 'report';
    case Article = 'article';
}
