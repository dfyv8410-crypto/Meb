<?php
/**
 * Banners API — public endpoint for active banners.
 * CRUD is handled by the generic CRUD system in crud.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/crud.php';

/** Public: return active banners within date range, sorted by sort_order. */
function handle_banners_public(): void
{
    $all = collection_list('banners');
    // Admin enters start/end as local wall-times (datetime-local input, no TZ
    // suffix). now_db() is UTC, which would skew the boundary comparison by the
    // server-vs-UTC offset — compare against the server-local clock instead.
    $now = date('Y-m-d H:i:s');
    $active = array_filter($all, function ($b) use ($now) {
        if ((int) ($b['is_active'] ?? 0) !== 1) return false;
        $starts = $b['starts_at'] ?? null;
        $ends   = $b['ends_at'] ?? null;
        if ($starts !== null && $starts !== '' && $starts > $now) return false;
        if ($ends !== null && $ends !== '' && $ends < $now) return false;
        return true;
    });
    usort($active, function ($a, $b) {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });
    ok(array_values($active));
}
