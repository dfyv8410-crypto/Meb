<?php
/**
 * Auth endpoints: login / logout / me.
 */

declare(strict_types=1);

function handle_auth(string $M, array $seg): void
{
    $sub = $seg[0] ?? '';

    if ($M === 'POST' && $sub === 'login') {
        if (login_rate_limited($_SERVER['REMOTE_ADDR'] ?? '')) fail('Too many requests', 429);
        $b = read_body();
        $email = strtolower(trim((string) ($b['email'] ?? '')));
        $pass  = (string) ($b['password'] ?? '');
        if ($email === '' || $pass === '') fail('Email и пароль обязательны', 400);
        $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch();
        if (!$user || !verify_password($pass, $user)) fail('Invalid credentials', 401);
        $token = sign_token(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        header('Set-Cookie: token=' . $token . '; Path=/; HttpOnly; SameSite=Lax' . ($isSecure ? '; Secure' : ''), false);
        audit_log('login', 'auth', $user['id']);
        ok(['token' => $token, 'csrf' => csrf_token(), 'user' => pub_user($user)]);
    }

    if ($M === 'POST' && $sub === 'logout') {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        header('Set-Cookie: token=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0' . ($isSecure ? '; Secure' : ''), false);
        ok(true);
    }

    if ($M === 'GET' && $sub === 'me') {
        $u = current_user();
        if (!$u) fail('Unauthorized', 401);
        ok(pub_user($u));
    }

    fail('Not found', 404);
}
