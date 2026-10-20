<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

/**
 * #8673 (épic #8670) — lecture des registres de stacks de déploiement.
 *
 * Les registres (`deploy/dev-stacks.json`, `deploy/prod-stacks.json`) sont la
 * source de vérité HUMAINE des stacks : quels comptes, quels projets, quels
 * statuts. Ce service ne fait que les lire et les normaliser pour l'écran
 * superadmin — il n'écrit JAMAIS dans ces fichiers (une bascule met à jour
 * les variables du dépôt via `.github/workflows/stack-switch.yml` ; le
 * registre s'aligne ensuite en PR, la protection de branche l'interdit
 * autrement).
 *
 * Pourquoi lire un fichier du dépôt et non une table : l'infrastructure est
 * décrite en YAML/JSON versionnés à côté des blueprints Render ; dupliquer
 * cet état en base garantirait la divergence. Le registre est copié dans
 * l'image Docker du service (`deploy/` est dans le contexte de build).
 */
class StackRegistryService
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    /** Tier par défaut : la prod est le tier critique, c'est elle qu'on pilote d'abord. */
    public const DEFAULT_TIER = 'prod';

    public const TIERS = ['prod', 'dev'];

    /**
     * @return array{tier: string, active_stack: string, updated_at: string|null, stacks: list<array<string, mixed>>}
     */
    public function forTier(string $tier): array
    {
        $registry = $this->read($tier);
        $stacks = [];

        /** @var array<string, array<string, mixed>> $entries */
        $entries = is_array($registry['stacks'] ?? null) ? $registry['stacks'] : [];

        foreach ($entries as $id => $entry) {
            $stacks[] = [
                'id' => (string) $id,
                'status' => (string) ($entry['status'] ?? 'unknown'),
                'status_note' => $entry['status_note'] ?? null,
                'active' => ((string) $id) === (string) ($registry['active_stack'] ?? ''),
                'api' => [
                    'service' => $entry['api']['service_name'] ?? null,
                    'base_url' => $entry['api']['base_url'] ?? null,
                    'health_url' => $entry['api']['health_url'] ?? null,
                    'region' => $entry['api']['region'] ?? null,
                    'plan' => $entry['api']['plan'] ?? null,
                    // Une stack « basculable » est provisionnée (URL + santé
                    // connues) et déclarée prête : ni `planned` ni `suspended`.
                    'switchable' => $this->isSwitchable($entry),
                ],
                'web_url' => $entry['web']['url'] ?? null,
                'admin_url' => $entry['admin']['url'] ?? null,
                'db_host' => $entry['db']['host'] ?? null,
            ];
        }

        return [
            'tier' => $tier,
            'active_stack' => (string) ($registry['active_stack'] ?? ''),
            'updated_at' => isset($registry['updated_at']) ? (string) $registry['updated_at'] : null,
            'stacks' => $stacks,
        ];
    }

    /**
     * Les deux tiers, dans l'ordre d'affichage de l'écran.
     *
     * @return list<array{tier: string, active_stack: string, updated_at: string|null, stacks: list<array<string, mixed>>}>
     */
    public function all(): array
    {
        $out = [];
        foreach (self::TIERS as $tier) {
            $out[] = $this->forTier($tier);
        }

        return $out;
    }

    /**
     * Vérifie qu'une bascule est recevable AVANT d'appeler GitHub : le tier
     * existe, la stack existe, elle est basculable, et ce n'est pas déjà
     * l'active. Renvoie la raison du refus (chaîne vide = recevable).
     *
     * Les garde-fous de données de la prod (même base, jamais de reseed) sont
     * appliqués côté script (`scripts/stack-switch.sh --tier prod`) : on ne
     * les duplique pas ici, on ne fait que le pré-contrôle lisible par l'écran.
     *
     * @return array{ok: bool, reason: string, stack: array<string, mixed>|null, active: string}
     */
    public function validateSwitch(string $tier, string $stack): array
    {
        if (! in_array($tier, self::TIERS, true)) {
            return ['ok' => false, 'reason' => 'tier_inconnu', 'stack' => null, 'active' => ''];
        }

        $registry = $this->forTier($tier);
        $active = $registry['active_stack'];

        foreach ($registry['stacks'] as $candidate) {
            if ($candidate['id'] !== $stack) {
                continue;
            }

            if ($stack === $active) {
                return ['ok' => false, 'reason' => 'deja_active', 'stack' => $candidate, 'active' => $active];
            }

            if (! $candidate['api']['switchable']) {
                return ['ok' => false, 'reason' => 'non_basculable', 'stack' => $candidate, 'active' => $active];
            }

            return ['ok' => true, 'reason' => '', 'stack' => $candidate, 'active' => $active];
        }

        return ['ok' => false, 'reason' => 'stack_inconnue', 'stack' => null, 'active' => $active];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isSwitchable(array $entry): bool
    {
        $status = (string) ($entry['status'] ?? '');

        if (! in_array($status, ['ready', 'active'], true)) {
            return false;
        }

        $healthUrl = $entry['api']['health_url'] ?? null;

        return is_string($healthUrl) && $healthUrl !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $tier): array
    {
        if ($this->cache === null) {
            $this->cache = [];
        }

        if (isset($this->cache[$tier])) {
            return $this->cache[$tier];
        }

        $decoded = [];
        foreach ($this->candidatePaths($tier) as $path) {
            if (! is_file($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
                break;
            }
        }

        return $this->cache[$tier] = $decoded;
    }

    /**
     * Le backend tourne depuis `api/` (Docker) : le registre est deux niveaux
     * au-dessus. Les tests, eux, tournent depuis la racine du dépôt. On essaie
     * les deux plutôt que de dépendre du CWD.
     *
     * @return list<string>
     */
    private function candidatePaths(string $tier): array
    {
        $file = $tier.'-stacks.json';
        $base = base_path();

        return [
            $base.'/deploy/'.$file,
            $base.'/../deploy/'.$file,
            dirname($base).'/deploy/'.$file,
        ];
    }
}
