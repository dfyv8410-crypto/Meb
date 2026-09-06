<?php
/**
 * Users endpoints (privileged): list / create / update / delete with role guards.
 * RBAC rules (enforced here, in addition to the generic admin gate):
 *  - only super_admin may create/assign the super_admin role
 *  - only super_admin may modify or delete an existing admin/super_admin user
 *  - nobody may escalate their own row to a privileged role
 */

declare(strict_types=1);

function handle_users(string $M, ?string $id): void
{
    $u = current_user();
    if (!can($u, 'admin')) fail('Forbidden', 403);
    $isSuper = can($u, 'super_admin');

    if ($M === 'GET' && $id === null) {
        ok(array_map('pub_user', all_rows('users')));
    }

    if ($M === 'GET' && $id !== null) {
        $row = find_row('users', $id);
        if (!$row) fail('Not found', 404);
        ok(pub_user($row));
    }

    if ($M === 'POST') {
        $b = read_body();
        $email = strtolower(trim((string) ($b['email'] ?? '')));
        $pass  = (string) ($b['password'] ?? '');
        if ($email === '' || $pass === '') fail('email и password обязательны', 400);
        $name = sanitize((string) ($b['name'] ?? ''));
        $role = valid_role($b['role'] ?? null) ? $b['role'] : 'editor';
        // Only super_admin may mint new privileged (admin/super_admin) accounts
        if (in_array($role, ['admin', 'super_admin'], true) && !$isSuper) {
            fail('Forbidden', 403);
        }
        $idn = new_id();
        $st = db()->prepare('INSERT INTO users (id,email,name,role,pass_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$idn, $email, $name, $role,
                      password_hash($pass, PASSWORD_DEFAULT), now_db(), now_db()]);
        audit_log('create', 'users', $idn);
        ok(['id'=>$idn,'email'=>$email,'name'=>$name,'role'=>$role]);
    }

    if ($M === 'PUT' && $id !== null) {
        $row = find_row('users', $id);
        if (!$row) fail('Not found', 404);
        $b = read_body();
        $name = array_key_exists('name', $b) ? sanitize((string) $b['name']) : $row['name'];
        $email = array_key_exists('email', $b) ? strtolower(trim((string) $b['email'])) : $row['email'];
        $role = array_key_exists('role', $b) ? (valid_role($b['role']) ? $b['role'] : $row['role']) : $row['role'];
        // RBAC: privileged rows (admin/super_admin) are only touchable by super_admin
        $targetPrivileged = in_array($row['role'], ['admin', 'super_admin'], true);
        $becomingPrivileged = in_array($role, ['admin', 'super_admin'], true);
        if (($targetPrivileged || $becomingPrivileged) && !$isSuper) {
            fail('Forbidden', 403);
        }
        // Nobody may promote themselves to a privileged role
        if ((string) $u['id'] === (string) $id && in_array($role, ['admin', 'super_admin'], true) && $row['role'] !== $role && !$isSuper) {
            fail('Forbidden', 403);
        }
        $passHash = $row['pass_hash'];
        if (!empty($b['password'])) $passHash = password_hash((string) $b['password'], PASSWORD_DEFAULT);
        $st = db()->prepare('UPDATE users SET name=?, email=?, role=?, pass_hash=?, updated_at=? WHERE id=?');
        $st->execute([$name, $email, $role, $passHash, now_db(), $id]);
        audit_log('update', 'users', $id);
        ok(['id'=>$id,'email'=>$email,'name'=>$name,'role'=>$role]);
    }

    if ($M === 'DELETE' && $id !== null) {
        if ((string) $u['id'] === (string) $id) fail('Нельзя удалить себя', 400);
        $row = find_row('users', $id);
        if (!$row) fail('Not found', 404);
        // Only super_admin may delete a super_admin account
        if ($row['role'] === 'super_admin' && !$isSuper) {
            fail('Forbidden', 403);
        }
        // Last-admin guard: cannot delete last remaining admin/super_admin
        if (in_array($row['role'], ['admin','super_admin'], true)) {
            $st = db()->prepare("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin')");
            $cnt = (int) $st->fetchColumn();
            if ($cnt <= 1) fail('Последний админ не может быть удалён', 400);
        }
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        audit_log('delete', 'users', $id);
        ok(true);
    }

    fail('Not found', 404);
}
