<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * Resume lisible d'une action en attente de confirmation — extrait de
 * `IntentEngine` par #9887.
 *
 * ## Pourquoi ce n'est pas de la presentation
 *
 * Ce texte est ce que l'utilisateur LIT avant de cliquer « confirmer ». Un
 * resume faux fait confirmer une action que la personne n'a pas comprise :
 * c'est un probleme de securite, pas de style. Deux decisions y sont donc
 * encodees explicitement :
 *
 *  - `create_employee` annonce l'**e-mail d'invitation** (effet externe
 *    visible) : l'utilisateur doit savoir qu'un message partira ;
 *  - `absence_decision` en mode `reject` affiche le **motif** : un refus sans
 *    motif visible serait confirme a l'aveugle.
 *
 * Le `default` reste volontairement generique : un write-tool ajoute sans
 * resume dedie affiche « Confirmer l'action X » plutot que d'inventer une
 * phrase qui decrirait mal l'effet.
 */
final class ConfirmationSummary
{
    public function __construct(
        private readonly ToolArgumentReader $arguments,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function forTool(string $toolName, array $arguments): string
    {
        $startDate = $this->arguments->string($arguments, 'start_date', '?');
        $endDate = $this->arguments->string($arguments, 'end_date', $startDate);
        $absenceId = $this->arguments->string($arguments, 'absence_id', '?');

        return match ($toolName) {
            'create_absence' => sprintf(
                'Creer une demande d\'absence du %s au %s',
                $startDate,
                $endDate,
            ),
            // BOS-004 (#8145) — `approve_absence` (legacy) SUPPRIME : chemin
            // unique = `absence_decision` (Action canonique Planning).
            // B3a (#6856) — decision (approbation/refus motive).
            'absence_decision' => sprintf(
                '%s la demande d\'absence #%s%s',
                $this->arguments->string($arguments, 'decision', '') === 'reject' ? 'Refuser' : 'Approuver',
                $absenceId,
                $this->arguments->string($arguments, 'decision', '') === 'reject'
                    ? ' — motif : '.$this->arguments->string($arguments, 'reason', '(non precise)')
                    : '',
            ),
            // B3b (#6857) — affectation d'un shift a un employe.
            'shift_assign' => sprintf(
                'Affecter le shift #%s a l\'employe #%s',
                $this->arguments->string($arguments, 'schedule_id', '?'),
                $this->arguments->string($arguments, 'employee_id', '?'),
            ),
            // B3c (#6858) — message a une equipe (parite annonces).
            'notify_team' => sprintf(
                'Envoyer « %s » a %s',
                mb_strimwidth($this->arguments->string($arguments, 'title', '?'), 0, 60, '…'),
                $this->arguments->string($arguments, 'audience_type', '') === 'company'
                    ? 'toute l\'entreprise'
                    : 'l\'equipe du departement #'.$this->arguments->string($arguments, 'department_id', '?'),
            ),
            // A7 (#7377) — creation d'un employe : le resume annonce
            // explicitement l'e-mail d'invitation (effet externe visible).
            'create_employee' => sprintf(
                'Creer l\'employe « %s %s » (%s) — un e-mail d\'invitation lui sera envoye',
                $this->arguments->string($arguments, 'first_name', '?'),
                $this->arguments->string($arguments, 'last_name', '?'),
                $this->arguments->string($arguments, 'email', '?'),
            ),
            // A8 (#7378) — pointage.
            'check_in_employee', 'check_out_employee' => sprintf(
                'Pointer %s pour l\'employe #%s',
                $toolName === 'check_out_employee' ? 'la SORTIE' : 'l\'ENTREE',
                $this->arguments->string($arguments, 'employee_id', 'moi-meme'),
            ),

            default => "Confirmer l'action {$toolName}",
        };
    }
}
