<?php
/**
 * Validation helpers — small, keyed to the existing API contracts.
 */

declare(strict_types=1);

function is_valid_email(?string $e): bool
{
    return filter_var((string) $e, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_slug(?string $s): bool
{
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $s);
}

function valid_status(?string $s): bool
{
    return in_array((string) $s, ['new', 'in_progress', 'contacted', 'done', 'rejected'], true);
}

function valid_role(?string $s): bool
{
    return in_array((string) $s, ['super_admin', 'admin', 'manager', 'editor'], true);
}
