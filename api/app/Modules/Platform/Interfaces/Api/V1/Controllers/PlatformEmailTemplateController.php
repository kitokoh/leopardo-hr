<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Mail\EmailTemplateRegistry;
use App\Core\Mail\EmailTemplateRepository;
use App\Core\Mail\EmailTemplateResolver;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * #7347 — édition des e-mails depuis la plateforme admin (rubrique Paramètres).
 *
 * Le contenu est stocké par (template, locale) dans `public.email_templates`.
 * Aucun code n'est stocké ni exécuté : les champs sont du TEXTE, échappé au
 * rendu, et seules les variables déclarées par le registre sont substituées.
 *
 * Contrat SPA admin :
 *   GET    /api/v1/admin/email-templates           → liste + valeurs effectives
 *   PUT    /api/v1/admin/email-templates           → surcharge (partielle)
 *   DELETE /api/v1/admin/email-templates           → retour au défaut
 *   POST   /api/v1/admin/email-templates/preview   → aperçu rendu dans le layout
 */
class PlatformEmailTemplateController extends Controller
{
    /** @var array<int, string> */
    private const LOCALES = ['fr', 'en', 'ar', 'tr'];

    public function __construct(
        private readonly EmailTemplateResolver $resolver,
        private readonly EmailTemplateRepository $repository,
    ) {}

    public function index(): JsonResponse
    {
        $templates = [];

        foreach (EmailTemplateRegistry::all() as $key => $definition) {
            $locales = [];

            foreach (self::LOCALES as $locale) {
                $locales[$locale] = $this->resolver->resolve($key, $locale)->toArray();
            }

            $templates[] = [
                'key' => $key,
                'variables' => $definition['variables'],
                'locales' => $locales,
            ];
        }

        return response()->json([
            'data' => $templates,
            'meta' => [
                'locales' => self::LOCALES,
                'fields' => EmailTemplateRegistry::FIELDS,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $key = (string) $validated['template_key'];
        $locale = (string) $validated['locale'];

        $this->repository->upsert(
            $key,
            $locale,
            Arr::only($validated, EmailTemplateRegistry::FIELDS),
            $this->updatedBy($request),
        );

        return response()->json(['data' => $this->resolver->resolve($key, $locale)->toArray()]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_key' => $this->templateKeyRule(),
            'locale' => $this->localeRule(),
        ]);

        $key = (string) $validated['template_key'];
        $locale = (string) $validated['locale'];

        $this->repository->reset($key, $locale);

        return response()->json(['data' => $this->resolver->resolve($key, $locale)->toArray()]);
    }

    /**
     * Aperçu : rend la surcharge dans le VRAI layout, avec les valeurs fournies
     * par l'écran d'administration (aucun exemple n'est codé ici).
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => $this->localeRule(),
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'cta_label' => ['nullable', 'string', 'max:160'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:255'],
        ]);

        $locale = (string) $validated['locale'];
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $body = (string) ($validated['body'] ?? '');
            $body = strtr($body, $this->cleanVariables($validated['variables'] ?? []));

            $html = view('emails.preview', [
                'locale' => $locale,
                'heading' => (string) ($validated['heading'] ?? ''),
                'bodyHtml' => nl2br(e($body)),
                'ctaLabel' => isset($validated['cta_label']) ? trim((string) $validated['cta_label']) : null,
            ])->render();
        } finally {
            app()->setLocale($previous);
        }

        return response()->json(['data' => ['html' => $html]]);
    }

    /**
     * Auteur de la modification, pour la traçabilité (`updated_by`).
     * Extrait explicitement : `user()` renvoie l'interface `Authenticatable`,
     * qui n'expose pas `email` (PHPStan 8).
     */
    private function updatedBy(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof SuperAdmin ? (string) $user->email : null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'template_key' => $this->templateKeyRule(),
            'locale' => $this->localeRule(),
            'subject' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'cta_label' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function templateKeyRule(): array
    {
        return ['required', 'string', 'in:'.implode(',', EmailTemplateRegistry::keys())];
    }

    /**
     * @return array<int, string>
     */
    private function localeRule(): array
    {
        return ['required', 'string', 'in:'.implode(',', self::LOCALES)];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    private function cleanVariables(array $variables): array
    {
        $kept = [];

        foreach ($variables as $name => $value) {
            if (is_scalar($value) && str_starts_with((string) $name, ':') && (string) $value !== '') {
                $kept[(string) $name] = (string) $value;
            }
        }

        return $kept;
    }
}
