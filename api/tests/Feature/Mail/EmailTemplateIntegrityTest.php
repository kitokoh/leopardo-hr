<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * #7346 — garde-fous de la charte e-mail.
 *
 * Contexte : la CI ne compile AUCUNE vue Blade (`view:cache` n'est pas exécuté),
 * donc une faute de syntaxe dans un template n'apparaîtrait qu'en production, au
 * moment d'envoyer l'e-mail — c'est-à-dire au pire endroit possible. Ce test
 * comble ce trou : il compile tous les templates d'e-mail et vérifie que le PHP
 * produit est syntaxiquement valide, sans rien exécuter.
 *
 * Il verrouille aussi les trois exigences de fond de #7346 :
 *   1. le RTL et le pré-en-tête sont portés par le layout unique ;
 *   2. l'adresse de contact n'est JAMAIS l'adresse d'envoi (`noreply@`) ;
 *   3. aucun terme interne ne peut revenir dans un e-mail client.
 */
class EmailTemplateIntegrityTest extends TestCase
{
    /**
     * Tous les templates d'e-mail du dépôt (layout, partiels, templates).
     *
     * @return array<int, string>
     */
    private function mailTemplates(): array
    {
        $paths = [];

        foreach (['emails', 'mail'] as $directory) {
            $root = resource_path('views/'.$directory);

            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $paths[] = $file->getPathname();
                }
            }
        }

        sort($paths);

        return $paths;
    }

    public function test_tous_les_templates_e_mail_compilent_en_php_valide(): void
    {
        $templates = $this->mailTemplates();

        $this->assertNotEmpty($templates, 'Aucun template d’e-mail trouvé — chemins erronés ?');

        foreach ($templates as $template) {
            $compiled = Blade::compileString(File::get($template));

            try {
                // TOKEN_PARSE demande au tokenizer de PARSER le code : une erreur
                // de syntaxe lève une ParseError que l'on peut rapporter.
                // Le resultat est conserve : `token_get_all()` est une fonction
                // pure, PHPStan strict refuse un appel sans effet.
                $tokens = token_get_all($compiled, TOKEN_PARSE);
                $this->assertNotEmpty($tokens);
            } catch (\ParseError $e) {
                $this->fail(sprintf(
                    'Template e-mail invalide : %s — %s (ligne %d du PHP compilé)',
                    str_replace(base_path().'/', '', $template),
                    $e->getMessage(),
                    $e->getLine()
                ));
            }
        }
    }

    public function test_le_layout_est_rtl_et_porte_un_pre_en_tete(): void
    {
        $ar = view('emails.layouts.base', ['locale' => 'ar', 'subject' => 'Sujet AR'])->render();
        $fr = view('emails.layouts.base', ['locale' => 'fr', 'subject' => 'Sujet FR'])->render();

        $this->assertStringContainsString('dir="rtl"', $ar, 'L’arabe doit être rendu en RTL par le layout.');
        $this->assertStringContainsString('dir="ltr"', $fr, 'Les autres locales doivent être rendues en LTR.');

        // Pré-en-tête masqué : sans lui, la boîte de réception affiche le début
        // du corps HTML, non maîtrisé.
        $this->assertStringContainsString('mso-hide:all', $ar);
        $this->assertStringContainsString('Sujet AR', $ar);

        // Le pied de page est unique et localisé dans les 4 langues.
        $this->assertStringContainsString(__('emails.layout_footer_context', ['brand' => config('mail.brand.name')], 'fr'), $fr);
        $this->assertStringContainsString(__('emails.layout_rights_reserved', [], 'fr'), $fr);
    }

    public function test_l_adresse_de_contact_n_est_jamais_l_adresse_d_envoi(): void
    {
        $support = (string) config('mail.brand.support_address');
        $from = (string) config('mail.from.address');

        $this->assertNotSame('', $support);
        $this->assertNotSame(
            $from,
            $support,
            'Le pied de page ne doit pas inviter à répondre à l’adresse d’envoi (noreply@) : elle n’est pas relevée.'
        );

        $html = view('emails.layouts.base', ['locale' => 'fr', 'subject' => 'Sujet'])->render();
        $this->assertStringContainsString($support, $html);
    }

    public function test_la_marque_ne_peut_pas_retomber_sur_le_nom_du_framework(): void
    {
        // `config('app.name')` vaut « Laravel » par défaut : ce nom s'est déjà
        // retrouvé dans des e-mails clients. La marque e-mail ne doit jamais en
        // dépendre seule.
        $this->assertNotSame('Laravel', config('mail.brand.name'));
        $this->assertNotSame('', (string) config('mail.brand.name'));

        $html = view('emails.layouts.base', ['locale' => 'fr', 'subject' => 'Sujet'])->render();
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_le_bouton_du_layout_est_stylise_en_ligne(): void
    {
        // Gmail supprime fréquemment les blocs `<style>` : un bouton stylisé par
        // classe perdait sa couleur. Le bouton doit donc porter ses styles en
        // ligne ET un attribut `bgcolor` (Outlook/Word).
        $html = view('emails.partials.button', ['url' => 'https://example.test/x', 'label' => 'Ouvrir'])->render();

        $this->assertStringContainsString('bgcolor=', $html);
        $this->assertStringContainsString('background-color:', $html);
        $this->assertStringNotContainsString('class=', $html, 'Un bouton stylisé par classe casserait dans Gmail.');
    }

    public function test_aucun_terme_interne_dans_les_templates_e_mail(): void
    {
        // Termes qui ne doivent JAMAIS apparaître dans un e-mail reçu par un
        // client : jargon de module interne, URL de back-office, adresse locale,
        // nom du framework ou de la pile technique.
        $interdits = [
            'Node Edge',
            'node Edge',
            'licence Edge',
            '/admin/edge-nodes',
            'localhost',
            'Laravel',
            'Sanctum',
            'artisan',
        ];

        $fautes = [];

        foreach ($this->mailTemplates() as $template) {
            $relative = str_replace(base_path().'/', '', $template);
            $source = File::get($template);

            // Les commentaires Blade et PHP sont retirés : ils documentent
            // justement ce qu'il ne faut pas faire (ex. « avant, ce template
            // pointait vers localhost ») et ne sont jamais envoyés.
            $code = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;
            $code = preg_replace('#^\s*(//|/\*|\*).*$#m', '', $code) ?? $code;

            foreach ($interdits as $terme) {
                if (stripos($code, $terme) !== false) {
                    $fautes[] = $relative.' → « '.$terme.' »';
                }
            }
        }

        $this->assertSame([], $fautes, "Termes internes détectés dans des e-mails clients :\n".implode("\n", $fautes));
    }
}
