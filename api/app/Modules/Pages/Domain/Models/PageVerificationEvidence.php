<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pièce justificative d'un dossier de vérification (BC PAGES, #8725).
 *
 * ## Modèle de confidentialité — le point le plus sensible du chantier
 *
 * Une pièce de vérification contient typiquement un bail, une facture d'énergie
 * ou un extrait de registre : nom, adresse, parfois signature et numéro de
 * compte. **Rien de tout cela ne doit être joignable depuis la surface
 * publique**, ni par URL devinable.
 *
 * Trois règles en découlent, toutes portées par le schéma :
 *
 * 1. **Aucune URL publique n'est stockée.** `storage_disc` + `storage_path`
 *    désignent un disque privé (S3 privé, disque `local`). Il n'existe pas de
 *    `public_url` : le seul moyen d'obtenir le fichier est une URL signée
 *    courte générée côté serveur après contrôle de permission.
 * 2. **`content_hash` obligatoire.** L'empreinte SHA-256 sert à prouver qu'une
 *    pièce n'a pas été remplacée après la décision — un dossier « vérifié »
 *    dont la pièce a changé n'est plus vérifiable. Elle sert aussi à refuser un
 *    doublon exact (le même bail soumis deux fois).
 * 3. **Conservation datée.** `retention_until` est obligatoire : sans échéance
 *    explicite, une pièce privée reste indéfiniment, ce qui viole la
 *    minimisation RGPD. La purge (#8729) s'appuie dessus.
 *
 * Le modèle ne porte **aucun contenu** : ni `content`, ni `extracted_text`.
 * Ce qui n'est pas chargé en mémoire ne peut pas fuiter dans un log ou une
 * sérialisation.
 *
 * @property int $id
 * @property string $company_id
 * @property int $request_id
 * @property string $kind
 * @property string $storage_disc
 * @property string $storage_path
 * @property string $content_hash
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon $retention_until
 * @property Carbon|null $created_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PageVerificationEvidence extends Model
{
    use BelongsToCompany;

    /** Pièces acceptées — allowlist stricte (pas d'exécutable, pas d'archive). */
    public const KINDS = [
        'lease',
        'utility_bill',
        'business_registration',
        'tax_certificate',
        'ownership_title',
        'photo_signage',
    ];

    /** Types MIME acceptés : images et PDF uniquement. */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Taille maximale d'une pièce (10 Mio). */
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /** Conservation par défaut : 24 mois après la décision (minimisation RGPD). */
    public const DEFAULT_RETENTION_MONTHS = 24;

    public $timestamps = false;

    protected $table = 'page_verification_evidence';

    protected $fillable = [
        'company_id',
        'request_id',
        'kind',
        'storage_disc',
        'storage_path',
        'content_hash',
        'mime_type',
        'size_bytes',
        'retention_until',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retention_until' => 'datetime',
            'created_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Vérifie qu'une pièce est acceptable AVANT de l'écrire.
     *
     * @return list<string> Erreurs (vide = acceptable).
     */
    public static function validateDescriptor(string $kind, string $mimeType, int $sizeBytes): array
    {
        $errors = [];

        if (! in_array($kind, self::KINDS, true)) {
            $errors[] = sprintf('Type de pièce non accepté : « %s ».', $kind);
        }

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            // Refuser explicitement les types exécutables/archives : un `.exe`
            // ou un `.zip` dans un dossier de vérification n'a aucune raison
            // d'être, et un antivirus ne remplace pas une allowlist.
            $errors[] = sprintf('Type de fichier non accepté : « %s » (PDF ou image uniquement).', $mimeType);
        }

        if ($sizeBytes <= 0) {
            $errors[] = 'La pièce est vide.';
        } elseif ($sizeBytes > self::MAX_SIZE_BYTES) {
            $errors[] = sprintf('La pièce dépasse %d octets.', self::MAX_SIZE_BYTES);
        }

        return $errors;
    }

    /**
     * Empreinte d'un contenu, pour prouver l'intégrité d'une pièce.
     */
    public static function hashContent(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    /**
     * La pièce doit-elle être purgée à cette date ?
     */
    public function isExpired(?Carbon $at = null): bool
    {
        return $this->retention_until->lessThanOrEqualTo($at ?? Carbon::now());
    }
}
