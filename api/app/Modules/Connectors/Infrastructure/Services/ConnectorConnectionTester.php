<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * #9838 (BC-14 INTEGRATION) — « Tester la connexion », honnêtement.
 *
 * Règle de base : **on ne prétend jamais qu'une connexion fonctionne quand on
 * ne l'a pas vérifiée.** Un bouton « Tester » qui répond toujours « OK » est
 * pire qu'aucun bouton : il fait croire qu'un virement arrivera sur un IBAN
 * mal saisi.
 *
 * Trois familles de contrôle, choisies par la nature réelle du connecteur :
 *
 *  1. **Comptes bancaires** (`category = bank`) — il n'existe pas d'API
 *     publique pour « pinguer » un IBAN. On exécute donc les contrôles qui ont
 *     une valeur réelle : clé de contrôle IBAN (mod-97, ISO 13616), format
 *     BIC (ISO 9362), présence du titulaire. Verdict `validated` ou `invalid`,
 *     avec le détail de chaque contrôle. Ce n'est PAS un ping réseau et le
 *     message le dit.
 *
 *  2. **Connecteurs à URL** (site, e-commerce, webhook) — un `GET` borné
 *     (timeout 5 s, redirections suivies) sur l'URL déclarée. Le statut HTTP
 *     réel décide : 2xx/3xx ⇒ `validated`, 4xx/5xx ⇒ `invalid`, erreur réseau
 *     ⇒ `unreachable`. Aucune exception ne remonte : un test qui plante est un
 *     test qui n'informe pas.
 *
 *  3. **Tout le reste** (réseaux sociaux, marketplaces, messageries) — la
 *     vérification exigerait un appel authentifié à une API tierce que nous
 *     n'exécutons pas ici. On répond `unsupported` avec une explication
 *     explicite, plutôt que d'inventer un succès.
 */
class ConnectorConnectionTester
{
    /** Délai maximal d'un contrôle réseau, en secondes. */
    private const HTTP_TIMEOUT = 5;

    /**
     * Exécute le contrôle adapté au connecteur.
     *
     * @param  array<string, mixed>  $config   configuration non secrète
     * @param  array<string, string>  $secrets  secrets déchiffrés (jamais loggés)
     * @return array{status: string, checks: list<array{key: string, ok: bool, detail: string}>, message: string}
     */
    public function test(ConnectorCatalogEntry $entry, array $config, array $secrets): array
    {
        if ($entry->category === 'bank') {
            return $this->testBankAccount($config, $secrets);
        }

        $url = $this->firstUrl($config);

        if ($url !== null) {
            return $this->testUrl($url);
        }

        return [
            'status' => 'unsupported',
            'checks' => [],
            'message' => 'Ce connecteur ne peut pas être vérifié automatiquement : '
                .'la vérification exigerait un appel authentifié à l\'API du fournisseur, '
                .'que la plateforme n\'exécute pas. La configuration est enregistrée telle quelle.',
        ];
    }

    /**
     * Contrôles réels d'un compte bancaire : IBAN (mod-97), BIC, titulaire.
     *
     * L'IBAN est un champ SECRET (chiffré au repos) : il arrive donc par
     * `$secrets`, pas par `$config`. On lit les deux pour rester tolérant à un
     * catalogue qui déclarerait l'IBAN en clair.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $secrets
     * @return array{status: string, checks: list<array{key: string, ok: bool, detail: string}>, message: string}
     */
    private function testBankAccount(array $config, array $secrets = []): array
    {
        $checks = [];

        $rawIban = (string) ($secrets['iban'] ?? $config['iban'] ?? '');
        $iban = strtoupper(preg_replace('/\s+/', '', $rawIban) ?? '');
        $ibanOk = $iban !== '' && $this->isValidIban($iban);
        $checks[] = [
            'key' => 'iban',
            'ok' => $ibanOk,
            'detail' => $iban === ''
                ? 'Aucun IBAN saisi.'
                : ($ibanOk
                    ? 'IBAN valide (clé de contrôle mod-97 vérifiée).'
                    : 'IBAN invalide : la clé de contrôle mod-97 ne correspond pas.'),
        ];

        $bic = strtoupper(trim((string) ($config['bic'] ?? '')));
        if ($bic !== '') {
            $bicOk = preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) === 1;
            $checks[] = [
                'key' => 'bic',
                'ok' => $bicOk,
                'detail' => $bicOk ? 'BIC au format ISO 9362.' : 'BIC invalide (format attendu : 8 ou 11 caractères).',
            ];
        }

        $holder = trim((string) ($config['account_holder'] ?? ''));
        if ($holder !== '') {
            $checks[] = [
                'key' => 'account_holder',
                'ok' => true,
                'detail' => 'Titulaire renseigné.',
            ];
        }

        $allOk = $checks !== [] && array_reduce(
            $checks,
            static fn (bool $carry, array $check): bool => $carry && $check['ok'],
            true,
        );

        return [
            'status' => $allOk ? 'validated' : 'invalid',
            'checks' => $checks,
            'message' => $allOk
                ? 'Coordonnées bancaires valides. Ce contrôle vérifie le FORMAT et la clé de contrôle : '
                    .'il ne confirme pas que le compte est ouvert (aucune API bancaire publique ne le permet).'
                : 'Coordonnées bancaires invalides : corrigez les champs en erreur avant d\'activer la connexion.',
        ];
    }

    /**
     * Contrôle réseau borné sur une URL déclarée.
     *
     * @return array{status: string, checks: list<array{key: string, ok: bool, detail: string}>, message: string}
     */
    private function testUrl(string $url): array
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->withHeaders(['User-Agent' => 'Leopardo-ConnectorCheck/1.0'])
                ->get($url);

            $status = $response->status();
            $ok = $status >= 200 && $status < 400;

            return [
                'status' => $ok ? 'validated' : 'invalid',
                'checks' => [[
                    'key' => 'http',
                    'ok' => $ok,
                    'detail' => sprintf('Réponse HTTP %d de %s.', $status, $url),
                ]],
                'message' => $ok
                    ? 'Le service a répondu. Ce contrôle vérifie l\'accessibilité de l\'URL, pas les droits du jeton.'
                    : sprintf('Le service a répondu %d : l\'URL est joignable mais refuse la requête.', $status),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'unreachable',
                'checks' => [[
                    'key' => 'http',
                    'ok' => false,
                    'detail' => sprintf('Aucune réponse de %s (%s).', $url, $exception->getMessage()),
                ]],
                'message' => 'Le service est injoignable depuis la plateforme : vérifiez l\'URL et la connectivité.',
            ];
        }
    }

    /**
     * Première URL exploitable de la configuration.
     *
     * @param  array<string, mixed>  $config
     */
    private function firstUrl(array $config): ?string
    {
        foreach (['site_url', 'url', 'shop_url', 'webhook_url', 'endpoint'] as $key) {
            $value = $config[$key] ?? null;

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Validation IBAN par la clé de contrôle mod-97 (ISO 13616).
     *
     * Algorithme : déplacer les 4 premiers caractères à la fin, remplacer
     * chaque lettre par sa valeur (A=10 … Z=35), puis vérifier que le nombre
     * obtenu modulo 97 vaut 1. On procède par tranches pour ne pas dépendre de
     * la précision des entiers flottants sur une chaîne longue.
     */
    public function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        $length = strlen($rearranged);
        for ($i = 0; $i < $length; $i++) {
            $char = $rearranged[$i];
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Modulo 97 par tranches de 9 chiffres : le reste est toujours < 97,
        // donc `reste * 10^9 + tranche` tient dans un entier 64 bits. On
        // multiplie le reste par 10^(longueur de la tranche) — et non par une
        // puissance fixe — sinon la dernière tranche, plus courte, serait
        // décalée et le calcul faux.
        $remainder = 0;
        $chunkLength = 9;
        $numericLength = strlen($numeric);

        for ($offset = 0; $offset < $numericLength; $offset += $chunkLength) {
            $chunk = substr($numeric, $offset, $chunkLength);
            $remainder = ($remainder * (10 ** strlen($chunk)) + (int) $chunk) % 97;
        }

        return $remainder === 1;
    }
}
