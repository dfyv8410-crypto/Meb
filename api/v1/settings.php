<?php
/**
 * Settings endpoints: GET / PUT (admin only).
 * GET is admin-gated because the full settings include secrets
 * (smtp.pass, fcm.key). Public pages read $s via layout(), not this endpoint.
 */

declare(strict_types=1);

function handle_settings(string $M): void
{
    $u = current_user();
    if (!can($u, 'admin')) fail('Forbidden', 403);
    if ($M === 'GET') {
        ok(get_settings());
    }
    if ($M === 'PUT') {
        if (!csrf_check_ok()) fail('Invalid CSRF', 403);
        $b = read_body();
        if (isset($b['csrf'])) unset($b['csrf']);
        $saved = save_settings($b);
        audit_log('update', 'settings', 'site');
        ok($saved);
    }
    fail('Not found', 404);
}
