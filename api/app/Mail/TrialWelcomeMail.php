<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrialWelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly int $trialDays;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $manager,
        public readonly string $tempPassword,
    ) {
        $this->trialDays = $this->resolveTrialDays();
    }

    public function build(): self
    {
        $locale = $this->company->language ?? 'fr';

        // S-5 (#1665) : les vues résolvent leurs chaînes via __() — il faut
        // épingler la locale applicative AVANT le rendu, sinon le corps du
        // mail se rend dans la locale ambiante (Accept-Language / défaut) et
        // le sujet/le corps peuvent être dans des langues différentes.
        \Illuminate\Support\Facades\App::setLocale($locale);

        return $this
            ->subject($this->resolveSubject($locale))
            ->view('emails.trial-welcome', [
                'company' => $this->company,
                'manager' => $this->manager,
                'tempPassword' => $this->tempPassword,
                'locale' => $locale,
                'trialDays' => $this->trialDays,
            ]);
    }


    /**
     * Durée d'essai réelle affichée dans l'email : dérivée du provisioning
     * (subscription_start → subscription_end), avec repli sur le plan.
     */
    private function resolveTrialDays(): int
    {
        // Les colonnes sont NULLables en base (tenants legacy, cf. #1952) —
        // on passe par getAttribute() pour que PHPStan traite la valeur comme
        // mixed et non comme Carbon non-nullable (docblock du modèle).
        $startRaw = $this->company->getAttribute('subscription_start');
        $endRaw = $this->company->getAttribute('subscription_end');

        $start = $startRaw !== null && $startRaw !== ''
            ? Carbon::parse($startRaw)->startOfDay()
            : null;
        $end = $endRaw !== null && $endRaw !== ''
            ? Carbon::parse($endRaw)->startOfDay()
            : null;

        if ($start !== null && $end !== null && $end->greaterThan($start)) {
            return max(1, (int) $start->diffInDays($end));
        }

        if ($this->company->plan_id) {
            $planDays = DB::table('plans')->where('id', $this->company->plan_id)->value('trial_days');
            if (is_numeric($planDays) && (int) $planDays > 0) {
                return (int) $planDays;
            }
        }

        return 14;
    }

    private function resolveSubject(string $locale): string
    {
        return match ($locale) {
            'en' => 'Your Leopardo RH workspace is ready!',
            'ar' => 'مساحة عملك في Leopardo RH جاهزة!',
            'tr' => 'Leopardo RH çalışma alanınız hazır!',
            default => 'Votre espace Leopardo RH est prêt !',
        };
    }
}

