<?php
/**
 * Auth: stateless HMAC token (matches Node security.js) + PHP fallback sessions.
 * Password hashing via PHP password_hash() (Bcrypt), with legacy PBKDF2 verify
 * for migrated users.
 */

declare(strict_types=1);

const ROLE_LEVEL = ['editor' => 1, 'manager' => 2, 'admin' => 3, 'super_admin' => 4];

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'), true);
}

/** HMAC-signed stateless token: base64url(payload).base64url(hmac). */
function sign_token(array $payload): string
{
    $b = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac('sha256', $b, MEB_JWT_SECRET, true));
    return $b . '.' . $sig;
}

/** Verify + decode token; returns payload array or null. */
function verify_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;
    [$b, $sig] = $parts;
    $expect = base64url_encode(hash_hmac('sha256', $b, MEB_JWT_SECRET, true));
    if (!hash_equals($expect, $sig)) return null;
    $payload = json_decode(base64url_decode($b), true);
    return is_array($payload) ? $payload : null;
}

/** Extract current user (fresh from DB) from Authorization header or cookie. */
function current_user(): ?array
{
    $token = null;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $h, $m)) $token = trim($m[1]);
    if (!$token && isset($_COOKIE['token'])) $token = $_COOKIE['token'];
    if (!$token) return null;
    $p = verify_token($token);
    if (!$p || empty($p['id'])) return null;
    return find_row('users', $p['id']);
}

/** Public-safe projection of a user row (no secrets). */
function pub_user(array $u): array
{
    return [
        'id'    => $u['id'],
        'email' => $u['email'],
        'name'  => $u['name'],
        'role'  => $u['role'],
    ];
}

/** RBAC check: does user meet required role (or above, or 'any')? */
function can(?array $user, string $need): bool
{
    if (!$user) return false;
    if ($need === 'any') return true;
    $have = ROLE_LEVEL[$user['role']] ?? 0;
    $needL = ROLE_LEVEL[$need] ?? 0;
    return $have >= $needL;
}

/** Login: verify password against pass_hash (and legacy PBKDF2). */
function verify_password(string $password, array $user): bool
{
    if (!empty($user['pass_hash'])) {
        return password_verify($password, $user['pass_hash']);
    }
    // legacy PBKDF2-SHA256(100k, 32B) — check salt+hash columns
    if (!empty($user['salt']) && !empty($user['hash'])) {
        $salt = hex2bin($user['salt']);
        if ($salt === false) return false;
        $calc = hash_pbkdf2('sha256', $password, $salt, 100000, 32);
        return hash_equals(strtolower($user['hash']), strtolower($calc));
    }
    return false;
}

/** Login rate limit (shared-host friendly, DB-backed). */
function login_rate_limited(string $ip): bool
{
    $window = (int) floor(time() / 60);          // 1-minute window
    $bucket = 'login';
    $st = db()->prepare(
        'INSERT INTO rate_limits (bucket, ip, window_ts, count) VALUES (?,?,?,1)
         ON DUPLICATE KEY UPDATE count = count + 1'
    );
    $st->execute([$bucket, $ip, $window]);
    $st2 = db()->prepare('SELECT count FROM rate_limits WHERE bucket=? AND ip=? AND window_ts=?');
    $st2->execute([$bucket, $ip, $window]);
    $count = (int) ($st2->fetchColumn() ?: 0);
    return $count > 10; // 10/min/IP
}

/** Generic rate limit check. Returns true if rate-limited.
 *  @param string $bucket  Rate-limit bucket name (e.g. 'lead', 'review')
 *  @param int    $max     Maximum allowed requests per window
 *  @param int    $window  Window size in seconds (default 300 = 5 min)
 */
function rate_limited(string $bucket, int $max = 10, int $window = 300): bool
{
    $windowTs = (int) floor(time() / $window);
    $st = db()->prepare(
        'INSERT INTO rate_limits (bucket, ip, window_ts, count) VALUES (?,?,?,1)
         ON DUPLICATE KEY UPDATE count = count + 1'
    );
    $st->execute([$bucket, '', $windowTs]);
    $st2 = db()->prepare('SELECT count FROM rate_limits WHERE bucket=? AND window_ts=?');
    $st2->execute([$bucket, $windowTs]);
    $count = (int) ($st2->fetchColumn() ?: 0);
    return $count > $max;
}
