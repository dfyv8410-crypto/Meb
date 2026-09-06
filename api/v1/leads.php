<?php
/**
 * Leads (CRM) + public lead creation from the contact form.
 */

declare(strict_types=1);

function handle_lead_public(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('Not found', 404);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (rate_limited('lead:' . $ip, 10, 300)) fail('Too many requests', 429);
    $b = read_body();
    $name  = trim((string) ($b['name'] ?? ''));
    $phone = trim((string) ($b['phone'] ?? ''));
    if ($name === '' || $phone === '') fail('Name and phone required', 400);
    if (mb_strlen($name) > 200) fail('Name too long', 400);
    if (mb_strlen($phone) > 30) fail('Phone too long', 400);
    $email = strtolower(trim((string) ($b['email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email', 400);
    $message = trim((string) ($b['message'] ?? ''));
    if (mb_strlen($message) > 5000) fail('Message too long', 400);
    $source = trim((string) ($b['source'] ?? 'web'));
    if (mb_strlen($source) > 50) fail('Source too long', 400);
    // Strip control characters (CRLF etc.) — fields are used in mail headers.
    $stripCtrl = function (?string $s): string {
        return (string) preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', (string) $s);
    };
    $name    = $stripCtrl($name);
    $phone   = $stripCtrl($phone);
    $email   = $stripCtrl($email);
    $message = $stripCtrl($message);
    $source  = $stripCtrl($source);
    $id = new_id();
    $st = db()->prepare(
        'INSERT INTO requests (id,name,phone,email,message,status,source,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $id, sanitize($name), sanitize($phone),
        sanitize($email), sanitize($message),
        'new', sanitize($source), now_db(), now_db(),
    ]);
    // notification (best-effort — a notification insert must not fail the lead)
    $noteId = null;
    try {
        $noteBody = "Телефон: " . sanitize($phone) . "." . ($message !== '' ? " " . sanitize($message) : '');
        $noteId = new_id();
        $st = db()->prepare(
            'INSERT INTO notifications (id,type,title,body,meta,`read`,created_at,updated_at)
             VALUES (?,?,?,?,?,0,?,?)'
        );
        $st->execute([$noteId, 'lead', 'Новая заявка: ' . $name, $noteBody, null, now_db(), now_db()]);
    } catch (\Throwable $e) {
        $noteId = null;
        error_log('lead notification insert failed: ' . $e->getMessage());
    }
    // optional email notification (best-effort, shared-host friendly)
    $s = get_settings();
    $notifyEmail = $s['notifyEmail'] ?? '';
    if ($notifyEmail !== '' && $noteId !== null) {
        $sent = @mail($notifyEmail, 'Новая заявка: ' . $name, $noteBody,
            "From: " . ($s['email'] ?? '') . "\r\nContent-Type: text/plain; charset=utf-8");
        if (!$sent) {
            db()->prepare("UPDATE notifications SET meta=? WHERE id=?")
               ->execute([json_encode(['emailError' => 'mail() failed']), $noteId]);
        }
    }
    audit_log('create', 'leads', $id);
    ok(['id' => $id, 'name' => $name, 'phone' => $phone, 'status' => 'new']);
}

function handle_leads(string $M, array $seg): void
{
    require_once __DIR__ . '/crud.php';

    if ($M === 'GET' && !isset($seg[0])) {
        $u = current_user();
        if (!can($u, 'any')) fail('Unauthorized', 401);
        ok(collection_list('requests'));
    }
    if ($M === 'GET' && isset($seg[0])) {
        $u = current_user();
        if (!can($u, 'any')) fail('Unauthorized', 401);
        $row = find_row('requests', $seg[0]);
        if (!$row) fail('Not found', 404);
        ok($row);
    }
    // writes require manager+
    $u = current_user();
    if (!can($u, 'manager')) fail('Forbidden', 403);

    if ($M === 'POST') {
        // leads created only via public form; admin create allowed for CRM
        $b = read_body();
        $name = trim((string) ($b['name'] ?? ''));
        $phone = trim((string) ($b['phone'] ?? ''));
        if ($name === '' || $phone === '') fail('Name and phone required', 400);
        $row = collection_insert('requests', [
            'name' => sanitize($name), 'phone' => sanitize($phone),
            'email' => sanitize((string)($b['email'] ?? '')),
            'message' => sanitize((string)($b['message'] ?? '')),
            'status' => valid_status($b['status'] ?? null) ? $b['status'] : 'new',
            'comment' => sanitize((string)($b['comment'] ?? '')),
        ]);
        audit_log('create', 'leads', $row['id']);
        ok($row, 200);
    }
    if ($M === 'PUT' && isset($seg[0])) {
        $row = find_row('requests', $seg[0]);
        if (!$row) fail('Not found', 404);
        $b = read_body();
        if (isset($b['status'])) {
            if (!valid_status($b['status'])) fail('Invalid status', 400);
            $b['status'] = $b['status'];
        }
        foreach (['name','phone','email','message','comment'] as $k) {
            if (isset($b[$k])) $b[$k] = sanitize((string) $b[$k]);
        }
        $row = collection_update('requests', $row['id'], $b);
        audit_log('update', 'leads', $row['id']);
        ok($row);
    }
    if ($M === 'DELETE' && isset($seg[0])) {
        $row = find_row('requests', $seg[0]);
        if (!$row) fail('Not found', 404);
        collection_delete('requests', $row['id']);
        audit_log('delete', 'leads', $row['id']);
        ok(true);
    }
    fail('Not found', 404);
}
