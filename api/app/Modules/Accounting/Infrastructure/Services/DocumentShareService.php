<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Partage sécurisé des documents comptables (issue #5225).
 *
 * Génère un token aléatoire unique + expiration pour un document et un email
 * destinataire. L'accès est strictement limité au document partagé (RGPD) :
 * la résolution ne retourne JAMAIS le document sans token valide et non expiré.
 */
final class DocumentShareService
{
    public const DEFAULT_TTL_DAYS = 14;

    public function createShare(
        AccountingDocument $document,
        string $email,
        ?Carbon $expiresAt = null,
    ): AccountingDocumentShare {
        $expiresAt ??= now()->addDays(self::DEFAULT_TTL_DAYS);

        /** @var AccountingDocumentShare $share */
        $share = AccountingDocumentShare::create([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'share_token' => $this->uniqueToken(),
            'shared_with_email' => $email,
            'expires_at' => $expiresAt,
        ]);

        return $share;
    }

    public function resolve(string $token): ?AccountingDocumentShare
    {
        /** @var AccountingDocumentShare|null $share */
        $share = AccountingDocumentShare::query()
            ->with('document')
            ->where('share_token', $token)
            ->first();

        if ($share === null || $share->isExpired()) {
            return null;
        }

        return $share;
    }

    public function portalUrl(AccountingDocumentShare $share): string
    {
        /** @var string $baseUrl */
        $baseUrl = config('app.frontend_url') ?? config('app.url') ?? '';

        return rtrim($baseUrl, '/').'/documents/shared/'.$share->share_token;
    }

    public function downloadUrl(AccountingDocumentShare $share): string
    {
        return url('/api/v1/accounting/documents/shared/'.$share->share_token.'/download');
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (AccountingDocumentShare::query()->where('share_token', $token)->exists());

        return $token;
    }
}
