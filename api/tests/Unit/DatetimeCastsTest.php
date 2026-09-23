<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Attendance\Domain\Models\ZktecoSyncLog;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use Tests\TestCase;

/**
 * Issue #7984 (audit 2026-09-20, point 4) — casts datetime manquants :
 * `ZktecoSyncLog.started_at/completed_at` et
 * `TravelComment.moderated_at/reported_at` étaient exposés en string brute.
 */
final class DatetimeCastsTest extends TestCase
{
    public function test_zkteco_sync_log_datetime_casts(): void
    {
        $casts = (new ZktecoSyncLog())->getCasts();

        $this->assertSame('datetime', $casts['started_at'] ?? null);
        $this->assertSame('datetime', $casts['completed_at'] ?? null);
    }

    public function test_travel_comment_datetime_casts(): void
    {
        $casts = (new TravelComment())->getCasts();

        $this->assertSame('datetime', $casts['moderated_at'] ?? null);
        $this->assertSame('datetime', $casts['reported_at'] ?? null);
    }
}
