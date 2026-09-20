<?php

declare(strict_types=1);

// BC-30 PHARMACY (PHARMA-001..007, #7798-#7804) — messages utilisateur de la
// verticale PharmaManager (PA2-I18N-007 : jamais de français en dur dans les
// services/exceptions, tout passe par ce catalogue).
return [
    'solution_inactive' => 'La solution PharmaManager n\'est pas active pour ce tenant.',
    'insufficient_stock' => 'Stock insuffisant pour le produit #:product : :requested demandé, :available disponible (lots non périmés).',
    'invalid_transition' => 'Transition invalide : :from → :to.',
    'prescription_required' => 'Le produit « :product » exige une ordonnance : fournir prescription_id.',
    'prescription_not_found' => 'Ordonnance introuvable.',
    'product_not_found' => 'Produit introuvable.',
    'batch_not_found' => 'Lot introuvable.',
    'batch_not_found_return' => 'Lot introuvable pour la contre-passation.',
    'order_line_not_found' => 'Ligne de commande introuvable.',
    'quantity_received_positive' => 'La quantité reçue doit être strictement positive.',
    'quantity_dispensed_positive' => 'La quantité délivrée doit être strictement positive.',
    'adjustment_delta_nonzero' => 'Le delta d\'ajustement ne peut pas être nul.',
    'adjustment_reason_required' => 'La raison de l\'ajustement est obligatoire.',
    'void_reason_required' => 'La raison de l\'annulation est obligatoire.',
    'invalid_adjustment_type' => 'Type d\'ajustement invalide.',
    'invalid_dispense_type' => 'Type de délivrance invalide.',
    'empty_order' => 'Une commande doit contenir au moins une ligne.',
    'empty_receipt' => 'Aucune ligne à réceptionner.',
    'empty_sale' => 'Une vente doit contenir au moins une ligne.',
    'over_receipt' => 'Sur-réception refusée sur la ligne #:line : :ordered commandé, :received déjà reçu, :proposed proposé.',
];
