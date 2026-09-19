<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * #7347 — registre des e-mails dont le CONTENU est éditable depuis l'admin.
 *
 * Principe : le registre ne contient **aucun texte**. Chaque champ pointe vers
 * une ou plusieurs clés du catalogue `api/lang/<locale>/emails.php`, qui restent la
 * source des valeurs PAR DÉFAUT. Une surcharge en base (`email_templates`) prend
 * le dessus pour une locale donnée ; un template jamais modifié se comporte donc
 * exactement comme avant, à la virgule près.
 *
 * Les `variables` sont la liste FERMÉE des jetons substituables. Rien d'autre
 * n'est remplacé : une valeur saisie dans l'admin est du texte, jamais du code.
 */
final class EmailTemplateRegistry
{
    /** Champs éditables, dans l'ordre d'affichage de l'admin. */
    public const FIELDS = ['subject', 'heading', 'body', 'cta_label'];

    /**
     * @var array<string, array{
     *     subject: string,
     *     heading: string,
     *     body: array<int, string>,
     *     cta_label: ?string,
     *     variables: array<int, string>
     * }>
     */
    private const TEMPLATES = [
        'password_reset' => [
            'subject' => 'emails.email_password_reset_subject',
            'heading' => 'emails.email_password_reset_subject',
            'body' => ['emails.email_password_reset_body'],
            'cta_label' => 'emails.email_password_reset_button',
            'variables' => [':name', ':brand'],
        ],
        'trial_verification' => [
            'subject' => 'emails.trial_verification_subject',
            'heading' => 'emails.trial_verification_subject',
            'body' => ['emails.trial_verification_intro'],
            'cta_label' => null,
            'variables' => [':name', ':brand'],
        ],
        'login_code' => [
            'subject' => 'emails.login_code_subject',
            'heading' => 'emails.login_code_subject',
            'body' => ['emails.login_code_intro'],
            'cta_label' => null,
            'variables' => [':brand'],
        ],
        'trial_welcome' => [
            'subject' => 'emails.email_trial_welcome_subject',
            'heading' => 'emails.email_trial_welcome_subject',
            'body' => ['emails.email_trial_welcome_intro'],
            'cta_label' => 'emails.email_trial_welcome_button',
            'variables' => [':company', ':name', ':brand', ':days'],
        ],
        'user_invitation' => [
            'subject' => 'emails.user_invitation_subject',
            'heading' => 'emails.user_invitation_title',
            'body' => ['emails.user_invitation_intro'],
            'cta_label' => 'emails.user_invitation_activate_line',
            'variables' => [':company', ':role', ':email', ':name', ':brand'],
        ],
        'role_assignment' => [
            'subject' => 'emails.role_assignment_subject',
            'heading' => 'emails.role_assignment_heading',
            'body' => ['emails.role_assignment_body'],
            'cta_label' => null,
            'variables' => [':role', ':company', ':assignedBy', ':name', ':brand'],
        ],
        'onboarding_reminder' => [
            'subject' => 'emails.onboarding_reminder_subject',
            'heading' => 'emails.onboarding_reminder_heading',
            'body' => ['emails.onboarding_reminder_intro'],
            'cta_label' => 'emails.onboarding_reminder_cta',
            'variables' => [':name', ':company', ':brand'],
        ],
        // #7760 — notifications e-mail des tickets support client ↔ plateforme.
        // Le corps ne recopie jamais le message du ticket : la conversation se
        // lit dans l'espace client / la console plateforme.
        'support_ticket_opened' => [
            'subject' => 'emails.support_ticket_opened_subject',
            'heading' => 'emails.support_ticket_opened_title',
            'body' => ['emails.support_ticket_opened_intro'],
            'cta_label' => null,
            'variables' => [':ticket', ':company', ':subject', ':category', ':priority', ':brand'],
        ],
        'support_ticket_platform_reply' => [
            'subject' => 'emails.support_ticket_platform_reply_subject',
            'heading' => 'emails.support_ticket_platform_reply_title',
            'body' => ['emails.support_ticket_platform_reply_intro'],
            'cta_label' => null,
            'variables' => [':ticket', ':subject', ':name', ':brand'],
        ],
        'support_ticket_tenant_reply' => [
            'subject' => 'emails.support_ticket_tenant_reply_subject',
            'heading' => 'emails.support_ticket_tenant_reply_title',
            'body' => ['emails.support_ticket_tenant_reply_intro'],
            'cta_label' => null,
            'variables' => [':ticket', ':company', ':subject', ':brand'],
        ],
        'invoice_issued' => [
            'subject' => 'emails.invoice_issued_subject',
            'heading' => 'emails.invoice_issued_heading',
            'body' => ['emails.invoice_issued_intro'],
            'cta_label' => null,
            'variables' => [':company', ':number', ':total', ':currency', ':due_date', ':name', ':brand'],
        ],
        'invoice_payment_receipt' => [
            'subject' => 'emails.invoice_payment_receipt_subject',
            'heading' => 'emails.invoice_payment_receipt_heading',
            'body' => ['emails.invoice_payment_receipt_intro'],
            'cta_label' => null,
            'variables' => [':company', ':number', ':total', ':currency', ':paid_at', ':name', ':brand'],
        ],
        // BC-29 Communication R4 (#7689) — relance automatique envoyee via
        // le Gmail de l'utilisateur (spec §3.4 : « gabarits via
        // EmailTemplateRegistry », surcharge par locale dans l'admin).
        'communication_follow_up' => [
            'subject' => 'emails.communication_follow_up_subject',
            'heading' => 'emails.communication_follow_up_subject',
            'body' => ['emails.communication_follow_up_body'],
            'cta_label' => null,
            'variables' => [':subject', ':name', ':brand'],
        ],
    ];

    /**
     * @return array<string, array{subject: string, heading: string, body: array<int, string>, cta_label: ?string, variables: array<int, string>}>
     */
    public static function all(): array
    {
        return self::TEMPLATES;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::TEMPLATES);
    }

    /**
     * @return array{subject: string, heading: string, body: array<int, string>, cta_label: ?string, variables: array<int, string>}|null
     */
    public static function definition(string $key): ?array
    {
        return self::TEMPLATES[$key] ?? null;
    }
}
