<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\EdgeNodeSilentAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Backward-compatible command for the legacy Edge node table.
 *
 * The canonical scheduled monitor is `edge:monitor`. This command remains
 * available for old operational scripts and fixtures while the legacy
 * `node_id` schema is being retired; it is deliberately not scheduled.
 */
class DetectSilentEdgeNodesCommand extends Command
{
    protected $signature = 'edge:detect-silent-nodes
        {--threshold=30 : Silence threshold in minutes}
        {--dry-run : Detect nodes without changing state or sending alerts}';

    protected $description = 'Detect silent legacy Edge nodes (compatibility command)';

    public function handle(): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $dryRun = (bool) $this->option('dry-run');

        if (! DB::getSchemaBuilder()->hasTable('edge_nodes')
            || ! DB::getSchemaBuilder()->hasColumn('edge_nodes', 'node_id')) {
            $this->warn('Legacy edge_nodes schema is not installed; use edge:monitor for the canonical schema.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subMinutes($threshold);
        $nodes = DB::table('edge_nodes as nodes')
            ->leftJoin('companies', 'companies.id', '=', 'nodes.company_id')
            ->where('nodes.status', '!=', 'revoked')
            ->where('nodes.alert_muted', false)
            ->where(function ($query) use ($cutoff): void {
                $query->where('nodes.last_seen_at', '<', $cutoff)
                    ->orWhereNull('nodes.last_seen_at');
            })
            ->select([
                'nodes.node_id',
                'nodes.name as node_name',
                'nodes.last_seen_at',
                'companies.name as company_name',
                'companies.email as company_email',
            ])
            ->get();

        foreach ($nodes as $node) {
            $lastSeen = $node->last_seen_at !== null
                ? Carbon::parse($node->last_seen_at)
                : null;

            $this->warn(sprintf(
                'Silent Edge node: %s (%s), last seen %s',
                $node->node_name,
                $node->node_id,
                $lastSeen?->diffForHumans() ?? 'never'
            ));

            if (! $dryRun && $node->company_email !== null) {
                Notification::route('mail', $node->company_email)->notify(new EdgeNodeSilentAlert(
                    (string) $node->node_name,
                    (string) $node->node_id,
                    (string) ($node->company_name ?? 'unknown'),
                    $lastSeen,
                    $threshold,
                ));
            }
        }

        $this->info(sprintf(
            'Silent Edge nodes detected: %d%s',
            $nodes->count(),
            $dryRun ? ' (dry run)' : ''
        ));

        return self::SUCCESS;
    }
}
