<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Modules\Catalog\Domain\Exceptions\InvalidInquiryStatusTransitionException;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage « faire avancer une demande de devis » (BOS-024f, #8217).
 *
 * Extrait de `CatalogInquiryController::updateStatus` : la matrice de
 * transitions (`CatalogInquiryStatus::allowedTransitions()`, spec §7) est un
 * invariant de domaine — statut terminal immuable, transition non autorisee
 * refusee par `InvalidInquiryStatusTransitionException`. La note interne est
 * horodatee et ajoutee a l'historique existant (jamais ecrasee).
 */
final class TransitionCatalogInquiryStatusAction
{
    /**
     * @throws InvalidInquiryStatusTransitionException
     */
    public function execute(CatalogInquiry $inquiry, string $status, ?string $note = null): CatalogInquiry
    {
        $current = $inquiry->status;
        $target = CatalogInquiryStatus::tryFrom($status);

        if ($target === null
            || $current->isTerminal()
            || ! in_array($target, $current->allowedTransitions(), true)) {
            throw new InvalidInquiryStatusTransitionException($current->value);
        }

        $inquiry->status = $target;

        if (is_string($note) && trim($note) !== '') {
            $stamped = '['.Carbon::now()->toIso8601String().'] '.trim($note);
            $inquiry->notes = $inquiry->notes !== null && trim($inquiry->notes) !== ''
                ? $inquiry->notes."\n".$stamped
                : $stamped;
        }

        $inquiry->save();

        return $inquiry;
    }
}
