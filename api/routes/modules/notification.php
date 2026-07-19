<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification Module Routes
|--------------------------------------------------------------------------
|
| Historical note (2026-07-19 audit): this file used to declare its own
| Route::prefix('v1/notifications') group. Since this file is require()'d
| from routes/api.php INSIDE an outer Route::prefix('v1') group, that
| produced unreachable dead routes under /api/v1/v1/notifications/*
| (double v1 prefix) — never matched by any real client, and duplicating
| functionality already exposed correctly under /api/v1/notifications/*
| via routes/modules/rh.php (index, read-all, {notification}/read, destroy,
| stream, sse-token) and routes/modules/dashboard.php (index, unread,
| {id}/read, mark-all-read).
|
| The dead group has been removed. This file is now a no-op kept only so
| the require() in routes/api.php does not need to change; the
| NotificationController import above was removed accordingly. If this
| module needs its own dedicated routes again, add them here using the
| relative paths only (no leading 'v1/'), since the outer group already
| supplies that prefix.
*/
