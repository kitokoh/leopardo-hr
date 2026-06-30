<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Notifications\EdgeNodeSilentNotification;
use App\Modules\EdgeSync\Notifications\EdgeLicenseExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;

/**
 * Priority 3.4 — Monitor Edge nodes for silence / license expiry.
 * Scheduled: every 30 minutes in Kernel.
 */
class MonitorEdgeNodesCommand extends Command
{
    protected $signature   = 'edge:monitor';
    protected $description = 'Monitor Edge nodes — alert on silence or expiring licenses';

    public function handle(): void
    {
        $this->info('[EdgeMonitor] Starting...');

        $silentThreshold  = now()->subMinutes(30);
        $renewalThreshold = now()->addDays(7);

        // ── Silent nodes (not seen in 30+ minutes) ────────────────
        $silentNodes = EdgeNode::where('status', 'active')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $silentThreshold)
            ->with('company')
            ->get();

        foreach ($silentNodes as $node) {
            $this->warn(sprintf(
                '[EdgeMonitor] SILENT: %s (%s) — last seen %s',
                $node->name,
                $node->company->name ?? 'unknown',
                $node->last_seen_at->diffForHumans()
            ));

            if ($node->company?->email) {
                Notification::route('mail', $node->company->email)
                    ->notify(new EdgeNodeSilentNotification($node));
            }
        }

        // ── Expiring licenses ─────────────────────────────────────
        $expiringLicenses = EdgeLicense::where('validation_status', 'valid')
            ->where('expires_at', '<=', $renewalThreshold)
            ->where('expires_at', '>', now())
            ->with('edgeNode.company')
            ->get();

        foreach ($expiringLicenses as $license) {
            $this->warn(sprintf(
                '[EdgeMonitor] LICENSE EXPIRING: %s — expires %s',
                $license->edgeNode->name ?? 'unknown',
                $license->expires_at->diffForHumans()
            ));

            // Mark as pending renewal
            $license->update(['validation_status' => 'pending_renewal']);

            $company = $license->edgeNode?->company;
            if ($company?->email) {
                Notification::route('mail', $company->email)
                    ->notify(new EdgeLicenseExpiringNotification($license));
            }
        }

        // ── Expired licenses ──────────────────────────────────────
        $expiredLicenses = EdgeLicense::where('validation_status', 'valid')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredLicenses as $license) {
            $license->update(['validation_status' => 'expired']);
            $this->error(sprintf(
                '[EdgeMonitor] LICENSE EXPIRED: edge_node_id=%s',
                $license->edge_node_id
            ));
        }

        $this->info(sprintf(
            '[EdgeMonitor] Done — silent:%d expiring:%d expired:%d',
            $silentNodes->count(),
            $expiringLicenses->count(),
            $expiredLicenses->count()
        ));
    }
}
