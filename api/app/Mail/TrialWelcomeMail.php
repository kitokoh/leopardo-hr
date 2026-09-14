<?php

namespace App\Mail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
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

        // `Mailable::send()` évalue `withLocale($this->locale)` AVANT `build()` :
        // la locale doit être posée ici pour que la VUE soit rendue dans la
        // bonne langue. (Le `App::setLocale()` qui vivait dans `build()` n'était
        // en outre jamais restauré : la locale fuyait sur le reste de la
        // requête, donc sur les messages d'erreur suivants.)
        $this->locale($this->company->language ?? 'fr');
    }

    public function build(): self
    {
        $locale = $this->company->language ?? 'fr';

        return $this
            ->subject($this->resolveSubject($locale))
            ->view('emails.trial-welcome', [
                'company' => $this->company,
                'manager' => $this->manager,
                'tempPassword' => $this->tempPassword,
                'locale' => $locale,
                'trialDays' => $this->trialDays,
                // L'email contient les identifiants temporaires : le CTA doit
                // pointer sur l'UI produit (page de connexion), jamais sur
                // l'API — même convention que TrialDripMail.
                'appUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/'),
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
