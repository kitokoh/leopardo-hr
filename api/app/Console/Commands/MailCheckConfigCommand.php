<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Mail\MailTransportDiagnostics;
use App\Mail\TrialVerificationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Plan 60 jours — issue #5162 : diagnostic de la chaîne d'envoi d'email.
 *
 * Le trial self-service (OTP) échouait en prod avec 503 TRIAL_OTP_SEND_FAILED
 * sans visibilité sur la cause (hypothèse : config Mailgun absente/invalide
 * sur Render, même famille que #5139/#5141). Cette commande expose :
 *
 *   - le transport résolu (MAIL_MAILER) et les variables requises par ce
 *     transport (présence uniquement — jamais les secrets) ;
 *   - l'adresse d'expéditeur configurée (MAIL_FROM_ADDRESS) ;
 *   - un envoi de test réel optionnel (--to=...) pour vérifier le egress.
 *
 * Usage :
 *   php artisan mail:check-config                 # diagnostic seul
 *   php artisan mail:check-config --to=a@b.dz     # + envoi de test réel
 *   php artisan mail:check-config --json          # sortie JSON (CI/scripts)
 */
class MailCheckConfigCommand extends Command
{
    protected $signature = 'mail:check-config {--to= : Adresse pour un envoi de test réel} {--json : Sortie JSON structurée}';

    protected $description = 'Diagnostic de la config email : transport résolu, variables requises, envoi de test optionnel';

    public function handle(): int
    {
        $mailer = (string) Config::get('mail.default', 'log');
        /** @var array<string, mixed> $mailerConfig */
        $mailerConfig = (array) Config::get("mail.mailers.{$mailer}", []);
        $transport = (string) ($mailerConfig['transport'] ?? $mailer);

        $issues = [];

        // 1. Variables requises par le transport RÉELLEMENT résolu (présence
        // seule, jamais les secrets).
        //
        // #9317 — la liste était locale à cette commande et ne couvrait que
        // `mailgun`/`smtp`/`ses` : le transport de production (`resend`) n'y
        // figurait pas, donc `mail:check-config` sortait en succès sur une
        // configuration qui ne pouvait pas envoyer. La résolution vit
        // désormais dans `MailTransportDiagnostics`, partagée avec la sonde
        // `/health` et le garde-fou de livraison.
        foreach ((new MailTransportDiagnostics)->missingRequiredEnv() as $issue) {
            $issues[] = $issue;
        }

        // 2. Expéditeur obligatoire pour tout transport.
        $fromAddress = (string) Config::get('mail.from.address', '');
        if ($fromAddress === '') {
            $issues[] = 'MAIL_FROM_ADDRESS absent/vide — aucun expéditeur pour les emails sortants.';
        }

        $report = [
            'mailer' => $mailer,
            'transport' => $transport,
            'from_address' => $fromAddress,
            'from_name' => (string) Config::get('mail.from.name', ''),
            'issues' => $issues,
        ];

        // 3. Envoi de test réel (optionnel) — vérifie l'egress de bout en bout.
        $to = $this->option('to');
        if (is_string($to) && $to !== '') {
            try {
                // #7854 : TrialVerificationMail est ShouldQueue — sendNow()
                // force l'envoi SYNCHRONE requis par ce diagnostic d'egress.
                Mail::to($to)->sendNow(new TrialVerificationMail('Diagnostic', '000000', 'fr'));
                $report['test_send'] = ['status' => 'ok', 'to' => $to];
            } catch (Throwable $e) {
                $report['test_send'] = ['status' => 'failed', 'to' => $to, 'error' => $e->getMessage()];
                $issues[] = "Envoi de test vers {$to} échoué : {$e->getMessage()}";
            }
        }

        $exitCode = $issues === [] ? self::SUCCESS : self::FAILURE;

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Mailer résolu : {$report['mailer']} (transport : {$report['transport']})");
            $this->line("Expéditeur : {$report['from_address']} <{$report['from_name']}>");
            if ($issues === []) {
                $this->info('Aucun problème de configuration détecté.');
            } else {
                foreach ($issues as $issue) {
                    $this->error("• {$issue}");
                }
            }
            if (isset($report['test_send'])) {
                $this->line('Envoi de test : '.$report['test_send']['status']);
            }
        }

        return $exitCode;
    }
}
