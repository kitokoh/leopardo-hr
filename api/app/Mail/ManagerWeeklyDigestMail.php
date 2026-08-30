<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Digest hebdomadaire manager — résumé RH envoyé chaque lundi matin (#5695).
 *
 * Contient :
 * - Absences en attente de validation (nombre)
 * - Présences moyennes de la semaine écoulée
 * - Nouveaux employés créés cette semaine
 * - Contrats arrivant à échéance (30 j)
 */
class ManagerWeeklyDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   manager_name: string,
     *   company_name: string,
     *   week_label: string,
     *   pending_absences: int,
     *   avg_attendance_pct: int,
     *   new_employees: int,
     *   expiring_contracts: int,
     *   app_url: string,
     * }  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        $subject = match ($this->locale) {
            'ar' => "ملخص أسبوعي للموارد البشرية — {$this->data['company_name']}",
            'tr' => "Haftalık HR Özeti — {$this->data['company_name']}",
            'en' => "Weekly HR Digest — {$this->data['company_name']}",
            default => "Résumé RH hebdomadaire — {$this->data['company_name']}",
        };

        return $this
            ->subject($subject)
            ->view('emails.manager-weekly-digest', [
                'data'   => $this->data,
                'locale' => $this->locale,
            ]);
    }
}
